<?php
/**
 * 前端埋点采集接口（Umami / Matomo 风格第一方 Cookie 统计）
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
    header('Access-Control-Allow-Headers: Content-Type');
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

    $visitorId = isset($data['visitor_id']) ? trim((string)$data['visitor_id']) : '';
    $sessionId = isset($data['session_id']) ? trim((string)$data['session_id']) : '';
    $route = isset($data['route']) ? (string)$data['route'] : '/';
    $referrer = isset($data['referrer']) ? (string)$data['referrer'] : '';

    if (!preg_match('/^[a-f0-9-]{8,64}$/i', $visitorId) || !preg_match('/^[a-f0-9-]{8,64}$/i', $sessionId)) {
        throw new Exception('invalid_ids');
    }

    $route = explode('?', $route)[0];
    $route = substr($route, 0, 255);
    if ($route === '') {
        $route = '/';
    }
    if (stripos($route, 'admin') !== false) {
        ob_end_clean();
        echo json_encode(['ok' => true, 'skipped' => 'admin']);
        exit;
    }

    $referrer = substr($referrer, 0, 512);

    $result = VisitorLoggerPro_Plugin::recordVisit(array(
        'route' => $route,
        'visitor_id' => $visitorId,
        'session_id' => $sessionId,
        'referrer' => $referrer,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'source' => 'client'
    ));

    ob_end_clean();
    echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
