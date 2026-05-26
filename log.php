<?php
require_once __DIR__ . '/includes/config.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$pdo = db();


$log = $pdo->prepare('SELECT * FROM scan_logs WHERE scan_date = ? ORDER BY id DESC LIMIT 1');
$log->execute([$date]);
$logRow = $log->fetch();

$results = [];
if ($logRow) {
    $r = $pdo->prepare('SELECT * FROM scan_results WHERE scan_log_id = ? ORDER BY status DESC, url');
    $r->execute([$logRow['id']]);
    $results = $r->fetchAll();
}


$dates = $pdo->query('SELECT DISTINCT scan_date FROM scan_logs ORDER BY scan_date DESC LIMIT 30')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SiteWatcher — Log <?= htmlspecialchars($date) ?></title>


<link rel="stylesheet" href="style.css">
<style>
  .ss-thumb{width:120px;height:70px;object-fit:cover;border-radius:4px;border:1px solid #ddd;cursor:pointer;}
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;}
  .modal.open{display:flex;}
  .modal img{max-width:90vw;max-height:90vh;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,.5);}
  .modal-close{position:absolute;top:20px;right:28px;font-size:32px;color:#fff;cursor:pointer;line-height:1;}
  .anomaly-tag{display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;
    padding:1px 6px;border-radius:3px;font-size:11px;margin:2px;}
  .filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
  .filter-btn{padding:5px 14px;border:1px solid #ddd;border-radius:4px;background:#fff;cursor:pointer;font-size:13px;}
  .filter-btn.active{background:#1a1a2e;color:#fff;border-color:#1a1a2e;}
</style>



</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>

<main>
<div class="page-header">
  <h1>Log — <?= htmlspecialchars($date) ?></h1>
  <select onchange="location='log.php?date='+this.value" class="input" style="width:auto;">
    <?php foreach ($dates as $d): ?>
    <option value="<?= $d['scan_date'] ?>" <?= $d['scan_date'] === $date ? 'selected' : '' ?>><?= $d['scan_date'] ?></option>
    <?php endforeach; ?>
  </select>
</div>

<?php if (!$logRow): ?>
<div class="alert error">No scan log found for <?= htmlspecialchars($date) ?>.</div>
<?php else: ?>


<div class="stats-row">
  <div class="stat-card"><div class="stat-num"><?= $logRow['total_checked'] ?></div><div class="stat-lbl">Checked</div></div>
  <div class="stat-card <?= $logRow['total_errors'] > 0 ? 'stat-error' : '' ?>">
    <div class="stat-num"><?= $logRow['total_errors'] ?></div><div class="stat-lbl">Errors</div>
  </div>
  <div class="stat-card <?= $logRow['total_warnings'] > 0 ? 'stat-warn' : '' ?>">
    <div class="stat-num"><?= $logRow['total_warnings'] ?></div><div class="stat-lbl">Warnings</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $logRow['total_checked'] - $logRow['total_errors'] - $logRow['total_warnings'] ?></div>
    <div class="stat-lbl">OK</div>
  </div>
</div>





<div class="filter-bar">
  <button class="filter-btn active" onclick="filterRows('all', this)">All</button>
  <button class="filter-btn" onclick="filterRows('error', this)">Errors</button>
  <button class="filter-btn" onclick="filterRows('warning', this)">Warnings</button>
  <button class="filter-btn" onclick="filterRows('ok', this)">OK</button>
</div>


<div class="card" style="padding:0;">
<table id="resultsTable">
  <thead>
    <tr>
      <th>Screenshot</th>
      <th>URL</th>
      <th>Status</th>
      <th>Issue</th>
      <th>HTTP</th>
      <th>Time</th>
      <th>Detail</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($results as $r): ?>
  <?php
    $icon = match($r['status']) { 'error'=>'X', 'warning'=>'!', default=>'GOOD' };
    $ssPath = $r['screenshot_path'] ? BASE_URL . '/' . $r['screenshot_path'] : '';
  ?>
  <tr data-status="<?= $r['status'] ?>">
    <td>
      <?php if ($ssPath): ?>
        <img src="<?= htmlspecialchars($ssPath) ?>" class="ss-thumb"
             onclick="openModal('<?= htmlspecialchars($ssPath) ?>')" alt="screenshot">
      <?php else: ?>
        <span class="muted" style="font-size:11px;">No screenshot</span>
      <?php endif; ?>
    </td>
    <td style="max-width:220px;word-break:break-all;">
      <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank"><?= htmlspecialchars($r['url']) ?></a>
    </td>
    <td><?= $icon ?> <span class="badge <?= $r['status'] === 'warning' ? 'warn' : $r['status'] ?>"><?= $r['status'] ?></span></td>
    <td><?= $r['error_type'] ? htmlspecialchars($r['error_type']) : '—' ?></td>
    <td><?= $r['http_code'] ?: '—' ?></td>
    <td><?= $r['response_time'] ? $r['response_time'] . 's' : '—' ?></td>
    <td>
      <?= $r['error_detail'] ? htmlspecialchars($r['error_detail']) : '' ?>
      <?php if ($r['redirect_url']): ?>
        <br><small class="muted">→ <?= htmlspecialchars($r['redirect_url']) ?></small>
      <?php endif; ?>
      <?php if ($r['anomalies_found']): ?>
        <br><?php foreach (json_decode($r['anomalies_found'], true) as $kw): ?>
          <span class="anomaly-tag"><?= htmlspecialchars($kw) ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php endif; ?>

</main>


<div class="modal" id="modal" onclick="closeModal()">
  <span class="modal-close" onclick="closeModal()">×</span>
  <img src="" id="modalImg" alt="screenshot">
</div>

<script>
function closeModal() {
  document.getElementById('modal').classList.remove('open');
}

function openModal(src) {
  document.getElementById('modalImg').src = src;
  document.getElementById('modal').classList.add('open');
}

function filterRows(status, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#resultsTable tbody tr').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}
</script>
</body>
</html>
