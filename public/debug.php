<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

require_login();
require_role('admin', 'accounting');

$resultMessage = '';
$resultType = '';
$testEmail = '';
$reservationId = '';
$reservationStartsAt = '';
$reservationEndsAt = '';
$reservationStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        $resultType = 'error';
        $resultMessage = 'Ungültiger Request.';
    } else {
        $action = (string)($_POST['action'] ?? 'smtp_test');

        if ($action === 'reservation_update') {
            $reservationId = trim((string)($_POST['reservation_id'] ?? ''));
            $reservationStartsAt = trim((string)($_POST['starts_at'] ?? ''));
            $reservationEndsAt = trim((string)($_POST['ends_at'] ?? ''));
            $reservationStatus = trim((string)($_POST['status'] ?? ''));

            $id = (int)$reservationId;
            $startTs = $reservationStartsAt !== '' ? strtotime($reservationStartsAt) : false;
            $endTs = $reservationEndsAt !== '' ? strtotime($reservationEndsAt) : false;

            if ($id <= 0 || $startTs === false || $endTs === false) {
                $resultType = 'error';
                $resultMessage = 'Bitte gültige Reservation-ID sowie Start/Ende erfassen.';
            } else {
                $statusValue = $reservationStatus !== '' ? $reservationStatus : null;
                $sql = 'UPDATE reservations SET starts_at = ?, ends_at = ?' . ($statusValue ? ', status = ?' : '') . ' WHERE id = ?';
                $params = $statusValue
                    ? [date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs), $statusValue, $id]
                    : [date('Y-m-d H:i:s', $startTs), date('Y-m-d H:i:s', $endTs), $id];
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                audit_log('debug_update', 'reservation', $id, [
                    'starts_at' => date('Y-m-d H:i:s', $startTs),
                    'ends_at' => date('Y-m-d H:i:s', $endTs),
                    'status' => $statusValue,
                ]);
                $resultType = 'success';
                $resultMessage = 'Reservation aktualisiert.';
            }
        } else {
            $testEmail = trim((string)($_POST['test_email'] ?? ''));
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $resultType = 'error';
                $resultMessage = 'Bitte eine gültige E-Mail Adresse eingeben.';
            } else {
                $subject = 'Plane Manager SMTP Test';
                $text = "Dies ist eine Test-Mail aus Plane Manager.\n\nZeit: " . date('d.m.Y H:i:s');
                $html = '<p>Dies ist eine <strong>Test-Mail</strong> aus Plane Manager.</p><p>Zeit: ' . h(date('d.m.Y H:i:s')) . '</p>';
                $send = smtp_send_mail($testEmail, $subject, $html, $text);
                if ($send['ok']) {
                    if (!empty($send['skipped'])) {
                        $resultType = 'success';
                        $resultMessage = 'SMTP ist deaktiviert. Es wurde keine Test-Mail versendet.';
                    } else {
                        $resultType = 'success';
                        $resultMessage = 'Test-Mail erfolgreich via SMTP versendet an ' . $testEmail . '.';
                        audit_log('debug_mail_test', 'smtp', null, ['to' => $testEmail]);
                    }
                } else {
                    $resultType = 'error';
                    $resultMessage = 'SMTP Test fehlgeschlagen: ' . (string)$send['error'];
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Debug | Plane Manager</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div>
        <h1>Plane Manager</h1>
        <p>Debug</p>
      </div>
      <div class="user-chip">
        <a href="index.php">Zur App</a>
        <a href="index.php?page=logout">Logout</a>
      </div>
    </header>

    <main class="panel">
      <h2>Debug</h2>
      <p><a href="cleanup.php">Zur Cleanup-Seite</a></p>

      <?php if ($resultMessage !== ''): ?>
        <div class="flash flash-<?= h($resultType === 'success' ? 'success' : 'error') ?>"><?= h($resultMessage) ?></div>
      <?php endif; ?>

      <h3>SMTP Einstellungen testen</h3>
      <div class="user-item">
        <?php if (!(bool)config('smtp.enabled', true)): ?>
          <div class="flash flash-error">SMTP ist per Config deaktiviert (`smtp.enabled = false`).</div>
        <?php elseif (!smtp_enabled()): ?>
          <div class="flash flash-error">SMTP ist nicht vollständig konfiguriert (host/port/from).</div>
        <?php endif; ?>
        <form method="post" class="grid-form" style="grid-template-columns: minmax(260px, 420px) auto;">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="smtp_test">
          <label>Empfänger E-Mail
            <input type="email" name="test_email" value="<?= h($testEmail) ?>" placeholder="test@example.com" required>
          </label>
          <button type="submit" class="btn-small" style="align-self: end;">Test-Mail senden</button>
        </form>
      </div>

      <h3>Reservation direkt ändern (Debug)</h3>
      <div class="user-item">
        <p>Ändert Start/Ende ohne Prüf-Logik. Nur im Debug verwenden.</p>
        <form method="post" class="grid-form" style="grid-template-columns: minmax(200px, 260px) minmax(220px, 320px) minmax(220px, 320px) minmax(180px, 220px) auto;">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="reservation_update">
          <label>Reservation ID
            <input type="number" name="reservation_id" min="1" value="<?= h($reservationId) ?>" required>
          </label>
          <label>Startzeit
            <input type="datetime-local" name="starts_at" value="<?= h($reservationStartsAt) ?>" required>
          </label>
          <label>Endzeit
            <input type="datetime-local" name="ends_at" value="<?= h($reservationEndsAt) ?>" required>
          </label>
          <label>Status (optional)
            <select name="status">
              <option value="">Unverändert</option>
              <option value="booked" <?= $reservationStatus === 'booked' ? 'selected' : '' ?>>booked</option>
              <option value="cancelled" <?= $reservationStatus === 'cancelled' ? 'selected' : '' ?>>cancelled</option>
              <option value="completed" <?= $reservationStatus === 'completed' ? 'selected' : '' ?>>completed</option>
            </select>
          </label>
          <button type="submit" class="btn-small" style="align-self: end;">Reservation ändern</button>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
