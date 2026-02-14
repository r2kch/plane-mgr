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

    $doReservations = isset($_POST['cleanup_reservations']);
    $doFlights = isset($_POST['cleanup_flights']);
    $doInvoices = isset($_POST['cleanup_invoices']);

    if (!$doReservations && !$doFlights && !$doInvoices) {
        flash('error', 'Bitte mindestens eine Cleanup-Option auswählen.');
        header('Location: cleanup.php');
        exit;
    }

    db()->beginTransaction();
    try {
        $pdfPaths = [];
        $executed = [];

        if ($doInvoices) {
            $pdfPaths = db()->query('SELECT pdf_path FROM invoices WHERE pdf_path IS NOT NULL AND pdf_path <> ""')->fetchAll();
            db()->exec('DELETE FROM invoice_items');
            db()->exec('UPDATE reservations SET invoice_id = NULL WHERE invoice_id IS NOT NULL');
            db()->exec('DELETE FROM invoices');
            $executed[] = 'rechnungen';
        }

        if ($doReservations) {
            // FK invoice_items.reservation_id -> reservations.id muss vor dem Löschen aufgelöst werden.
            db()->exec('DELETE ii FROM invoice_items ii JOIN reservations r ON r.id = ii.reservation_id');
            db()->exec('DELETE FROM reservation_flights');
            db()->exec('DELETE FROM reservations');
            $executed[] = 'reservierungen';
            $executed[] = 'fluege';
        } elseif ($doFlights) {
            db()->exec('DELETE FROM reservation_flights');
            db()->exec('UPDATE reservations SET hours = 0');
            $executed[] = 'fluege';
        }

        audit_log('cleanup', 'debug', null, [
            'reservations' => $doReservations,
            'flights' => $doFlights,
            'invoices' => $doInvoices,
        ]);
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

        $executed = array_values(array_unique($executed));
        flash('success', 'Cleanup abgeschlossen: ' . implode(', ', $executed) . '.');
        header('Location: cleanup.php');
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
      <p>Diese Aktion ist nur für Debug. Es werden nur die Bereiche ausgeführt, die angehakt sind.</p>

      <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endforeach; ?>

      <form method="post" class="grid-form narrow">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <label class="checks-inline">
          <span class="checkline"><input type="checkbox" name="cleanup_reservations" value="1"> Alle Reservierungen löschen</span>
          <span class="checkline"><input type="checkbox" name="cleanup_flights" value="1"> Alle Flüge löschen</span>
          <span class="checkline"><input type="checkbox" name="cleanup_invoices" value="1"> Alle Rechnungen löschen</span>
        </label>
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
