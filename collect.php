<?php
/**
 * 前端埋点采集接口
 * 对照 Umami src/app/api/send/route.ts：服务端计算 session/visit，前端只传页面元数据
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-VLP-Cache');
    ob_end_clean();
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__, 4));
}

if (!class_exists('Typecho_Db') && !class_exists('\\Typecho\\Db')) {
    require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

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
require_once dirname(__FILE__) . '/UmamiIdentity.php';
require_once dirname(__FILE__) . '/BotFilter.php';
require_once dirname(__FILE__) . '/Plugin.php';

try {
    $pluginOpts = Helper::options()->plugin('VisitorLoggerPro');
    if (isset($pluginOpts->enableStats) && (string)$pluginOpts->enableStats === '0') {
        ob_end_clean();
        echo json_encode(['ok' => true, 'skipped' => 'disabled']);
        exit;
    }

    $mode = isset($pluginOpts->trackingMode) ? (string)$pluginOpts->trackingMode : 'server';
    if ($mode !== 'client') {
        ob_end_clean();
        echo json_encode(['ok' => true, 'skipped' => 'not_client_mode']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        throw new Exception('invalid_json');
    }

    // 兼容 {type,payload}（Umami）与扁平字段
    $payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : $data;

    $route = isset($payload['route']) ? (string)$payload['route'] : '';
    if ($route === '' && !empty($payload['url'])) {
        $parts = parse_url((string)$payload['url']);
        $route = isset($parts['path']) ? $parts['path'] : '/';
    }
    if ($route === '') {
        $route = '/';
    }
    $route = explode('?', $route)[0];
    $route = substr($route, 0, 255);
    if (stripos($route, 'admin') !== false) {
        ob_end_clean();
        echo json_encode(['ok' => true, 'skipped' => 'admin']);
        exit;
    }

    $referrer = isset($payload['referrer']) ? substr((string)$payload['referrer'], 0, 512) : '';
    $cacheHeader = $_SERVER['HTTP_X_VLP_CACHE'] ?? '';

    $result = VisitorLoggerPro_Plugin::recordVisit(array(
        'route' => $route,
        'referrer' => $referrer,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'cache_token' => $cacheHeader,
        'source' => 'client',
        'assign_umami_ids' => true
    ));

    $ids = is_array($result) ? $result : array('result' => $result);
    ob_end_clean();
    echo json_encode(array_merge(['ok' => true], $ids), JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
