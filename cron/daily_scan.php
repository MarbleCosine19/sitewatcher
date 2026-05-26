<?php
define('RUNNING_AS_CRON', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/scanner.php';


if (php_sapi_name() !== 'cli') {
    $expectedKey = md5(getSetting('email_to'));
    if (($_GET['key'] ?? '') !== $expectedKey) {
        http_response_code(403);
        echo 'Forbidden — missing or wrong key.';
        exit;
    }
}


if (getSetting('cron_enabled', '1') !== '1') {
    echo date('[Y-m-d H:i:s]') . "cron disabled in settings. Exiting.\n";
    exit;
}

$today = date('Y-m-d');
$existing = db()->prepare('SELECT id FROM scan_logs WHERE scan_date = ?');
$existing->execute([$today]);
if ($existing->fetch()) {
    echo date('[Y-m-d H:i:s]') . "already ran today ({$today}). Exiting.\n";
    exit;
}

echo date('[Y-m-d H:i:s]') . "starting daily scan...\n";
$start = microtime(true);

$scanLogId = runFullScan();

$elapsed = round(microtime(true) - $start, 1);
echo date('[Y-m-d H:i:s]') . "scan complete in {$elapsed}s. Scan log ID: {$scanLogId}\n";


