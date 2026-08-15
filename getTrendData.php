<?php
// 设置错误报告级别，隐藏警告
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// 确保 Typecho 环境已加载
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
}

// 只有在 Typecho 环境未加载时才加载
if (!class_exists('Typecho_Db') && !class_exists('\\Typecho\\Db')) {
    require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

    // 兼容 Typecho 1.2 / 1.3 初始化
    if (class_exists('\\Widget\\Init')) {
        \Widget\Init::alloc();
    } elseif (file_exists(__TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php')) {
        require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
        if (method_exists('\\Typecho\\Common', 'init')) {
            \Typecho\Common::init();
        }
    } elseif (file_exists(__TYPECHO_ROOT_DIR__ . '/var/Common.php')) {
        require_once __TYPECHO_ROOT_DIR__ . '/var/Common.php';
        Typecho_Common::init();
    }
}

require_once dirname(__FILE__) . '/adapter.php';

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('仅支持POST请求');
    }

    // 获取数据库连接
    if (class_exists('\\Typecho\\Db')) {
        $db = \Typecho\Db::get();
    } else {
        $db = Typecho_Db::get();
    }

    if (!$db) {
        throw new Exception('数据库连接失败');
    }
    $prefix = $db->getPrefix();

    // 读取POST数据
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('未收到请求数据');
    }

    $requestData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON解析错误: ' . json_last_error_msg());
    }

    $startDate = isset($requestData['startDate']) ? $requestData['startDate'] : date('Y-m-d 00:00:00', strtotime('-7 days'));
    $endDate = isset($requestData['endDate']) ? $requestData['endDate'] : date('Y-m-d 23:59:59');

    // 处理特殊的"all"时间范围
    if ($startDate === 'all') {
        // 获取第一条记录的时间作为开始时间
        $firstRecord = $db->fetchRow("SELECT MIN(time) as first_time FROM {$prefix}visitor_log WHERE time IS NOT NULL");
        if ($firstRecord && $firstRecord['first_time']) {
            $startDate = date('Y-m-d 00:00:00', strtotime($firstRecord['first_time']));
        } else {
            // 如果没有记录，使用当前时间
            $startDate = date('Y-m-d 00:00:00');
        }
        $endDate = date('Y-m-d 23:59:59');
    }

    // 验证日期格式
    $startDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $startDate);
    $endDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $endDate);

    if (!$startDateTime || !$endDateTime) {
        throw new Exception('日期格式错误');
    }

    if ($startDateTime > $endDateTime) {
        throw new Exception('开始日期不能大于结束日期');
    }

    // 计算日期差
    $dateDiff = $startDateTime->diff($endDateTime)->days;
    if ($dateDiff > 365) {
        throw new Exception('查询时间范围不能超过365天');
    }

    // 查询趋势数据
    $result = getTrendData($db, $prefix, $startDate, $endDate);

    // 返回成功响应
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'typecho_defined' => defined('__TYPECHO_ROOT_DIR__'),
            'typecho_db_class' => class_exists('Typecho_Db'),
            'typecho_new_db_class' => class_exists('\\Typecho\\Db'),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 计算会话数（基于IP和时间间隔）
 * 同一IP在30分钟内的访问视为同一会话
 * 优化版：使用SQL窗口函数来提高性能
 */
