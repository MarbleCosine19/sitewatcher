<?php
require_once __DIR__ . '/includes/config.php';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

         if ($_POST['action'] === 'add') {
       $url   = trim($_POST['url'] ?? ''
        );
     $label = trim($_POST['label'] ?? '');
              if (!filter_var($url, FILTER_VALIDATE_URL)) {
            flash('Invalid URL: ' . $url , 'error');
        } else {


            try {
       db()->prepare('INSERT INTO urls (url, label) VALUES (?,?)')
                    ->execute([$url, $label]);
                flash("Added: {$url}");
             } catch (PDOException $e) {
                flash('URL already exists or error: ' . $e->getMessage(), 'error');
            }
        }






    } elseif ($_POST['action'] === 'delete') {
 $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM urls WHERE id=?')->execute([$id]);
        flash('URL removed.');


    }    elseif ($_POST['action'] === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE urls SET active = 1 - active WHERE id=?')->execute([$id]);
     flash('URL status toggled.');




    } elseif ($_POST['action'] === 'import_csv') {

        if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK) {
       $handle = fopen($_FILES['csvfile']['tmp_name'], 'r');
            $added = 0; $skipped = 0; $invalid = 0;


              while (($row = fgetcsv($handle)) !== false) {
                $url = trim($row[0] ?? '');
                $label = trim($row[1] ?? '');

                if (!filter_var($url, FILTER_VALIDATE_URL)) { $invalid++; continue; }


                try {
                    db()->prepare('INSERT INTO urls (url, label) VALUES (?,?)')                    ->execute([$url, $label]);
                    $added++;
                } catch (PDOException $e) {
                    $skipped++; 
                }
            }
            fclose($handle);
 flash("Import complete: {$added} added, {$skipped} duplicates skipped, {$invalid} invalid.");





        } else {
            flash('No file uploaded or upload error.', 'error');
        }


    } elseif ($_POST['action'] === 'export_csv') {
       
        $rows = db()->query('SELECT url, label, added_at FROM urls WHERE active=1 ORDER BY id')->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sitewatcher_urls_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['URL', 'Label', 'Added At']);
        foreach ($rows as $r) fputcsv($out, [$r['url'], $r['label'], $r['added_at']]);
        fclose($out);
        exit;
    }

    header('Location: admin.php');   exit;
}

$flash = getFlash();
$urls = db()->query('SELECT * FROM urls ORDER BY id DESC')->fetchAll();
?>

---------------------------------------------------------------------------------------

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SiteWatcher — Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<main>
<div class="page-header">
  <h1>manage URLs</h1>
  <span class="muted"><?= count($urls) ?> total</span>
</div>

<?php if ($flash): ?>
<div class="alert <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>


<div class="card">
  <h2>Add URL</h2>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
  <input type="url" name="url" placeholder="https://example.com" required class="input">
      <input type="text" name="label" placeholder="Label (optional)" class="input">
      <button type="submit" class="btn btn-primary">Add</button>
    </div>
  </form>
</div>



<div class="card">
  <h2>Bulk Import / Export</h2>
  <div class="form-row">

    <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
   <input type="hidden" name="action" value="import_csv">
      <input type="file" name="csvfile" accept=".csv" class="input" required>
      <button type="submit" class="btn">import CSV</button>
    </form>

    <form method="post" style="margin-left:auto;">
      <input type="hidden" name="action" value="export_csv">
      <button type="submit" class="btn">⬇ Export CSV</button>
    </form>

  </div>
  <p class="muted" style="margin-top:8px;">CSV format: <code>url, label (optional)</code> — one per line. Duplicates are skipped automatically.</p>
</div>


<div class="card">
  <h2>URL List</h2>
  <?php if (empty($urls)): ?>
  <p class="muted">No URLs added yet.</p>
  <?php else: ?>

  <table>
    <thead>
      <tr>
        <th>#</th><th>URL</th><th>Label</th><th>Added</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>


    <tbody>
    <?php foreach ($urls as $row): ?>
     <tr class="<?= $row['active'] ? '' : 'row-disabled' ?>">

      <td><?= $row['id'] ?></td>
      <td><a href="<?= htmlspecialchars($row['url']) ?>" target="_blank"><?= htmlspecialchars($row['url']) ?></a></td>
      <td><?= htmlspecialchars($row['label'] ?? '') ?></td>
      <td><?= substr($row['added_at'], 0, 10) ?></td>
         <td><span class="badge <?= $row['active'] ? 'ok' : 'muted' ?>"><?= $row['active'] ? 'Active' : 'Paused' ?></span></td>

      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <button class="btn btn-sm"><?= $row['active'] ? 'Pause' : 'Resume' ?></button>
        </form>

        <form method="post" style="display:inline" onsubmit="return confirm('Delete this URL?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $row['id'] ?>">
          <button class="btn btn-sm btn-danger">Dleete</button>
        </form>
      </td>

    </tr>
    <?php endforeach; ?>
    </tbody>

  </table>
  <?php endif; ?>
</div>

</main>
</body>
</html>