<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 前后台独立页面显示访客日志插件，记录来访者的信息，统计来访者情况，本插件基于VisitorLogger进行开发，现已排除蜘蛛IP，且支持IPV6
 *
 * @package VisitorLoggerPro
 * @author xuan
 * @version 2.0.0   
 * @link https://blog.ybyq.wang/
 */

// 加载兼容适配器
require_once dirname(__FILE__) . '/adapter.php';

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

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}visitor_log` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL,
            `route` VARCHAR(255) NOT NULL,
            `country` VARCHAR(100),
            `region` VARCHAR(100),
            `city` VARCHAR(100),
            `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        // ********如果提示UNSIGNED 或 AUTO_INCREMENT 或 ENGINE的相关错误，将上述代码替换成以下代码********
        //$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}visitor_log` (
        //    `id` INT(10) PRIMARY KEY,
        //    `ip` VARCHAR(45) NOT NULL,
        //    `route` VARCHAR(255) NOT NULL,
        //    `country` VARCHAR(100),
        //    `region` VARCHAR(100),
        //    `city` VARCHAR(100),
        //   `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        //);";

        try {
            $db->query($sql);
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception('创建访客日志表或IP地址记录表失败: ' . $e->getMessage());
        }

        // 注册访客统计API
        Helper::addAction('visitor-stats-api', 'VisitorLogger_Action');

        // 注册统计模板和钩子
        Typecho_Plugin::factory('Widget_Archive')->handle = array('VisitorLoggerPro_Plugin', 'handleTemplate');
        Typecho_Plugin::factory('Widget_Archive')->header = array('VisitorLoggerPro_Plugin', 'logVisitorInfo');

        Helper::addPanel(1, 'VisitorLoggerPro/panel.php', '访客日志', '查看访客日志', 'administrator');

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
        Helper::removePanel(1, 'VisitorLoggerPro/panel.php');
        Helper::removeAction('visitor-stats-api');
        return '插件已禁用，访客日志功能已停用。';
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

        /* IPV4数据库选择 */
        $ipv4db = new Typecho_Widget_Helper_Form_Element_Radio(
            'ipv4db',
            array('ip2region' => _t('ip2region数据库'), 'cz88' => _t('纯真数据库')),
            'cz88',
            'IPV4数据库选项',
            '介绍：此项是选择IPV4类型的数据库！本插件基于XQLocation进行开发'
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

    public static function logVisitorInfo()
    {
        if (self::isBot()) {
            return;
        }
        $route = explode('?', $_SERVER['REQUEST_URI'])[0];
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

        $location = self::getIpLocation($ip);

        $db->query($db->insert('table.visitor_log')->rows(array(
            'ip' => $ip,
            'route' => $route,
            'country' => $location['country'] ?? 'Unknown',
            'region' => $location['region'] ?? 'Unknown',
            'city' => $location['city'] ?? 'Unknown'
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
            $select->where('ip LIKE ?', '%' . $ip . '%');
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
     * 处理自定义模板
     * 
     * @access public
     * @param Widget_Archive $archive
     * @return void
     */
    public static function handleTemplate($archive)
    {
        if ($archive->is('page')) {
            $template = $archive->template;
            if ($template == 'visitor-stats.php' || $template == 'page-visitor-stats.php') {
                $archive->setThemeFile('visitor-stats.php');
            }
        }
    }
}
