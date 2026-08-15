<?php
/**
 * 模拟 Typecho 自动加载副作用：加载命名空间类时顺带创建旧式别名，
 * 验证 vlp_safe_class_alias 不会产生 "already in use" 警告。
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('__TYPECHO_ROOT_DIR__', sys_get_temp_dir());

// 模拟 Typecho 自动加载：存在命名空间类时同步注册旧式别名
spl_autoload_register(function ($class) {
    static $map = [
        'Typecho\\Plugin\\Exception' => 'Typecho_Plugin_Exception',
        'Typecho\\Widget\\Helper\\Form\\Element\\Textarea' => 'Typecho_Widget_Helper_Form_Element_Textarea',
        'Typecho\\Widget\\Helper\\Form\\Element\\Radio' => 'Typecho_Widget_Helper_Form_Element_Radio',
        'Typecho\\Widget\\Exception' => 'Typecho_Widget_Exception',
        'Widget\\Archive' => 'Widget_Archive',
        'Widget\\ActionInterface' => 'Widget_Interface_Do',
    ];

    $normalized = ltrim($class, '\\');
    if (!isset($map[$normalized])) {
        return;
    }

    if ($normalized === 'Widget\\ActionInterface') {
        if (!interface_exists($normalized, false)) {
            eval('namespace Widget { interface ActionInterface {} }');
        }
    } else {
        if (!class_exists($normalized, false)) {
            $parts = explode('\\', $normalized);
            $short = array_pop($parts);
            $ns = implode('\\', $parts);
            eval($ns !== '' ? "namespace {$ns} { class {$short} {} }" : "class {$short} {}");
        }
    }

    $alias = $map[$normalized];
    if (!class_exists($alias, false) && !interface_exists($alias, false)) {
        class_alias('\\' . $normalized, $alias);
    }
});

$warnings = [];
set_error_handler(function ($errno, $errstr) use (&$warnings) {
    if ($errno === E_WARNING || $errno === E_USER_WARNING) {
        $warnings[] = $errstr;
    }
    return true;
});

require dirname(__DIR__) . '/adapter.php';

restore_error_handler();

$aliasWarnings = array_filter($warnings, function ($msg) {
    return stripos($msg, 'already in use') !== false
        || stripos($msg, 'Cannot declare') !== false;
});

if (!empty($aliasWarnings)) {
    fwrite(STDERR, "FAIL: unexpected alias warnings:\n- " . implode("\n- ", $aliasWarnings) . "\n");
    exit(1);
}

$required = [
    'Typecho_Plugin_Exception',
    'Typecho_Widget_Helper_Form_Element_Textarea',
    'Typecho_Widget_Helper_Form_Element_Radio',
    'Typecho_Widget_Exception',
    'Widget_Archive',
];

foreach ($required as $name) {
    if (!class_exists($name, false) && !interface_exists($name, false)) {
        fwrite(STDERR, "FAIL: expected alias missing: {$name}\n");
        exit(1);
    }
}

if (!interface_exists('Widget_Interface_Do', false)) {
    fwrite(STDERR, "FAIL: expected interface alias missing: Widget_Interface_Do\n");
    exit(1);
}

echo "OK: adapter alias safety checks passed\n";
exit(0);
