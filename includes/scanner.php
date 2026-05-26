<?php


require_once __DIR__ . '/includes/config.php';


function checkUrl(string $url): array {
    $result = [
        'url'            => $url,
        'status'         => 'ok',
        'error_type'     => null,
        'error_detail'   => null,
        'redirect_url'   => null,
        'http_code'      => null,
        'response_time'  => null,
        'anomalies_found'=> null,
        'screenshot_path'=> null,
    ];


    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,  
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => '',
        CURLOPT_HEADER         => true,
    ]);

    $start    = microtime(true);
    $response = curl_exec($ch);
    $elapsed  = round(microtime(true) - $start, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_errno($ch);
    $errorMsg = curl_error($ch);
    curl_close($ch);

    $result['response_time'] = $elapsed;
    $result['http_code']     = $httpCode;



    if (in_array($httpCode, [301, 302, 303, 307, 308])) {

        preg_match('/^Location:\s*(.+)$/im', $response, $matches);
        $location = isset($matches[1]) ? trim($matches[1]) : '';


        $originalHost = parse_url($url, PHP_URL_HOST);
        $redirectHost = parse_url($location, PHP_URL_HOST);

        if ($redirectHost && $redirectHost !== $originalHost) {
            $result['status']       = 'error';
            $result['error_type']   = 'redirect';
            $result['error_detail'] = "Redirects to external domain: {$redirectHost}";
            $result['redirect_url'] = $location;
        } else {
 
            $result['redirect_url'] = $location;



            $body = fetchBody($url);
            $result = array_merge($result, checkBodyAnomalies($body, $result));
        }
        return $result;
    }


    if ($httpCode >= 500) {
        $result['status']       = 'error';
        $result['error_type']   = 'server_error';
        $result['error_detail'] = "HTTP {$httpCode} Server Error";
        return $result;
    }

    if ($httpCode === 0) {
        $result['status']       = 'error';
        $result['error_type']   = 'unreachable';
        $result['error_detail'] = 'No response received (timeout or DNS failure)';
        return $result;
    }


    $body = fetchBody($url);

    $result = array_merge($result, checkBodyAnomalies($body, $result));

    return $result;
}

function fetchBody(string $url): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 SiteWatcher/1.0',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

function checkBodyAnomalies(string $body, array $result): array {
    $text     = strtolower(strip_tags($body));
    $keywords = ANOMALY_KEYWORDS;
    $found    = [];

    foreach ($keywords as $kw) {
        if (strpos($text, strtolower($kw)) !== false) {
            $found[] = $kw;
        }
    }

    if (!empty($found)) {
        $result['status']         = 'warning';
        $result['error_type']     = 'anomaly';
        $result['error_detail']   = 'Suspicious content detected';
        $result['anomalies_found']= json_encode($found);
    }

    return $result;
}



function takeScreenshot(string $url, string $scanDate): string {
    $safeUrl  = preg_replace('/[^a-zA-Z0-9]/', '_', $url);
    $safeUrl  = substr($safeUrl, 0, 80);
    $filename = $scanDate . '_' . $safeUrl . '.png';
    $dir      = SS_DIR . '/' . $scanDate;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fullPath = $dir . '/' . $filename;


    $wkPath = findExecutable(['wkhtmltoimage', '/usr/local/bin/wkhtmltoimage', '/usr/bin/wkhtmltoimage']);
    if ($wkPath) {
        $cmd = escapeshellcmd($wkPath) . ' --quiet --quality 70 '
             . escapeshellarg($url) . ' ' . escapeshellarg($fullPath) . ' 2>/dev/null';
        exec($cmd);
        if (file_exists($fullPath)) {
            return 'screenshots/' . $scanDate . '/' . $filename;
        }
    }


    if (extension_loaded('gd')) {
        $img = imagecreatetruecolor(800, 100);
        $bg  = imagecolorallocate($img, 240, 240, 245);
        $fg  = imagecolorallocate($img, 60, 60, 80);



        imagefill($img, 0, 0, $bg);
        imagestring($img, 3, 10, 10, 'URL: ' . $url, $fg);
        imagestring($img, 2, 10, 35, 'Screenshot taken: ' . date('Y-m-d H:i:s'), $fg);
        imagestring($img, 2, 10, 55, '(Install wkhtmltoimage for real screenshots)', $fg);
        imagepng($img, $fullPath);


        imagedestroy($img);
        if (file_exists($fullPath)) {
            return 'screenshots/' . $scanDate . '/' . $filename;
        }
    }

    return '';
}

function findExecutable(array $candidates): ?string {
    foreach ($candidates as $c) {
        if (is_executable($c)) return $c;
        $out = shell_exec('which ' . escapeshellarg($c) . ' 2>/dev/null');
        if ($out && is_executable(trim($out))) return trim($out);
    }
    return null;
}


function runFullScan(): int {
    $pdo      = db();
    $urls     = $pdo->query('SELECT * FROM urls WHERE active = 1')->fetchAll();
    $scanDate = date('Y-m-d');




    $pdo->prepare('INSERT INTO scan_logs (scan_date, scan_start) VALUES (?,?)')
        ->execute([$scanDate, date('Y-m-d H:i:s')]);
    $scanLogId = (int)$pdo->lastInsertId();

    $totalErrors   = 0;
    $totalWarnings = 0;

    foreach ($urls as $row) {
        $url    = $row['url'];
        $result = checkUrl($url);
        $ss     = takeScreenshot($url, $scanDate);

        if ($result['status'] === 'error')   $totalErrors++;
        if ($result['status'] === 'warning') $totalWarnings++;

        $pdo->prepare('INSERT INTO scan_results
            (scan_log_id, url_id, url, status, error_type, error_detail, redirect_url,
             screenshot_path, http_code, response_time, anomalies_found, checked_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([
                $scanLogId, $row['id'], $url,


                $result['status'], $result['error_type'], $result['error_detail'],
                $result['http_code'], $result['response_time'],
                $result['redirect_url'], $ss,
                $result['anomalies_found'],
            ]);
    }


    $pdo->prepare('UPDATE scan_logs SET scan_end=?, total_checked=?, total_errors=?, total_warnings=? WHERE id=?')
        ->execute([date('Y-m-d H:i:s'), count($urls), $totalErrors, $totalWarnings, $scanLogId]);


    purgeOldLogs();

    return $scanLogId;
}

function purgeOldLogs(): void {
    $days     = (int)getSetting('log_retention_days', '30');
    $cutoff   = date('Y-m-d', strtotime("-{$days} days"));
    $pdo      = db();




    $old = $pdo->prepare('SELECT scan_date FROM scan_logs WHERE scan_date < ?');
    $old->execute([$cutoff]);
    foreach ($old->fetchAll() as $row) {
        $dir = SS_DIR . '/' . $row['scan_date'];
        if (is_dir($dir)) {
            array_map('unlink', glob("$dir/*"));
            @rmdir($dir);
        }
    }

    $pdo->prepare('DELETE FROM scan_logs WHERE scan_date < ?')->execute([$cutoff]);
}
