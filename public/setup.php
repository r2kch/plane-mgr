<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$errors = [];
$success = null;

$hasUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
$dompdfReady = dompdf_is_available();
$gdReady = extension_loaded('gd');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasUsers) {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        $errors[] = 'Ungültiger Request.';
    }

    if (!$dompdfReady) {
        $errors[] = 'Dompdf fehlt. Bitte public/vendor/dompdf hochladen.';
    }
    if (!$gdReady) {
        $errors[] = 'PHP-Erweiterung gd fehlt auf dem Server.';
    }

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || strlen($password) < 8) {
        $errors[] = 'Bitte alle Felder ausfüllen (Passwort min. 8 Zeichen).';
    }

    if ($errors === []) {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)db()->lastInsertId();

            $roleStmt = db()->prepare("SELECT id FROM roles WHERE name = 'admin'");
            $roleStmt->execute();
            $roleId = (int)$roleStmt->fetchColumn();
            if ($roleId <= 0) {
                throw new RuntimeException('Rolle admin fehlt.');
            }
            db()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);

            db()->commit();
            $success = 'Admin wurde angelegt. Du kannst dich jetzt einloggen.';
            $hasUsers = true;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'Admin konnte nicht angelegt werden.';
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup | Plane Manager</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div>
        <h1>Plane Manager</h1>
        <p>Reservation & Abrechnung</p>
      </div>
    </header>

    <main class="panel">
      <h2>Setup</h2>

      <?php if ($success !== null): ?>
        <div class="flash flash-success"><?= h($success) ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
      <?php endforeach; ?>

      <div class="table-wrap" style="margin-bottom: 16px;">
        <table class="entries-table">
          <thead>
            <tr><th>Prüfung</th><th>Status</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Dompdf vorhanden (`public/vendor/dompdf`)</td>
              <td><?= $dompdfReady ? 'OK' : 'Fehlt' ?></td>
            </tr>
            <tr>
              <td>PHP GD-Erweiterung</td>
              <td><?= $gdReady ? 'OK' : 'Fehlt' ?></td>
            </tr>
            <tr>
              <td>Admin bereits vorhanden</td>
              <td><?= $hasUsers ? 'Ja' : 'Nein' ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <?php if (!$dompdfReady): ?>
        <div class="flash flash-error">
          Dompdf fehlt. Bitte Dompdf von GitHub herunterladen und nach <strong>public/vendor/dompdf</strong> hochladen.
        </div>
      <?php endif; ?>

      <?php if ($hasUsers): ?>
        <p>Setup ist abgeschlossen. <a href="index.php?page=login">Zum Login</a></p>
      <?php else: ?>
        <h3>Ersten Admin anlegen</h3>
        <form method="post" class="grid-form">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <label>Vorname
            <input type="text" name="first_name" required>
          </label>
          <label>Nachname
            <input type="text" name="last_name" required>
          </label>
          <label>E-Mail
            <input type="email" name="email" required>
          </label>
          <label>Passwort
            <input type="password" name="password" minlength="8" required>
          </label>
          <button type="submit">Admin anlegen</button>
        </form>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>