function calculateSessions($db, $prefix, $whereClause)
{
    try {
        // 使用SQL计算会话数，性能更好
        // 如果两次访问间隔超过30分钟，则认为是新会话
        $sql = "SELECT 
                    COUNT(*) as session_count
                FROM (
                    SELECT 
                        ip,
                        time,
                        LAG(time) OVER (PARTITION BY ip ORDER BY time) as prev_time,
                        CASE 
                            WHEN LAG(time) OVER (PARTITION BY ip ORDER BY time) IS NULL 
                                OR TIMESTAMPDIFF(MINUTE, LAG(time) OVER (PARTITION BY ip ORDER BY time), time) > 30 
                            THEN 1 
                            ELSE 0 
                        END as is_new_session
                    FROM {$prefix}visitor_log 
                    WHERE {$whereClause}
                    ORDER BY ip, time
                ) as session_data
                WHERE is_new_session = 1";

        $result = $db->fetchRow($sql);
        return (int)($result['session_count'] ?? 0);
    } catch (Exception $e) {
        // 如果数据库不支持窗口函数，回退到简单计算
        // 对于较老的MySQL版本，简单地用独立IP数代替会话数
        $sql = "SELECT COUNT(DISTINCT ip) as session_count FROM {$prefix}visitor_log WHERE {$whereClause}";
        $result = $db->fetchRow($sql);
        return (int)($result['session_count'] ?? 0);
    }
}

/**
 * 获取趋势数据
 */
