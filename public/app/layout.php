<?php

declare(strict_types=1);

function render(string $title, string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $user = current_user();
    $flashes = pull_flashes();
    ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($title) ?> | Plane Manager</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div>
        <h1>Plane Manager</h1>
        <p>Reservation & Abrechnung</p>
      </div>
      <?php if ($user): ?>
        <div class="user-chip">
          <a href="index.php?page=profile"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></a>
          <a href="index.php?page=logout">Logout</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($user): ?>
      <nav class="nav-grid">
        <a href="index.php">Dashboard</a>
        <?php if (module_enabled('reservations')): ?><a href="index.php?page=reservations">Reservierungen</a><?php endif; ?>
        <?php if (module_enabled('billing')): ?><a href="index.php?page=my_invoices">Meine Rechnungen</a><?php endif; ?>
        <a href="index.php?page=members">Mitglieder</a>
        <?php if (has_role('admin')): ?>
          <div class="nav-dropdown">
            <span class="nav-link-red">Admin</span>
            <div class="nav-dropdown-menu">
              <a href="index.php?page=admin">Admin</a>
              <a href="index.php?page=aircraft">Flugzeuge</a>
              <a href="index.php?page=groups">Flugzeug-Gruppen</a>
              <a href="index.php?page=users">Benutzer</a>
              <?php if (module_enabled('audit')): ?><a href="index.php?page=audit">Audit-Log</a><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if (module_enabled('billing') && has_role('admin', 'accounting')): ?>
          <div class="nav-dropdown">
            <span class="nav-link-red">Buchhaltung</span>
            <div class="nav-dropdown-menu">
              <a href="index.php?page=accounting">Buchhaltung</a>
              <a href="index.php?page=invoices">Abrechnung</a>
              <?php if (has_role('admin')): ?><a href="index.php?page=rates">Preise</a><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <main class="panel">
      <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endforeach; ?>

      <?php include __DIR__ . '/views/' . $view . '.php'; ?>
    </main>
  </div>
</body>
</html>
<?php
}
