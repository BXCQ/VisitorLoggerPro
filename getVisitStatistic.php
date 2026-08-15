<?php
// 设置最大执行时间，避免超时
set_time_limit(30);

// 彻底清理输出缓冲区，防止任何意外的输出。
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma');
header('Access-Control-Max-Age: 1728000');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    header('HTTP/1.1 200 OK');
    exit;
}

// 添加缓存控制头，确保响应不被缓存
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// API的错误处理：记录错误到日志，但不显示在输出中，避免JSON损坏。
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 确保 Typecho 环境已加载
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
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

// 检查 Typecho 是否成功加载
if (!class_exists('\\Typecho\\Db') && !class_exists('Typecho_Db')) {
    error_log("Typecho not loaded correctly.");
    ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Typecho not loaded correctly'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 处理 API 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 获取请求数据
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            if ($input === false) {
                throw new Exception('Failed to read input data');
            }

            $request = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data: ' . json_last_error_msg());
            }

            $startDate = $request['startDate'] ?? null;
            $endDate = $request['endDate'] ?? null;
        } else {
            // 支持GET请求，方便调试
            $startDate = $_GET['startDate'] ?? null;
            $endDate = $_GET['endDate'] ?? null;
        }

        if (!$startDate || !$endDate) {
            // 如果没有提供日期，使用默认值（最近7天）
            $endDate = date('Y-m-d 23:59:59');
            $startDate = date('Y-m-d 00:00:00', strtotime('-6 days'));
        }

        $provinces = [
            "北京",
            "上海",
            "天津",
            "重庆",
            "河北",
            "山西",
            "内蒙古",
            "辽宁",
            "吉林",
            "黑龙江",
            "江苏",
            "浙江",
            "安徽",
            "福建",
            "江西",
            "山东",
            "河南",
            "湖北",
            "湖南",
            "广东",
            "广西",
            "海南",
            "四川",
            "贵州",
            "云南",
            "西藏",
            "陕西",
            "甘肃",
            "宁夏",
            "青海",
            "新疆",
            "香港",
            "澳门",
            "台湾"
        ];

        // 根据Typecho版本选择正确的方式获取Db实例
        if (class_exists('\\Typecho\\Db')) {
            $db = \Typecho\Db::get();
        } else {
            $db = Typecho_Db::get();
        }
        $prefix = $db->getPrefix();

        // 确保表存在
        try {
            // 测试表是否存在
            $tableExists = $db->fetchRow($db->select()->from($prefix . 'visitor_log')->limit(1));

            if ($tableExists === false) {
                throw new Exception("访问日志表不存在");
            }
        } catch (Exception $e) {
            error_log('Error checking visitor_log table: ' . $e->getMessage());
            ob_end_clean();
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'error' => '数据表访问错误',
                'message' => $e->getMessage(),
                'debug_info' => 'Failed to access visitor_log table'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 使用缓存机制提高性能
        $cacheKey = md5($startDate . $endDate);
        $cacheFile = sys_get_temp_dir() . '/visitor_stats_' . $cacheKey . '.json';
        $cacheExpire = 300; // 5分钟缓存

        // 检查是否有可用缓存
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheExpire)) {
            $cachedData = file_get_contents($cacheFile);
            if ($cachedData !== false) {
                ob_end_clean();
                echo $cachedData;
                exit;
            }
        }

        // 获取总访问量
        $totalVisitsResult = $db->fetchObject(
            $db->select('COUNT(id) as total')
                ->from($prefix . 'visitor_log')
                ->where('time >= ?', $startDate)
                ->where('time <= ?', $endDate)
        );
        $totalVisits = $totalVisitsResult->total;

        // 获取国家和地区访问数据
        $countryCountsResult = $db->fetchAll(
            $db->select('country', 'COUNT(id) as count')
                ->from($prefix . 'visitor_log')
                ->where('time >= ?', $startDate)
                ->where('time <= ?', $endDate)
                ->group('country')
                ->order('count', Typecho_Db::SORT_DESC)
        );

        $countryData = [];
        $provinceData = [];
        $totalCountries = 0;

        foreach ($countryCountsResult as $row) {
            $countryName = $row['country'] ?: '未知';
            $count = $row['count'];

            if (!isset($countryData[$countryName])) {
                $countryData[$countryName] = 0;
                $totalCountries++;
            }
            $countryData[$countryName] += $count;

            if (strpos($countryName, '中国') !== false) {
                foreach ($provinces as $province) {
                    if (strpos($countryName, $province) !== false) {
                        if (!isset($provinceData[$province])) {
                            $provinceData[$province] = 0;
                        }
                        $provinceData[$province] += $count;
                        break;
                    }
                }
            }
        }

        // 获取路由访问数据
        $routeCountsResult = $db->fetchAll(
            $db->select("SUBSTRING_INDEX(route, '?', 1) as clean_route", 'COUNT(id) as count')
                ->from($prefix . 'visitor_log')
                ->where('time >= ?', $startDate)
                ->where('time <= ?', $endDate)
                ->group('clean_route')
        );

        $routeCounts = [];
        foreach ($routeCountsResult as $row) {
            $decodedRoute = urldecode($row['clean_route']);
            $routeCounts[$decodedRoute] = $row['count'];
        }

        arsort($countryData);
        arsort($provinceData);
        arsort($routeCounts);

        // 保存完整数据副本
        $allCountryData = $countryData;
        $allProvinceData = $provinceData;

        // 只保留前30个国家/地区（确保不过滤掉任何数据）
        $countryData = array_slice($countryData, 0, 30, true);

        // 只保留前30个省份（确保不过滤掉任何数据）
        $provinceData = array_slice($provinceData, 0, 30, true);

        // 只保留前20个路由
        $routeCounts = array_slice($routeCounts, 0, 20, true);

        $result = [
            'countryData' => $countryData,
            'provinceData' => $provinceData,
            'routeData' => array_filter($routeCounts, function ($count) {
                return $count > 0;
            }),
            'totalVisits' => $totalVisits,
            'totalCountries' => $totalCountries
        ];

        // 将结果缓存到临时文件
        $jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($cacheFile, $jsonResult);

        // 在输出前再次清理缓冲区，并发送响应
        ob_end_clean();
        echo $jsonResult;
        exit;
    } catch (Exception $e) {
        error_log('Error in getVisitStatistic.php: ' . $e->getMessage());

        // 在输出前再次清理缓冲区，并发送响应
        ob_end_clean();
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
} else {
    // 返回错误响应
    ob_end_clean();
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid request method'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
