<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/scanner.php';
require_once __DIR__ . '/includes/mailer.php';


$urlCount = db()->query('SELECT COUNT(*) as n FROM urls WHERE active=1')->fetch()['n'];
?>


---------------------------------------------------


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SiteWatcher — Running Scan</title>
<link rel="stylesheet" href="style.css">


<style>
  .progress-wrap{background:#f5f5f5;border-radius:6px;height:20px;overflow:hidden;margin:12px 0;}
  .progress-bar{height:100%;background:#1a1a2e;width:0%;transition:width .3s;}
  .log-line{font-family:monospace;font-size:13px;padding:4px 0;border-bottom:1px solid #f0f0f0;}
  .log-line.error{color:#c0392b;}
  .log-line.warning{color:#e67e22;}
  .log-line.ok{color:#27ae60;}
</style>

</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>


<main>
<div class="page-header"><h1>Running Scan</h1></div>

<?php if ($urlCount === 0): ?>
<div class="alert errr">No active URLs to scan. <a href="admin.php">Add URLs first</a>.</div>
<?php else: ?>

<div class="card">
  <p id="statusMsg">Scanning <strong><?= $urlCount ?></strong> sites...</p>
  <div class="progress-wrap"><div class="progress-bar" id="bar"></div></div>
  <div id="logBox" style="max-height:400px;overflow-y:auto;"></div>
</div>

<div class="card" id="summaryCard" style="display:none;">
  <h2>Scan Complete</h2>
  <div id="summaryContent"></div>
  <div style="margin-top:16px;">
    <a href="index.php" class="btn">← Dashboard</a>
    <a id="logLink" href="#" class="btn btn-primary" style="margin-left:8px;">View Full Log</a>
  </div>
</div>

<script>
(async () => {
  const logBox = document.getElementById('logBox');
  const bar    = document.getElementById('bar');
  const msg    = document.getElementById('statusMsg');

  function addLine(text, cls='') {
    const d = document.createElement('div');
    d.className = 'log-line ' + cls;
    d.textContent = text;
    logBox.appendChild(d);
    logBox.scrollTop = logBox.scrollHeight;
  }


  const resp = await fetch('scan_stream.php');
  const reader = resp.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let total = 0, done = 0, errors = 0, warnings = 0, scanLogId = null;

  while (true) {
    const {value, done: streamDone} = await reader.read();
    if (streamDone) break;
    buffer += decoder.decode(value, {stream:true});
    let lines = buffer.split('\n');
    buffer = lines.pop();



    for (const line of lines) {
        if (!line.trim()) continue;

      try {
        const obj = JSON.parse(line); if (obj.type === 'start') {
          total = obj.total;
          addLine('Starting scan of ' + total + ' URLs...');


        } else if (obj.type === 'result') {
          done++;
          const pct = Math.round(done/total*100);
          bar.style.width = pct + '%';
          const icon = obj.status === 'error' ? 'X' : obj.status === 'warning' ? '!' : 'GOOD';
          const detail = obj.error_detail ? ' — ' + obj.error_detail : '';
          addLine(icon + ' [' + done + '/' + total + '] ' + obj.url + detail, obj.status);
          if (obj.status === 'error') errors++;
          if (obj.status === 'warning') warnings++;
        } else if (obj.type === 'done') {
          scanLogId = obj.scan_log_id;
          msg.textContent = 'Scan complete!';
          bar.style.width = '100%';
          const ok = total - errors - warnings;
          document.getElementById('summaryContent').innerHTML =
            '<div class="stats-row">' +
            '<div class="stat-card"><div class="stat-num">' + total + '</div><div class="stat-lbl">Checked</div></div>' +
            '<div class="stat-card ' + (errors>0?'stat-error':'') + '"><div class="stat-num">' + errors + '</div><div class="stat-lbl">Errors</div></div>' +
            '<div class="stat-card ' + (warnings>0?'stat-warn':'') + '"><div class="stat-num">' + warnings + '</div><div class="stat-lbl">Warnings</div></div>' +
            '<div class="stat-card"><div class="stat-num">' + ok + '</div><div class="stat-lbl">OK</div></div>' +
            '</div>' +
            (obj.email_sent ? '<p class="muted">📧 Report emailed to ' + <?= json_encode(getSetting('email_to')) ?> + '</p>' : '');
          document.getElementById('logLink').href = 'log.php?date=' + obj.scan_date;
          document.getElementById('summaryCard').style.display = '';
        }
        } catch(e) {}
    }
  }
})();
</script>

<?php endif; ?>


</main>
</body>
</html>
