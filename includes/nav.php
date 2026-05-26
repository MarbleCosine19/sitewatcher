<nav>
  <div class="nav-inner">
    <a href="index.php" class="nav-brand">🔍 SiteWatcher</a>
    <div class="nav-links">  

      <a href="index.php" <?= basename($_SERVER['PHP_SELF'])==='index.php'?'class="active"':'' ?>>Dashboard</a>
      <a href="admin.php" <?= basename($_SERVER['PHP_SELF'])==='admin.php'?'class="active"':'' ?>>URLs</a>
      <a href="log.php" <?= basename($_SERVER['PHP_SELF'])==='log.php'?'class="active"':'' ?>>Logs</a>
      <a href="settings.php" <?= basename($_SERVER['PHP_SELF'])==='settings.php'?'class="active"':'' ?>>Settings</a>
      <a href="run.php" class="btn btn-primary nav-run">▶ Run Scan</a>

    </div>
  </div>
</nav>
