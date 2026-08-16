<?php
/**
 * BotFilter 单元测试
 * 运行：php tests/bot_filter_test.php
 */

require_once dirname(__DIR__) . '/BotFilter.php';

$failed = 0;

function assertSame($expected, $actual, $msg)
{
    global $failed;
    if ($expected === $actual) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
        $failed++;
    }
}

$bots = array(
    'Googlebot/2.1 (+http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'curl/8.5.0',
    'Wget/1.21',
    'python-requests/2.31.0',
    'Go-http-client/1.1',
    'Mozilla/5.0 (compatible; AhrefsBot/7.0)',
    'HeadlessChrome/120.0.6099.0',
    'facebookexternalhit/1.1',
    '',
);

$humans = array(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
);

foreach ($bots as $ua) {
    assertSame(true, VisitorLoggerPro_BotFilter::isBotUa($ua), 'bot: ' . ($ua === '' ? '(empty)' : substr($ua, 0, 48)));
}

foreach ($humans as $ua) {
    assertSame(false, VisitorLoggerPro_BotFilter::isBotUa($ua), 'human: ' . substr($ua, 0, 48));
}

if ($failed > 0) {
    echo "\n{$failed} test(s) failed\n";
    exit(1);
}

echo "\nAll tests passed\n";
exit(0);
