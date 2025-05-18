<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

use Typecho\Common;
use Typecho\Db;

// 确保 Typecho 环境已加载
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
    require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
    require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
    Typecho_Common::init();
}

// 检查 Typecho 是否成功加载
if (!class_exists('Typecho_Db')) {
    error_log("Typecho not loaded correctly.");
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Typecho not loaded correctly'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 处理 API 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取请求数据
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

        if (!$startDate || !$endDate) {
            throw new Exception('Missing startDate or endDate');
        }

        // 调用插件方法获取访客日志
        $countries = [
            "中国",
            "美国",
            "加拿大",
            "英国",
            "法国",
            "德国",
            "澳大利亚",
            "日本",
            "韩国",
            "印度",
            "俄罗斯",
            "巴西",
            "南非",
            "意大利",
            "西班牙",
            "墨西哥",
            "印度尼西亚",
            "荷兰",
            "瑞士",
            "瑞典",
            "挪威",
            "丹麦",
            "芬兰",
            "比利时",
            "奥地利",
            "爱尔兰",
            "新西兰",
            "阿根廷",
            "智利",
            "哥伦比亚",
            "秘鲁",
            "委内瑞拉",
            "乌拉圭",
            "巴拉圭",
            "玻利维亚",
            "厄瓜多尔",
            "古巴",
            "牙买加",
            "海地",
            "多米尼加共和国",
            "波多黎各",
            "特立尼达和多巴哥",
            "巴巴多斯",
            "圣卢西亚",
            "圣基茨和尼维斯",
            "安提瓜和巴布达",
            "格林纳达",
            "圣文森特和格林纳丁斯",
            "巴哈马",
            "伯利兹",
            "哥斯达黎加",
            "萨尔瓦多",
            "危地马拉",
            "洪都拉斯",
            "尼加拉瓜",
            "巴拿马",
            "阿尔巴尼亚",
            "亚美尼亚",
            "阿塞拜疆",
            "白俄罗斯",
            "波黑",
            "保加利亚",
            "克罗地亚",
            "塞浦路斯",
            "捷克",
            "爱沙尼亚",
            "格鲁吉亚",
            "希腊",
            "匈牙利",
            "冰岛",
            "哈萨克斯坦",
            "科索沃",
            "吉尔吉斯斯坦",
            "拉脱维亚",
            "立陶宛",
            "卢森堡",
            "北马其顿",
            "马耳他",
            "摩尔多瓦",
            "黑山",
            "波兰",
            "葡萄牙",
            "罗马尼亚",
            "塞尔维亚",
            "斯洛伐克",
            "斯洛文尼亚",
            "塔吉克斯坦",
            "土耳其",
            "土库曼斯坦",
            "乌克兰",
            "乌兹别克斯坦",
            "阿尔及利亚",
            "安哥拉",
            "贝宁",
            "博茨瓦纳",
            "布基纳法索",
            "布隆迪",
            "佛得角",
            "喀麦隆",
            "中非共和国",
            "乍得",
            "科摩罗",
            "刚果共和国",
            "刚果民主共和国",
            "吉布提",
            "埃及",
            "赤道几内亚",
            "厄立特里亚",
            "埃塞俄比亚",
            "加蓬",
            "冈比亚",
            "加纳",
            "几内亚",
            "几内亚比绍",
            "科特迪瓦",
            "肯尼亚",
            "莱索托",
            "利比里亚",
            "利比亚",
            "马达加斯加",
            "马拉维",
            "马里",
            "毛里塔尼亚",
            "毛里求斯",
            "摩洛哥",
            "莫桑比克",
            "纳米比亚",
            "尼日尔",
            "尼日利亚",
            "卢旺达",
            "圣多美和普林西比",
            "塞内加尔",
            "塞舌尔",
            "塞拉利昂",
            "索马里",
            "南非",
            "南苏丹",
            "苏丹",
            "斯威士兰",
            "坦桑尼亚",
            "多哥",
            "突尼斯",
            "乌干达",
            "赞比亚",
            "津巴布韦",
            "阿富汗",
            "巴林",
            "孟加拉国",
            "不丹",
            "文莱",
            "柬埔寨",
            "中国",
            "印度",
            "印度尼西亚",
            "伊朗",
            "伊拉克",
            "以色列",
            "日本",
            "约旦",
            "哈萨克斯坦",
            "科威特",
            "吉尔吉斯斯坦",
            "老挝",
            "黎巴嫩",
            "马来西亚",
            "马尔代夫",
            "蒙古",
            "缅甸",
            "尼泊尔",
            "朝鲜",
            "阿曼",
            "巴基斯坦",
            "巴勒斯坦",
            "菲律宾",
            "卡塔尔",
            "沙特阿拉伯",
            "新加坡",
            "韩国",
            "斯里兰卡",
            "叙利亚",
            "塔吉克斯坦",
            "泰国",
            "东帝汶",
            "土耳其",
            "土库曼斯坦",
            "阿联酋",
            "乌兹别克斯坦",
            "越南",
            "也门",
            "澳大利亚",
            "斐济",
            "基里巴斯",
            "马绍尔群岛",
            "密克罗尼西亚",
            "瑙鲁",
            "新西兰",
            "帕劳",
            "巴布亚新几内亚",
            "萨摩亚",
            "所罗门群岛",
            "汤加",
            "图瓦卢",
            "瓦努阿图",
            "未知地区"
        ];

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
            "台湾",
            "未知地区"
        ];

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 获取国家访问数据
        $logsToday = $db->fetchAll(
            $db->select('country')
                ->from($prefix . 'visitor_log')
                ->where('time >= ?', $startDate)
                ->where('time <= ?', $endDate)
        );

        $countryVisitCounts = array_fill_keys($countries, 0);
        $logsChina = array();

        foreach ($logsToday as $log) {
            $country = $log['country'];
            foreach ($countries as $countryName) {
                if (strpos($country, "中国") !== false) {
                    array_push($logsChina, $country);
                }
                if (strpos($country, $countryName) !== false) {
                    $countryVisitCounts[$countryName]++;
                    break;
                }
            }
        }

        // 获取省份访问数据
        $provincesVisitCounts = array_fill_keys($provinces, 0);
        foreach ($logsChina as $log) {
            foreach ($provinces as $province) {
                if (strpos($log, $province) !== false) {
                    $provincesVisitCounts[$province]++;
                    break;
                }
            }
        }

        // 获取路由访问数据
        $selectedRoutes = $db->fetchAll(
            $db->select('route')
                ->from($prefix . 'visitor_log')
                ->where('time >= ?', $startDate)
                ->where('time <= ?', $endDate)
        );

        $routeCounts = [];
        foreach ($selectedRoutes as $row) {
            $route = explode('?', $row['route'])[0];
            $decodedRoute = urldecode($route);
            if (isset($routeCounts[$decodedRoute])) {
                $routeCounts[$decodedRoute]++;
            } else {
                $routeCounts[$decodedRoute] = 1;
            }
        }

        arsort($countryVisitCounts);
        arsort($provincesVisitCounts);
        arsort($routeCounts);

        $result = [
            'countryData' => array_filter($countryVisitCounts, function ($count) {
                return $count > 0;
            }),
            'provinceData' => array_filter($provincesVisitCounts, function ($count) {
                return $count > 0;
            }),
            'routeData' => array_filter($routeCounts, function ($count) {
                return $count > 0;
            })
        ];

        // 返回响应
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 确保输出缓冲区是干净的
        if (ob_get_level()) {
            ob_end_clean();
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Exception $e) {
        error_log('Error in getVisitStatistic.php: ' . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (ob_get_level()) {
            ob_end_clean();
        }

        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
} else {
    // 返回错误响应
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode(['error' => 'Invalid request method'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
