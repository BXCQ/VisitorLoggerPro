<?php
/**
 * UmamiIdentity 单元测试（不依赖 Typecho）
 * 运行：php tests/umami_identity_test.php
 */

require_once dirname(__DIR__) . '/UmamiIdentity.php';

$failed = 0;

function assertTrue($cond, $msg)
{
    global $failed;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $failed++;
    }
}

$website = 'https://example.com/';
$ip = '203.0.113.10';
$ua = 'Mozilla/5.0 TestBrowser';
$t1 = gmmktime(12, 0, 0, 8, 16, 2026);
$t2 = gmmktime(12, 10, 0, 8, 16, 2026); // 同月同时段偏后
$tNextMonth = gmmktime(12, 0, 0, 9, 1, 2026);

$v1 = VisitorLoggerPro_UmamiIdentity::makeVisitorId($website, $ip, $ua, $t1);
$v2 = VisitorLoggerPro_UmamiIdentity::makeVisitorId($website, $ip, $ua, $t2);
$vOtherIp = VisitorLoggerPro_UmamiIdentity::makeVisitorId($website, '203.0.113.11', $ua, $t1);
$vNextMonth = VisitorLoggerPro_UmamiIdentity::makeVisitorId($website, $ip, $ua, $tNextMonth);

assertTrue(preg_match('/^[a-f0-9-]{36}$/', $v1) === 1, 'visitor_id 为 UUID 形态');
assertTrue($v1 === $v2, '同月同 IP+UA → 同一 visitor_id（Umami sessionId）');
assertTrue($v1 !== $vOtherIp, '不同 IP → 不同 visitor_id');
assertTrue($v1 !== $vNextMonth, '跨月盐轮换 → 不同 visitor_id');

$visitA = VisitorLoggerPro_UmamiIdentity::resolveVisit($v1, null, $t1);
$cache = array(
    'visitor_id' => $visitA['visitor_id'],
    'session_id' => $visitA['session_id'],
    'iat' => $visitA['iat']
);

$within = VisitorLoggerPro_UmamiIdentity::resolveVisit($v1, $cache, $t1 + 600); // 10 分钟内
assertTrue($within['session_id'] === $visitA['session_id'], '30 分钟内复用同一 visitId');
assertTrue($within['iat'] === $visitA['iat'], '活动期内保留原 iat（对齐 Umami）');

$expired = VisitorLoggerPro_UmamiIdentity::resolveVisit($v1, $cache, $t1 + 1801);
assertTrue($expired['session_id'] !== $visitA['session_id'], '超时后生成新的 visitId');
assertTrue($expired['iat'] === $t1 + 1801, '超时后 iat 更新为 now');

$token = VisitorLoggerPro_UmamiIdentity::encodeCache($visitA);
$decoded = VisitorLoggerPro_UmamiIdentity::decodeCache($token);
assertTrue(is_array($decoded), 'cache token 可解码');
assertTrue($decoded['visitor_id'] === $visitA['visitor_id'], 'cache.visitor_id 一致');
assertTrue($decoded['session_id'] === $visitA['session_id'], 'cache.session_id 一致');
assertTrue(VisitorLoggerPro_UmamiIdentity::decodeCache('tampered') === null, '篡改 token 拒绝');
assertTrue(VisitorLoggerPro_UmamiIdentity::decodeCache('') === null, '空 token 拒绝');

if ($failed > 0) {
    echo "\n{$failed} test(s) failed\n";
    exit(1);
}

echo "\nAll tests passed\n";
exit(0);