function getTrendData($db, $prefix, $startDate, $endDate)
{
    try {
        // 解析日期范围
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        $days = $interval->days + 1; // 包含结束日期

        // 判断是否为单日数据（需要按小时显示）
        $isSingleDay = $days === 1;

        if ($isSingleDay) {
            // 单日数据：按小时分组
            $hours = [];
            for ($h = 0; $h < 24; $h++) {
                $hours[] = sprintf('%02d:00', $h);
            }

            // 查询小时数据
            $sql = "SELECT 
                        HOUR(time) as hour,
                        COUNT(*) as pv_count,
                        COUNT(DISTINCT ip) as unique_ip_count,
                        COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as unique_visitor_count
                    FROM {$prefix}visitor_log 
                    WHERE DATE(time) = '" . date('Y-m-d', strtotime($startDate)) . "'
                    GROUP BY HOUR(time)
                    ORDER BY hour ASC";

            $results = $db->fetchAll($sql);

            // 创建结果映射
            $dataMap = [];
            foreach ($results as $row) {
                $hour = sprintf('%02d:00', $row['hour']);
                $dataMap[$hour] = [
                    'pv_count' => (int)$row['pv_count'],
                    'unique_ip_count' => (int)$row['unique_ip_count'],
                    'unique_visitor_count' => (int)$row['unique_visitor_count']
                ];
            }

            // 计算每小时的会话数
            $targetDate = date('Y-m-d', strtotime($startDate));
            foreach ($hours as $hour) {
                $hourNum = intval(substr($hour, 0, 2));
                $hourStart = $targetDate . ' ' . sprintf('%02d:00:00', $hourNum);
                $hourEnd = $targetDate . ' ' . sprintf('%02d:59:59', $hourNum);
                $whereClause = "time >= '{$hourStart}' AND time <= '{$hourEnd}'";
                $sessionCount = calculateSessions($db, $prefix, $whereClause);

                if (isset($dataMap[$hour])) {
                    $dataMap[$hour]['session_count'] = $sessionCount;
                } else {
                    $dataMap[$hour] = [
                        'pv_count' => 0,
                        'unique_ip_count' => 0,
                        'unique_visitor_count' => 0,
                        'session_count' => $sessionCount
                    ];
                }
            }

            // 填充完整小时数据
            $data = [];
            foreach ($hours as $hour) {
                $data[] = [
                    'date' => $hour,
                    'pv_count' => $dataMap[$hour]['pv_count'] ?? 0,
                    'unique_ip_count' => $dataMap[$hour]['unique_ip_count'] ?? 0,
                    'unique_visitor_count' => $dataMap[$hour]['unique_visitor_count'] ?? 0,
                    'session_count' => $dataMap[$hour]['session_count'] ?? 0
                ];
            }
        } else {
            // 多日数据：按天分组
            $dates = [];
            $current = clone $start;
            for ($i = 0; $i < $days; $i++) {
                $dates[] = $current->format('Y-m-d');
                $current->add(new DateInterval('P1D'));
            }

            // 查询数据
            $sql = "SELECT 
                        DATE(time) as date,
                        COUNT(*) as pv_count,
                        COUNT(DISTINCT ip) as unique_ip_count,
                        COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as unique_visitor_count
                    FROM {$prefix}visitor_log 
                    WHERE time >= '{$startDate}' AND time <= '{$endDate}'
                    GROUP BY DATE(time)
                    ORDER BY date ASC";

            $results = $db->fetchAll($sql);

            // 创建结果映射
            $dataMap = [];
            foreach ($results as $row) {
                $dataMap[$row['date']] = [
                    'pv_count' => (int)$row['pv_count'],
                    'unique_ip_count' => (int)$row['unique_ip_count'],
                    'unique_visitor_count' => (int)$row['unique_visitor_count']
                ];
            }

            // 计算每天的会话数
            foreach ($dates as $date) {
                $whereClause = "DATE(time) = '{$date}'";
                $sessionCount = calculateSessions($db, $prefix, $whereClause);

                if (isset($dataMap[$date])) {
                    $dataMap[$date]['session_count'] = $sessionCount;
                } else {
                    $dataMap[$date] = [
                        'pv_count' => 0,
                        'unique_ip_count' => 0,
                        'unique_visitor_count' => 0,
                        'session_count' => $sessionCount
                    ];
                }
            }

            // 填充完整日期数据
            $data = [];
            foreach ($dates as $date) {
                $data[] = [
                    'date' => $date,
                    'pv_count' => $dataMap[$date]['pv_count'] ?? 0,
                    'unique_ip_count' => $dataMap[$date]['unique_ip_count'] ?? 0,
                    'unique_visitor_count' => $dataMap[$date]['unique_visitor_count'] ?? 0,
                    'session_count' => $dataMap[$date]['session_count'] ?? 0
                ];
            }
        }

        // 计算总数（对于单日数据需要特别处理）
        if ($isSingleDay) {
            // 单日数据：统计整天的各项指标
            $totalSql = "SELECT 
                            COUNT(*) as total_pv,
                            COUNT(DISTINCT ip) as total_unique_ip,
                            COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as total_unique_visitor
                        FROM {$prefix}visitor_log 
                        WHERE DATE(time) = '" . date('Y-m-d', strtotime($startDate)) . "'";
            $totalResult = $db->fetchRow($totalSql);
            $totalPv = (int)($totalResult['total_pv'] ?? 0);
            $totalUniqueIp = (int)($totalResult['total_unique_ip'] ?? 0);
            $totalUniqueVisitor = (int)($totalResult['total_unique_visitor'] ?? 0);

            // 计算总会话数
            $whereClause = "DATE(time) = '" . date('Y-m-d', strtotime($startDate)) . "'";
            $totalSession = calculateSessions($db, $prefix, $whereClause);
        } else {
            // 多日数据：统计时间范围内的各项指标
            $totalSql = "SELECT 
                            COUNT(*) as total_pv,
                            COUNT(DISTINCT ip) as total_unique_ip,
                            COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as total_unique_visitor
                        FROM {$prefix}visitor_log 
                        WHERE time >= '{$startDate}' AND time <= '{$endDate}'";
            $totalResult = $db->fetchRow($totalSql);
            $totalPv = (int)($totalResult['total_pv'] ?? 0);
            $totalUniqueIp = (int)($totalResult['total_unique_ip'] ?? 0);
            $totalUniqueVisitor = (int)($totalResult['total_unique_visitor'] ?? 0);

            // 计算总会话数
            $whereClause = "time >= '{$startDate}' AND time <= '{$endDate}'";
            $totalSession = calculateSessions($db, $prefix, $whereClause);
        }

        return [
            'success' => true,
            'data' => $data,
            'range' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => $days,
                'is_single_day' => $isSingleDay
            ],
            'totals' => [
                'total_pv' => $totalPv,
                'total_unique_ip' => $totalUniqueIp,
                'total_unique_visitor' => $totalUniqueVisitor,
                'total_session' => $totalSession
            ]
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
