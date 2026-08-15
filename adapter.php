<?php

/**
 * Typecho兼容适配器
 * 用于支持新版Typecho（1.2+/1.3 命名空间版本）运行旧版插件写法
 */

// 确保这个文件只在 Typecho 环境中被执行
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

// 处理已经加载的IP类（目标别名已存在则跳过）
if (class_exists('itbdw\\Ip\\IpLocation') && !class_exists('vlp\\Ip\\IpLocation', false)) {
    class_alias('itbdw\\Ip\\IpLocation', 'vlp\\Ip\\IpLocation');
}

if (class_exists('ipdbv6') && !class_exists('vlp\\Ip\\ipdbv6', false)) {
    class_alias('ipdbv6', 'vlp\\Ip\\ipdbv6');
}

if (class_exists('ip2region\\XdbSearcher') && !class_exists('vlp\\ip2region\\XdbSearcher', false)) {
    class_alias('ip2region\\XdbSearcher', 'vlp\\ip2region\\XdbSearcher');
}

if (!function_exists('vlp_symbol_loaded')) {
    /**
     * 判断类/接口/Trait 是否已加载（不触发自动加载）
     *
     * @param string $name
     * @return bool
     */
    function vlp_symbol_loaded($name)
    {
        return class_exists($name, false)
            || interface_exists($name, false)
            || trait_exists($name, false);
    }
}

if (!function_exists('vlp_safe_class_alias')) {
    /**
     * 安全创建类/接口别名（目标已存在则跳过）
     *
     * Typecho 1.2+/1.3 在自动加载命名空间类时会顺带注册旧式别名
     *（如 Typecho_Plugin_Exception）。若先 class_exists($from) 再 class_alias，
     * 目标名可能已在自动加载副作用中声明，需再次检查后再别名。
     *
     * @param string $from 真实类名
     * @param string $to   旧式别名
     */
    function vlp_safe_class_alias($from, $to)
    {
        if (vlp_symbol_loaded($to)) {
            return;
        }

        $fromExists = vlp_symbol_loaded($from)
            || class_exists($from)
            || interface_exists($from);

        if (!$fromExists) {
            return;
        }

        // 自动加载可能已由 Typecho 创建旧式别名，避免重复声明警告
        if (vlp_symbol_loaded($to)) {
            return;
        }

        class_alias($from, $to);
    }
}

// Typecho 1.3: PluginInterface；部分中间版本曾用 Interface
if (!interface_exists('Typecho_Plugin_Interface', false)) {
    if (interface_exists('\\Typecho\\Plugin\\PluginInterface', false) || interface_exists('\\Typecho\\Plugin\\PluginInterface')) {
        vlp_safe_class_alias('\\Typecho\\Plugin\\PluginInterface', 'Typecho_Plugin_Interface');
    } elseif (interface_exists('\\Typecho\\Plugin\\Interface', false) || interface_exists('\\Typecho\\Plugin\\Interface')) {
        vlp_safe_class_alias('\\Typecho\\Plugin\\Interface', 'Typecho_Plugin_Interface');
    }
}

vlp_safe_class_alias('\\Typecho\\Db', 'Typecho_Db');
vlp_safe_class_alias('\\Typecho\\Plugin\\Exception', 'Typecho_Plugin_Exception');
vlp_safe_class_alias('\\Typecho\\Plugin', 'Typecho_Plugin');
vlp_safe_class_alias('\\Typecho\\Widget\\Helper\\Form', 'Typecho_Widget_Helper_Form');
vlp_safe_class_alias('\\Typecho\\Widget\\Helper\\Form\\Element\\Textarea', 'Typecho_Widget_Helper_Form_Element_Textarea');
vlp_safe_class_alias('\\Typecho\\Widget\\Helper\\Form\\Element\\Radio', 'Typecho_Widget_Helper_Form_Element_Radio');
vlp_safe_class_alias('\\Typecho\\Request', 'Typecho_Request');
vlp_safe_class_alias('\\Typecho\\Common', 'Typecho_Common');
vlp_safe_class_alias('\\Typecho\\Widget', 'Typecho_Widget');
vlp_safe_class_alias('\\Typecho\\Widget\\Exception', 'Typecho_Widget_Exception');
vlp_safe_class_alias('\\Widget\\Archive', 'Widget_Archive');
vlp_safe_class_alias('\\Widget\\ActionInterface', 'Widget_Interface_Do');
vlp_safe_class_alias('\\Widget\\Options', 'Widget_Options');
vlp_safe_class_alias('\\Widget\\User', 'Widget_User');

// 处理Helper类（1.3 为 Utils\Helper，Init 中会建别名；独立脚本场景需兜底）
if (!class_exists('Helper', false)) {
    if (class_exists('\\Utils\\Helper')) {
        // Utils\Helper 自动加载后 Helper 别名可能已存在
        if (!class_exists('Helper', false)) {
            class_alias('\\Utils\\Helper', 'Helper');
        }
    } elseif (class_exists('\\Typecho\\Helper')) {
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
