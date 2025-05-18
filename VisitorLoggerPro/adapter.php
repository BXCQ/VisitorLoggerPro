<?php

/**
 * Typecho兼容适配器
 * 用于支持新版Typecho (带命名空间版本) 运行旧版插件
 */

// 确保这个文件只在 Typecho 环境中被执行
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

// 处理已经加载的IP类
if (class_exists('itbdw\\Ip\\IpLocation')) {
    // 已经存在这个类，创建别名到我们的命名空间
    class_alias('itbdw\\Ip\\IpLocation', 'vlp\\Ip\\IpLocation');
}

// 如果 ipdbv6 类已存在，也创建别名
if (class_exists('ipdbv6')) {
    class_alias('ipdbv6', 'vlp\\Ip\\ipdbv6');
}

// 如果 XdbSearcher 类已存在，也创建别名
if (class_exists('ip2region\\XdbSearcher')) {
    class_alias('ip2region\\XdbSearcher', 'vlp\\ip2region\\XdbSearcher');
}

// 只有在没有定义这些类的情况下才创建别名
if (!class_exists('Typecho_Plugin_Interface') && class_exists('\\Typecho\\Plugin\\Interface')) {
    class_alias('\\Typecho\\Plugin\\Interface', 'Typecho_Plugin_Interface');
}

if (!class_exists('Typecho_Db') && class_exists('\\Typecho\\Db')) {
    class_alias('\\Typecho\\Db', 'Typecho_Db');
}

if (!class_exists('Typecho_Plugin_Exception') && class_exists('\\Typecho\\Plugin\\Exception')) {
    class_alias('\\Typecho\\Plugin\\Exception', 'Typecho_Plugin_Exception');
}

if (!class_exists('Typecho_Plugin') && class_exists('\\Typecho\\Plugin')) {
    class_alias('\\Typecho\\Plugin', 'Typecho_Plugin');
}

if (!class_exists('Typecho_Widget_Helper_Form') && class_exists('\\Typecho\\Widget\\Helper\\Form')) {
    class_alias('\\Typecho\\Widget\\Helper\\Form', 'Typecho_Widget_Helper_Form');
}

if (!class_exists('Typecho_Widget_Helper_Form_Element_Textarea') && class_exists('\\Typecho\\Widget\\Helper\\Form\\Element\\Textarea')) {
    class_alias('\\Typecho\\Widget\\Helper\\Form\\Element\\Textarea', 'Typecho_Widget_Helper_Form_Element_Textarea');
}

if (!class_exists('Typecho_Widget_Helper_Form_Element_Radio') && class_exists('\\Typecho\\Widget\\Helper\\Form\\Element\\Radio')) {
    class_alias('\\Typecho\\Widget\\Helper\\Form\\Element\\Radio', 'Typecho_Widget_Helper_Form_Element_Radio');
}

if (!class_exists('Typecho_Request') && class_exists('\\Typecho\\Request')) {
    class_alias('\\Typecho\\Request', 'Typecho_Request');
}

if (!class_exists('Widget_Archive') && class_exists('\\Typecho\\Widget\\Archive')) {
    class_alias('\\Typecho\\Widget\\Archive', 'Widget_Archive');
}

// 处理Helper类
if (!class_exists('Helper') && class_exists('\\Typecho\\Helper')) {
    class Helper
    {
        public static function options()
        {
            $class = '\\Typecho\\Helper';
            return $class::options();
        }

        public static function addAction($action, $className)
        {
            $class = '\\Typecho\\Helper';
            return $class::addAction($action, $className);
        }

        public static function removeAction($action)
        {
            $class = '\\Typecho\\Helper';
            return $class::removeAction($action);
        }

        public static function addPanel($group, $fileName, $title, $description, $permission = null)
        {
            $class = '\\Typecho\\Helper';
            return $class::addPanel($group, $fileName, $title, $description, $permission);
        }

        public static function removePanel($group, $fileName)
        {
            $class = '\\Typecho\\Helper';
            return $class::removePanel($group, $fileName);
        }
    }
}

// 处理 _t 函数
if (!function_exists('_t') && function_exists('__')) {
    function _t($string)
    {
        return call_user_func('__', $string);
    }
}

// 如果 __ 函数不存在，创建一个简单的实现
if (!function_exists('__')) {
    function __($string)
    {
        return $string;
    }
}

// 修正plugin路径指向
if (!class_exists('VisitorLogger_Action') && class_exists('VisitorLoggerPro_Action')) {
    class_alias('VisitorLoggerPro_Action', 'VisitorLogger_Action');
}

// 修正plugin名称
if (!class_exists('VisitorLoggerPro_Plugin') && class_exists('VisitorLogger_Plugin')) {
    class_alias('VisitorLogger_Plugin', 'VisitorLoggerPro_Plugin');
} else if (!class_exists('VisitorLogger_Plugin') && class_exists('VisitorLoggerPro_Plugin')) {
    class_alias('VisitorLoggerPro_Plugin', 'VisitorLogger_Plugin');
}
