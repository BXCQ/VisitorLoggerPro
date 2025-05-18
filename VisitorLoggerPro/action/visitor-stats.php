<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$db = Typecho_Db::get();
$prefix = $db->getPrefix();

// 获取统计周期
$period = isset($_GET['period']) ? intval($_GET['period']) : 7;
$where = '';

if ($period > 0) {
    $where = "WHERE time >= DATE_SUB(NOW(), INTERVAL {$period} DAY)";
}

// 获取访问趋势数据
$trendSql = "SELECT DATE(time) as date, COUNT(*) as count 
             FROM {$prefix}visitor_log 
             {$where}
             GROUP BY DATE(time)
             ORDER BY date DESC
             LIMIT {$period}";

$trendData = $db->fetchAll($db->query($trendSql));
$trend = [
    'dates' => array_reverse(array_column($trendData, 'date')),
    'counts' => array_reverse(array_column($trendData, 'count'))
];

// 获取国家分布数据
$countrySql = "SELECT country, COUNT(*) as count 
               FROM {$prefix}visitor_log 
               {$where}
               GROUP BY country 
               ORDER BY count DESC 
               LIMIT 10";

$countryData = $db->fetchAll($db->query($countrySql));
$countries = array_map(function ($item) {
    return [
        'name' => $item['country'],
        'value' => $item['count']
    ];
}, $countryData);

// 获取热门页面数据
$routeSql = "SELECT route, COUNT(*) as count 
             FROM {$prefix}visitor_log 
             {$where}
             GROUP BY route 
             ORDER BY count DESC 
             LIMIT 10";

$routeData = $db->fetchAll($db->query($routeSql));
$routes = array_map(function ($item) {
    return [
        'name' => urldecode($item['route']),
        'value' => $item['count']
    ];
}, $routeData);

// 获取访问时段数据
$timeSql = "SELECT HOUR(time) as hour, COUNT(*) as count 
            FROM {$prefix}visitor_log 
            {$where}
            GROUP BY HOUR(time) 
            ORDER BY hour";

$timeData = $db->fetchAll($db->query($timeSql));
$times = [
    'hours' => array_column($timeData, 'hour'),
    'counts' => array_column($timeData, 'count')
];

// 返回JSON数据
header('Content-Type: application/json');
echo json_encode([
    'trend' => $trend,
    'countries' => $countries,
    'routes' => $routes,
    'times' => $times
]);
