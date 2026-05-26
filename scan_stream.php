<?php


require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
require_once __DIR__ . '/includes/mailer.php';


while (ob_get_level()) ob_end_clean();
header('Content-Type: application/x-ndjson');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache');

set_time_limit(600); 

function emit(array $data): void {
    echo json_encode($data) . "\n";
    flush();
}

$pdo  = db();
$urls = $pdo->query('SELECT * FROM urls WHERE active=1')->fetchAll();

if (empty($urls)) {
    emit(['type' => 'error', 'msg' => 'No active URLs']);
    exit;
}

$scanDate = date('Y-m-d');


$pdo->prepare('INSERT INTO scan_logs (scan_date, scan_start) VALUES (?,?)')
    ->execute([$scanDate, date('Y-m-d H:i:s')]);
$scanLogId = (int)$pdo->lastInsertId();

emit(['type' => 'start', 'total' => count($urls)]);

$errors = 0; $warnings = 0;

foreach ($urls as $row) {
    $url    = $row['url'];
    $result = checkUrl($url);
    $ss     = takeScreenshot($url, $scanDate);

    if ($result['status'] === 'error')   $errors++;
    if ($result['status'] === 'warning') $warnings++;

    $pdo->prepare('INSERT INTO scan_results
        (scan_log_id, url_id, url, status, error_type, error_detail, redirect_url,
         screenshot_path, http_code, response_time, anomalies_found, checked_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([
            $scanLogId, $row['id'], $url,
            $result['status'], $result['error_type'], $result['error_detail'],
            $result['redirect_url'], $ss,
            $result['http_code'], $result['response_time'],
            $result['anomalies_found'],
        ]);

    emit([
        'type'         => 'result',
        'url'          => $url,
        'status'       => $result['status'],
        'error_type'   => $result['error_type'],
        'error_detail' => $result['error_detail'],
    ]);
}


$pdo->prepare('UPDATE scan_logs SET scan_end=?, total_checked=?, total_errors=?, total_warnings=? WHERE id=?')
    ->execute([date('Y-m-d H:i:s'), count($urls), $errors, $warnings, $scanLogId]);

purgeOldLogs();

$emailSent = sendScanReport($scanLogId);

emit([
    'type'        => 'done',
    'scan_log_id' => $scanLogId,
    'scan_date'   => $scanDate,
    'total'       => count($urls),
    'errors'      => $errors,
    'warnings'    => $warnings,
    'email_sent'  => $emailSent,
]);
