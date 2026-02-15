<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

require_login();
require_role('admin', 'accounting');

$resultMessage = '';
$resultType = '';
$testEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        $resultType = 'error';
        $resultMessage = 'Ungültiger Request.';
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
          <label>Empfänger E-Mail
            <input type="email" name="test_email" value="<?= h($testEmail) ?>" placeholder="test@example.com" required>
          </label>
          <button type="submit" class="btn-small" style="align-self: end;">Test-Mail senden</button>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
