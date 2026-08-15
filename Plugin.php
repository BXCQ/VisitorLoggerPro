<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 访客统计插件
 * 
 * @package VisitorLoggerPro
 * @author 璇
 * @version 2.2.10
 * @link https://blog.ybyq.wang
 * @since 1.2.0
 */

// 加载兼容适配器
require_once dirname(__FILE__) . '/adapter.php';
require_once dirname(__FILE__) . '/DbOptimize.php';

require_once dirname(__FILE__) . '/ipdata/src/IpLocation.php';
require_once dirname(__FILE__) . '/ipdata/src/ipdbv6.func.php';

use vlp\Ip\IpLocation;

require_once dirname(__FILE__) . '/ip2region/src/XdbSearcher.php';

use vlp\ip2region\XdbSearcher;

class VisitorLoggerPro_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 升级: 检查 time 列是否为 TIMESTAMP 类型，如果是，则修改为 DATETIME
        // 并将现有数据从 UTC 转换为服务器设置的本地时间
        try {
            $col = $db->fetchRow($db->query("SHOW COLUMNS FROM `{$prefix}visitor_log` WHERE Field = 'time'"));
            if ($col && strpos(strtolower($col['Type']), 'timestamp') !== false) {
                $db->query("ALTER TABLE `{$prefix}visitor_log` MODIFY COLUMN `time` DATETIME NULL DEFAULT NULL");

                // convert existing timestamp data
                // get timezone offset from typecho settings
                $options = Helper::options();
                $timezone = $options->timezone;
                $offset = 0;
                if (!empty($timezone)) {
                    $tz = new DateTimeZone($timezone);
                    $datetime = new DateTime('now', $tz);
                    $offset = $tz->getOffset($datetime);
                }

                if ($offset != 0) {
                    // TIMESTAMP is stored as UTC. ALTER TABLE converts it to DATETIME in session timezone (likely UTC).
                    // So we add the blog's timezone offset.
                    $db->query("UPDATE `{$prefix}visitor_log` SET `time` = DATE_ADD(`time`, INTERVAL {$offset} SECOND) WHERE `time` IS NOT NULL");
                }
            }
        } catch (Exception $e) {
            // If it fails, it might be a database that doesn't support this syntax (like SQLite), or the table doesn't exist yet (new install).
            // Ignore the error and continue with the table creation logic below.
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}visitor_log` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL,
            `route` VARCHAR(255) NOT NULL,
            `country` VARCHAR(100),
            `region` VARCHAR(100),
            `city` VARCHAR(100),
            `user_agent` TEXT,
            `time` DATETIME DEFAULT NULL,
            KEY `idx_time` (`time`),
            KEY `idx_time_country` (`time`, `country`),
            KEY `idx_ip` (`ip`),
            KEY `idx_time_ip` (`time`, `ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        // ********如果提示UNSIGNED 或 AUTO_INCREMENT 或 ENGINE的相关错误，将上述代码替换成以下代码********
        //$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}visitor_log` (
        //    `id` INT(10) PRIMARY KEY,
        //    `ip` VARCHAR(45) NOT NULL,
        //    `route` VARCHAR(255) NOT NULL,
        //    `country` VARCHAR(100),
        //    `region` VARCHAR(100),
        //    `city` VARCHAR(100),
        //    `time` DATETIME DEFAULT NULL
        //);";

        try {
            $db->query($sql);

            // 检查是否需要添加user_agent字段（用于升级现有安装）
            try {
                $columns = $db->fetchAll("SHOW COLUMNS FROM `{$prefix}visitor_log`");
                $hasUserAgent = false;
                foreach ($columns as $column) {
                    if ($column['Field'] === 'user_agent') {
                        $hasUserAgent = true;
                        break;
                    }
                }

                if (!$hasUserAgent) {
                    $db->query("ALTER TABLE `{$prefix}visitor_log` ADD COLUMN `user_agent` TEXT AFTER `city`");
                }
            } catch (Exception $e) {
                // 如果添加字段失败，继续运行（可能是权限问题或数据库不支持）
            }

            // 为已有安装补齐索引（新安装建表时已带索引，此处幂等）
            VisitorLoggerPro_DbOptimize::ensureIndexes($db, $prefix);
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception('创建访客日志表或IP地址记录表失败: ' . $e->getMessage());
        }

        // 注册访客统计API
        Helper::addAction('visitor-stats-api', 'VisitorLogger_Action');

        // 注册统计模板和钩子（兼容 Typecho 1.2 / 1.3）
        // Plugin::factory 内部会将 Widget\Archive 归一为 Widget_Archive
        Typecho_Plugin::factory('Widget_Archive')->handle = array('VisitorLoggerPro_Plugin', 'handleTemplate');
        Typecho_Plugin::factory('Widget_Archive')->header = array('VisitorLoggerPro_Plugin', 'logVisitorInfo');

        Helper::addPanel(1, 'VisitorLoggerPro/panel.php', '访客日志', '查看访客日志', 'administrator');
        Helper::addPanel(2, 'VisitorLoggerPro/trend.php', '趋势分析', '访客趋势分析', 'administrator');

        return '插件已激活，访客日志功能已启用。';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        $dropData = false;
        try {
            $pluginOpts = Helper::options()->plugin('VisitorLoggerPro');
            if (isset($pluginOpts->dropDataOnDeactivate) && $pluginOpts->dropDataOnDeactivate == '1') {
                $dropData = true;
            }
        } catch (Exception $e) {
            // 读取配置失败时默认保留数据
        }

        if ($dropData) {
            try {
                $db = Typecho_Db::get();
                $prefix = $db->getPrefix();
                $db->query("DROP TABLE IF EXISTS `{$prefix}visitor_log`");
            } catch (Exception $e) {
                // 删表失败不影响面板与 Action 卸载
            }
        }

        Helper::removePanel(1, 'VisitorLoggerPro/panel.php');
        Helper::removePanel(2, 'VisitorLoggerPro/trend.php');
        Helper::removeAction('visitor-stats-api');

        return $dropData
            ? '插件已禁用，访客日志数据表已删除。'
            : '插件已禁用，访客日志功能已停用（数据已保留）。';
    }

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        /* botlist设置 */
        $bots = array(
            'baidu=>百度',
            'google=>谷歌',
            'sogou=>搜狗',
            'youdao=>有道',
            'soso=>搜搜',
            'bing=>必应',
            'yahoo=>雅虎',
            '360=>360搜索'
        );

        $botList = new Typecho_Widget_Helper_Form_Element_Textarea('botList', null, implode("\n", $bots), _t('蜘蛛记录设置'), _t('请按照格式填入蜘蛛信息，英文关键字不能超过16个字符'));

        $form->addInput($botList);

        /* 忽略IP设置 */
        $ignoreIPs = new Typecho_Widget_Helper_Form_Element_Textarea(
            'ignoreIPs',
            null,
            '',
            _t('忽略的IP地址'),
            _t('请输入不需要记录的IP地址，每行一个IP地址。支持以下格式：<br>' .
                '1. 精确匹配：192.168.1.1<br>' .
                '2. 通配符匹配：192.168.*.*（使用星号作为通配符）<br>' .
                '3. CIDR格式：192.168.1.0/24（指定网段）<br>' .
                '支持IPv4和IPv6格式。')
        );
        $form->addInput($ignoreIPs);

        /* IPV4数据库选择 */
        $ipv4db = new Typecho_Widget_Helper_Form_Element_Radio(
            'ipv4db',
            array('ip2region' => _t('ip2region数据库'), 'cz88' => _t('纯真数据库')),
            'cz88',
            'IPV4数据库选项',
            _t('<strong>纯真数据库(cz88):</strong> 更新勤快，数据详尽，但可能包含一些非标准信息（如"网吧"），插件已做过滤处理。<br><strong>ip2region数据库:</strong> 查询速度快，格式标准统一，准确性高。推荐使用。')
        );
        $form->addInput($ipv4db);

        /* 启用访客统计 */
        $enableStats = new Typecho_Widget_Helper_Form_Element_Radio(
            'enableStats',
            array(
                '1' => _t('启用'),
                '0' => _t('禁用')
            ),
            '1',
            _t('启用访客统计'),
            _t('是否启用访客统计功能')
        );
        $form->addInput($enableStats);

        /* 禁用插件时删除数据表（开关，默认关闭） */
        $dropDataOnDeactivate = new Typecho_Widget_Helper_Form_Element_Radio(
            'dropDataOnDeactivate',
            array(
                '0' => _t('关闭'),
                '1' => _t('开启')
            ),
            '0',
            _t('禁用时删除数据表'),
            _t('开关默认关闭：禁用插件时保留 visitor_log 及历史记录。开启后，禁用插件时将删除该数据表及全部访问记录，不可恢复。')
        );
        $form->addInput($dropDataOnDeactivate);
    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}


    /**
     * 获取蜘蛛列表
     *
     * @return array
     */
    public static function getBotsList()
    {
        $bots = array();
        $_bots = explode("|", str_replace(array("\r\n", "\r", "\n"), "|", Helper::options()->plugin('VisitorLoggerPro')->botList));
        foreach ($_bots as $_bot) {
            $_bot = explode("=>", $_bot);
            $bots[strval($_bot[0])] = $_bot[1];
        }
        return $bots;
    }


    /**
     * 蜘蛛记录函数
     *
     * @param mixed $rule
     * @return boolean
     */
    public static function isBot()
    {
        $botList = self::getBotsList();
        $bot = NULL;
        if (count($botList) > 0) {
            $request = Typecho_Request::getInstance();
            $useragent = strtolower($request->getAgent());
            foreach ($botList as $key => $value) {
                if (strpos($useragent, strval($key)) !== false) {
                    $bot = $key;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 检查IP是否在忽略列表中
     *
     * @param string $ip 要检查的IP地址
     * @return boolean 如果在忽略列表中返回true，否则返回false
     */
    public static function isIgnoredIP($ip)
    {
        $options = Helper::options();
        if (!isset($options->plugin('VisitorLoggerPro')->ignoreIPs)) {
            return false;
        }

        $ignoreIPs = explode("\n", str_replace(array("\r\n", "\r"), "\n", $options->plugin('VisitorLoggerPro')->ignoreIPs));
        foreach ($ignoreIPs as $ignoreIP) {
            $ignoreIP = trim($ignoreIP);
            if (empty($ignoreIP)) {
                continue;
            }

            // 精确匹配
            if ($ignoreIP === $ip) {
                return true;
            }

            // 支持通配符 * 匹配，例如 192.168.*.*
            if (strpos($ignoreIP, '*') !== false) {
                $pattern = '/^' . str_replace(['*', '.'], ['[0-9]+', '\.'], $ignoreIP) . '$/';
                if (preg_match($pattern, $ip)) {
                    return true;
                }
            }

            // 支持CIDR格式，例如 192.168.1.0/24
            if (strpos($ignoreIP, '/') !== false) {
                list($subnet, $mask) = explode('/', $ignoreIP);
                if (self::ipInCIDR($ip, $subnet, $mask)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查IP是否在CIDR范围内
     *
     * @param string $ip 要检查的IP地址
     * @param string $subnet 子网地址
     * @param int $mask 子网掩码
     * @return boolean 如果在范围内返回true，否则返回false
     */
    private static function ipInCIDR($ip, $subnet, $mask)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // 将IP地址转换为长整型
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);

        if ($ip_long === false || $subnet_long === false) {
            return false;
        }

        // 计算网络掩码
        $mask_bits = pow(2, 32) - pow(2, (32 - intval($mask)));
        $mask_long = $mask_bits & 0xFFFFFFFF;

        // 判断是否在网络范围内
        return (($ip_long & $mask_long) == ($subnet_long & $mask_long));
    }

    public static function logVisitorInfo($header = null, $archive = null)
    {
        if (self::isBot()) {
            return;
        }
        $route = explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0];
        if (strpos($route, "admin") !== false) {
            return;
        }
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $ip_string = self::getIpAddress();
        if (strpos($ip_string, ',') !== false) {
            $ip_string = str_replace(' ', '', $ip_string);
            $parts = explode(',', $ip_string);
            $ip = $parts[0];
        } else {
            $ip = $ip_string;
        }

        // 检查IP是否在忽略列表中
        if (self::isIgnoredIP($ip)) {
            return;
        }

        $location = self::getIpLocation($ip);

        $db->query($db->insert('table.visitor_log')->rows(array(
            'ip' => $ip,
            'route' => $route,
            'country' => $location['country'] ?? 'Unknown',
            'region' => $location['region'] ?? 'Unknown',
            'city' => $location['city'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'time' => date('Y-m-d H:i:s')
        )));
    }

    public static function getVisitorLogs($page = 1, $pageSize = 10)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $offset = ($page - 1) * $pageSize;

        $select = $db->select()->from($prefix . 'visitor_log')
            ->order('time', Typecho_Db::SORT_DESC)
            ->offset($offset)
            ->limit($pageSize);

        return $db->fetchAll($select);
    }

    public static function getSearchVisitorLogs($page = 1, $pageSize = 10, $ip = '')
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $offset = ($page - 1) * $pageSize;

        $select = $db->select()->from($prefix . 'visitor_log')
            ->order('time', Typecho_Db::SORT_DESC)
            ->offset($offset)
            ->limit($pageSize);

        if (!empty($ip)) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $select->where('ip = ?', $ip);
            } else {
                $select->where('ip LIKE ?', '%' . $ip . '%');
            }
        }

        return $db->fetchAll($select);
    }


    private static function getIpAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    private static function getIpLocation($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (Helper::options()->plugin('VisitorLoggerPro')->ipv4db === "cz88") {
                $ipaddr = IpLocation::getLocation($ip)['area'];
                $location = array();
                $location['country'] = $ipaddr ?? 'Unknown';
            } else {
                $xdb = __DIR__ . DIRECTORY_SEPARATOR . 'ip2region/src/ip2region.xdb';
                $region = XdbSearcher::newWithFileOnly($xdb)->search($ip);
                $region = str_replace("0", "", $region);

                $subStrings = explode(' ', $region);

                $repeatedSubstring = explode('|', $region)[3];
                $newString = '';

                foreach ($subStrings as $subString) {
                    if (strpos($newString, $subString) !== false) {
                        $repeatedSubstring = $subString;
                        break;
                    }
                    $newString .= $subString . ' ';
                }

                if ($repeatedSubstring) {
                    $newString = str_replace($repeatedSubstring, '', $newString);
                    $ipaddr = str_replace("|", "", $newString);
                } else {
                    $ipaddr = str_replace("|", "", $region);
                }
                $location['country'] = $ipaddr ?? 'Unknown';
            }
        } else {
            $ipaddr = self::ipquery($ip);
            $location['country'] = $ipaddr;
        }
        return $location;
    }

    private static function ipquery($ip)
    {
        $db6 = new vlp\Ip\ipdbv6(__DIR__ . DIRECTORY_SEPARATOR . 'ipdata/src/zxipv6wry.db');
        $code = 0;
        try {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $result = $db6->query($ip);
            }
        } catch (Exception $e) {
            $result = array("disp" => $e->getMessage());
            $code = -400;
        }
        $o1 = $result["addr"][0];
        $o2 = $result["addr"][1];
        $o1 = str_replace("\"", "\\\"", $o1);
        $o2 = str_replace("\"", "\\\"", $o2);
        $local = str_replace(["无线基站网络", "公众宽带", "3GNET网络", "CMNET网络", "CTNET网络", "\t"], "", $o1);
        $locals = str_replace(["无线基站网络", "公众宽带", "3GNET网络", "CMNET网络", "CTNET网络", "中国", "\t"], "", $o2);
        return $local . $locals;
    }



    public static function cleanUpOldRecords($records)
    {
        $db = Typecho_Db::get();

        try {
            // 先获取总记录数，用于显示
            $totalRecords = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from('table.visitor_log'))->num;

            if ($records <= 0) {
                // 如果输入为0或负数，则不执行删除操作
                return "请输入有效的清理条数（大于0）";
            }

            // 如果要删除的记录数大于等于总记录数，则清空表
            if ($records >= $totalRecords) {
                $db->query($db->delete('table.visitor_log'));
                return "已清空所有访问记录（原有 {$totalRecords} 条）";
            } else {
                // 只保留最新的 (总记录数-要删除的记录数) 条记录
                $keepRecords = $totalRecords - $records;

                // 获取要保留的记录的最早ID
                $minIdToKeep = $db->fetchObject(
                    $db->select('id')->from('table.visitor_log')
                        ->order('time', Typecho_Db::SORT_DESC)
                        ->offset($keepRecords - 1)
                        ->limit(1)
                )->id;

                // 删除ID小于最早ID的记录
                $deleteResult = $db->query($db->delete('table.visitor_log')->where('id < ?', $minIdToKeep));
                $deletedCount = $totalRecords - $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from('table.visitor_log'))->num;

                return "已清理 {$deletedCount} 条最早的访问记录（原有 {$totalRecords} 条，现有 " . ($totalRecords - $deletedCount) . " 条）";
            }
        } catch (Exception $e) {
            error_log("Error deleting records from visitor_log: " . $e->getMessage());
            return "清理记录失败: " . $e->getMessage();
        }
    }

    /**
     * 根据天数清理历史记录
     * 
     * @param int $days 要清理的天数，从最早的记录开始删除指定天数的记录
     * @return string 清理结果描述
     */
    public static function cleanUpRecordsByDays($days)
    {
        if ($days <= 0) {
            return "请输入有效的天数（大于0）";
        }

        $db = Typecho_Db::get();

        try {
            // 先获取总记录数，用于显示
            $totalRecords = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from('table.visitor_log'))->num;

            if ($totalRecords == 0) {
                return "数据库中没有记录可清理";
            }

            // 获取最早的记录日期
            $earliestRecord = $db->fetchRow(
                $db->select('time')
                    ->from('table.visitor_log')
                    ->order('time', Typecho_Db::SORT_ASC)
                    ->limit(1)
            );

            $earliestDate = strtotime($earliestRecord['time']);
            $endDeleteDate = strtotime("+{$days} days", $earliestDate);
            $endDateFormatted = date('Y-m-d H:i:s', $endDeleteDate);

            // 删除从最早记录到指定天数内的记录
            $deleteResult = $db->query($db->delete('table.visitor_log')->where('time < ?', $endDateFormatted));
            $currentRecords = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from('table.visitor_log'))->num;
            $deletedCount = $totalRecords - $currentRecords;

            if ($deletedCount > 0) {
                return "已删除最早的 {$days} 天数据（从 " . $earliestRecord['time'] . " 到 " . $endDateFormatted . "），共 {$deletedCount} 条记录（原有 {$totalRecords} 条，现有 {$currentRecords} 条）";
            } else {
                return "没有找到从 " . $earliestRecord['time'] . " 开始的 {$days} 天内的记录需要清理";
            }
        } catch (Exception $e) {
            error_log("Error deleting old records from visitor_log: " . $e->getMessage());
            return "清理记录失败: " . $e->getMessage();
        }
    }

    /**
     * 处理自定义模板
     *
     * Typecho 1.2 / 1.3 的 handle 钩子签名均为：
     *   handle(string $type, Widget_Archive $archive, $select)
     * 此处做参数兼容，避免把 $type 误当成 $archive。
     *
     * @access public
     * @param mixed $typeOrArchive
     * @param mixed $archive
     * @param mixed $select
     * @return void
     */
    public static function handleTemplate($typeOrArchive, $archive = null, $select = null)
    {
        if (!is_object($archive) || !method_exists($archive, 'is')) {
            // 兼容异常调用：仅传入 archive 对象
            if (is_object($typeOrArchive) && method_exists($typeOrArchive, 'is')) {
                $archive = $typeOrArchive;
            } else {
                return;
            }
        }

        if ($archive->is('page')) {
            $template = $archive->template;
            if ($template == 'visitor-stats.php' || $template == 'page-visitor-stats.php') {
                $archive->setThemeFile('visitor-stats.php');
            }
        }
    }
}
