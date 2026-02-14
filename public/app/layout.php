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
          <span><?= h($user['first_name'] . ' ' . $user['last_name']) ?> (<?= h(implode(', ', $user['roles'] ?? [])) ?>)</span>
          <a href="index.php?page=logout">Logout</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($user): ?>
      <nav class="nav-grid">
        <a href="index.php">Dashboard</a>
        <a href="index.php?page=reservations">Reservierungen</a>
        <a href="index.php?page=my_invoices">Meine Rechnungen</a>
        <?php if (has_role('admin')): ?><a href="index.php?page=aircraft">Flugzeuge</a><?php endif; ?>
        <?php if (has_role('admin')): ?><a href="index.php?page=users">Benutzer</a><?php endif; ?>
        <?php if (has_role('admin')): ?><a href="index.php?page=rates">Preise</a><?php endif; ?>
        <?php if (has_role('admin', 'accounting')): ?><a href="index.php?page=invoices">Abrechnung</a><?php endif; ?>
        <?php if (has_role('admin')): ?><a href="index.php?page=audit">Audit-Log</a><?php endif; ?>
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
