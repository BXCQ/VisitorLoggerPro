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

    // 兼容不同版本的Typecho
    if (file_exists(__TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php')) {
        require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
        \Typecho\Common::init();
    } else if (file_exists(__TYPECHO_ROOT_DIR__ . '/var/Common.php')) {
        require_once __TYPECHO_ROOT_DIR__ . '/var/Common.php';
        Typecho_Common::init();
    }
}

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

    // 补齐查询索引（幂等）
    require_once dirname(__FILE__) . '/DbOptimize.php';
    VisitorLoggerPro_DbOptimize::ensureIndexes($db, $prefix);

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
 */
function calculateSessions($db, $prefix, $whereClause)
{
    try {
        $sql = "SELECT 
                    COUNT(*) as session_count
                FROM (
                    SELECT 
                        ip,
                        time,
                        CASE 
                            WHEN LAG(time) OVER (PARTITION BY ip ORDER BY time) IS NULL 
                                OR TIMESTAMPDIFF(MINUTE, LAG(time) OVER (PARTITION BY ip ORDER BY time), time) > 30 
                            THEN 1 
                            ELSE 0 
                        END as is_new_session
                    FROM {$prefix}visitor_log 
                    WHERE {$whereClause}
                ) as session_data
                WHERE is_new_session = 1";

        $result = $db->fetchRow($sql);
        return (int)($result['session_count'] ?? 0);
    } catch (Exception $e) {
        $sql = "SELECT COUNT(DISTINCT ip) as session_count FROM {$prefix}visitor_log WHERE {$whereClause}";
        $result = $db->fetchRow($sql);
        return (int)($result['session_count'] ?? 0);
    }
}

/**
 * 按天或按小时批量计算会话数（一次扫描，避免 N 次窗口函数查询）
 *
 * @param string $groupExpr SQL 分组表达式，如 DATE(time) 或 HOUR(time)
 * @return array key => session_count
 */
function calculateSessionsGrouped($db, $prefix, $whereClause, $groupExpr)
{
    try {
        $sql = "SELECT 
                    bucket,
                    COUNT(*) as session_count
                FROM (
                    SELECT 
                        {$groupExpr} as bucket,
                        CASE 
                            WHEN LAG(time) OVER (PARTITION BY ip ORDER BY time) IS NULL 
                                OR TIMESTAMPDIFF(MINUTE, LAG(time) OVER (PARTITION BY ip ORDER BY time), time) > 30 
                            THEN 1 
                            ELSE 0 
                        END as is_new_session
                    FROM {$prefix}visitor_log 
                    WHERE {$whereClause}
                ) as session_data
                WHERE is_new_session = 1
                GROUP BY bucket";

        $rows = $db->fetchAll($sql);
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['bucket']] = (int)$row['session_count'];
        }
        return $map;
    } catch (Exception $e) {
        // 旧版 MySQL：用独立 IP 数近似会话数
        $sql = "SELECT {$groupExpr} as bucket, COUNT(DISTINCT ip) as session_count
                FROM {$prefix}visitor_log
                WHERE {$whereClause}
                GROUP BY bucket";
        $rows = $db->fetchAll($sql);
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['bucket']] = (int)$row['session_count'];
        }
        return $map;
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
        // 使用时间范围条件，避免 DATE(time)= 导致无法走索引
        $rangeWhere = "time >= '{$startDate}' AND time <= '{$endDate}'";

        if ($isSingleDay) {
            // 单日数据：按小时分组
            $hours = [];
            for ($h = 0; $h < 24; $h++) {
                $hours[] = sprintf('%02d:00', $h);
            }

            $sql = "SELECT 
                        HOUR(time) as hour,
                        COUNT(*) as pv_count,
                        COUNT(DISTINCT ip) as unique_ip_count,
                        COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as unique_visitor_count
                    FROM {$prefix}visitor_log 
                    WHERE {$rangeWhere}
                    GROUP BY HOUR(time)
                    ORDER BY hour ASC";

            $results = $db->fetchAll($sql);

            $dataMap = [];
            foreach ($results as $row) {
                $hour = sprintf('%02d:00', $row['hour']);
                $dataMap[$hour] = [
                    'pv_count' => (int)$row['pv_count'],
                    'unique_ip_count' => (int)$row['unique_ip_count'],
                    'unique_visitor_count' => (int)$row['unique_visitor_count']
                ];
            }

            // 一次查出全天各小时会话数（替代 24 次窗口查询）
            $sessionByHour = calculateSessionsGrouped($db, $prefix, $rangeWhere, 'HOUR(time)');
            foreach ($hours as $hour) {
                $hourNum = (string)intval(substr($hour, 0, 2));
                $sessionCount = isset($sessionByHour[$hourNum]) ? $sessionByHour[$hourNum] : 0;
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

            $sql = "SELECT 
                        DATE(time) as date,
                        COUNT(*) as pv_count,
                        COUNT(DISTINCT ip) as unique_ip_count,
                        COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as unique_visitor_count
                    FROM {$prefix}visitor_log 
                    WHERE {$rangeWhere}
                    GROUP BY DATE(time)
                    ORDER BY date ASC";

            $results = $db->fetchAll($sql);

            $dataMap = [];
            foreach ($results as $row) {
                $dataMap[$row['date']] = [
                    'pv_count' => (int)$row['pv_count'],
                    'unique_ip_count' => (int)$row['unique_ip_count'],
                    'unique_visitor_count' => (int)$row['unique_visitor_count']
                ];
            }

            // 一次查出各天会话数（替代按天 N 次窗口查询）
            $sessionByDate = calculateSessionsGrouped($db, $prefix, $rangeWhere, 'DATE(time)');
            foreach ($dates as $date) {
                $sessionCount = isset($sessionByDate[$date]) ? $sessionByDate[$date] : 0;
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

        // 总数：从已聚合数据求和（PV），其余一次范围查询；会话一次计算
        $totalPv = 0;
        $totalUniqueIp = 0;
        $totalUniqueVisitor = 0;
        foreach ($data as $row) {
            $totalPv += (int)$row['pv_count'];
        }

        $totalSql = "SELECT 
                        COUNT(DISTINCT ip) as total_unique_ip,
                        COUNT(DISTINCT CONCAT(ip, IFNULL(user_agent, ''))) as total_unique_visitor
                    FROM {$prefix}visitor_log 
                    WHERE {$rangeWhere}";
        $totalResult = $db->fetchRow($totalSql);
        $totalUniqueIp = (int)($totalResult['total_unique_ip'] ?? 0);
        $totalUniqueVisitor = (int)($totalResult['total_unique_visitor'] ?? 0);
        $totalSession = calculateSessions($db, $prefix, $rangeWhere);

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
