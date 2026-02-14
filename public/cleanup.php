<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

require_login();
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        flash('error', 'Ungültiger Request.');
        header('Location: cleanup.php');
        exit;
    }

    if (($_POST['confirm'] ?? '') !== 'YES') {
        flash('error', 'Bitte zur Bestätigung YES eingeben.');
        header('Location: cleanup.php');
        exit;
    }

    db()->beginTransaction();
    try {
        $pdfPaths = db()->query('SELECT pdf_path FROM invoices WHERE pdf_path IS NOT NULL AND pdf_path <> ""')->fetchAll();

        db()->exec('DELETE FROM invoice_items');
        db()->exec('UPDATE reservations SET invoice_id = NULL WHERE invoice_id IS NOT NULL');
        db()->exec('DELETE FROM invoices');

        audit_log('cleanup', 'debug', null, ['target' => 'invoice_reset_keep_reservations']);
        db()->commit();

        foreach ($pdfPaths as $row) {
            $rawPath = trim((string)($row['pdf_path'] ?? ''));
            if ($rawPath === '') {
                continue;
            }

            $candidatePaths = [];
            if (str_starts_with($rawPath, '/')) {
                $candidatePaths[] = $rawPath;
            } else {
                $candidatePaths[] = __DIR__ . '/' . ltrim($rawPath, '/');
                $candidatePaths[] = dirname(__DIR__) . '/' . ltrim($rawPath, '/');
            }

            foreach ($candidatePaths as $candidatePath) {
                if (is_file($candidatePath)) {
                    @unlink($candidatePath);
                    break;
                }
            }
        }

        flash('success', 'Rechnungen wurden gelöscht und Reservierungen auf nicht verrechnet zurückgesetzt.');
        header('Location: index.php?page=reservations');
        exit;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        flash('error', 'Cleanup fehlgeschlagen.');
        header('Location: cleanup.php');
        exit;
    }
}

$flashes = pull_flashes();
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cleanup Debug</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="app-shell">
    <main class="panel" style="max-width:760px; margin:32px auto;">
      <h2>Debug Cleanup</h2>
      <p>Diese Aktion löscht <strong>alle Rechnungen und Rechnungspositionen</strong> und setzt Reservierungen auf <strong>nicht verrechnet</strong> zurück.</p>

      <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endforeach; ?>

      <form method="post" class="grid-form narrow">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <label>Bestätigung (YES eingeben)
          <input name="confirm" required>
        </label>
        <button type="submit">Cleanup ausführen</button>
      </form>

      <p><a href="index.php?page=reservations">Zurück</a></p>
    </main>
  </div>
</body>
</html>
