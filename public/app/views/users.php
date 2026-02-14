<h2>Benutzerverwaltung</h2>
<div class="section-head-actions">
  <?php
  $newUserHref = 'index.php?page=users&new=' . (((int)($showNewUserForm ?? 0) === 1) ? '0' : '1');
  if (!empty($userSearch)) {
      $newUserHref .= '&q=' . urlencode((string)$userSearch);
  }
  ?>
  <a class="btn-small" href="<?= h($newUserHref) ?>">Neuer Benutzer</a>
</div>

<?php if ((int)($showNewUserForm ?? 0) === 1): ?>
  <h3>Neuen Benutzer anlegen</h3>
  <form method="post" class="grid-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <label>Vorname
      <input name="first_name" required>
    </label>
    <label>Nachname
      <input name="last_name" required>
    </label>
    <label>E-Mail
      <input type="email" name="email" required>
    </label>
    <label>Rolle
      <div class="checks checks-inline role-group">
        <label class="checkline"><input type="checkbox" name="roles[]" value="pilot" checked> Pilot</label>
        <label class="checkline"><input type="checkbox" name="roles[]" value="accounting"> Buchhaltung</label>
        <label class="checkline"><input type="checkbox" name="roles[]" value="admin"> Admin</label>
      </div>
    </label>
    <label>Initiales Passwort
      <input type="password" name="password" minlength="8" required>
    </label>
    <button type="submit">Benutzer erstellen</button>
  </form>
<?php endif; ?>

<h3>Bestehende Benutzer</h3>
<form method="get" class="inline-form user-search-form" style="margin-bottom: 12px;">
  <input type="hidden" name="page" value="users">
  <?php if ((int)($showNewUserForm ?? 0) === 1): ?>
    <input type="hidden" name="new" value="1">
  <?php endif; ?>
  <label>Suche
    <input name="q" value="<?= h($userSearch ?? '') ?>" placeholder="Vorname, Nachname oder E-Mail">
  </label>
  <button type="submit" class="btn-small">Filtern</button>
  <?php if (!empty($userSearch)): ?>
    <a class="btn-ghost btn-small" href="index.php?page=users<?= ((int)($showNewUserForm ?? 0) === 1) ? '&new=1' : '' ?>">Zurücksetzen</a>
  <?php endif; ?>
</form>
<div class="table-wrap" style="margin-bottom: 12px;">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Vorname</th>
        <th>Nachname</th>
        <th>E-Mail</th>
        <th>Rollen</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="5">Keine Benutzer gefunden.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <?php
          $openHref = 'index.php?page=users&open_user_id=' . (int)$u['id'];
          if (!empty($userSearch)) {
              $openHref .= '&q=' . urlencode((string)$userSearch);
          }
          if ((int)($showNewUserForm ?? 0) === 1) {
              $openHref .= '&new=1';
          }
          ?>
          <tr>
            <td><a href="<?= h($openHref) ?>"><?= h($u['first_name']) ?></a></td>
            <td><a href="<?= h($openHref) ?>"><?= h($u['last_name']) ?></a></td>
            <td><?= h($u['email']) ?></td>
            <td><?= h(implode(', ', $u['roles'])) ?></td>
            <td><?= (int)$u['is_active'] === 1 ? 'Aktiv' : 'Deaktiviert' ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$openUser = null;
foreach ($users as $candidate) {
    if ((int)$candidate['id'] === (int)($openUserId ?? 0)) {
        $openUser = $candidate;
        break;
    }
}
?>

<?php if ($openUser !== null): ?>
  <?php $deleteFormId = 'delete-user-' . (int)$openUser['id']; ?>
  <h3>Benutzer bearbeiten</h3>
  <form method="post" class="user-item">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="user_id" value="<?= (int)$openUser['id'] ?>">

    <div class="grid-form user-grid">
      <label>Vorname
        <input name="first_name" value="<?= h($openUser['first_name']) ?>" required>
      </label>
      <label>Nachname
        <input name="last_name" value="<?= h($openUser['last_name']) ?>" required>
      </label>
      <label>E-Mail
        <input type="email" name="email" value="<?= h($openUser['email']) ?>" required>
      </label>
      <label>Status
        <select name="is_active">
          <option value="1" <?= (int)$openUser['is_active'] === 1 ? 'selected' : '' ?>>Aktiv</option>
          <option value="0" <?= (int)$openUser['is_active'] === 0 ? 'selected' : '' ?>>Deaktiviert</option>
        </select>
      </label>
      <label>Neues Passwort (optional)
        <input type="password" name="new_password" minlength="8" placeholder="mind. 8 Zeichen">
      </label>
    </div>

    <label>Rolle
      <div class="checks checks-inline role-group">
        <label class="checkline"><input type="checkbox" name="roles[]" value="pilot" <?= in_array('pilot', $openUser['roles'], true) ? 'checked' : '' ?>> Pilot</label>
        <label class="checkline"><input type="checkbox" name="roles[]" value="accounting" <?= in_array('accounting', $openUser['roles'], true) ? 'checked' : '' ?>> Buchhaltung</label>
        <label class="checkline"><input type="checkbox" name="roles[]" value="admin" <?= in_array('admin', $openUser['roles'], true) ? 'checked' : '' ?>> Admin</label>
      </div>
    </label>

    <div class="inline-form user-meta">
      <span>ID: <?= (int)$openUser['id'] ?></span>
      <button type="submit" class="btn-small">Speichern</button>
      <button
        type="submit"
        form="<?= h($deleteFormId) ?>"
        class="btn-ghost btn-small"
        onclick="return confirm('Benutzer wirklich löschen? Aktive Reservierungen werden entfernt.');"
      >
        Benutzer löschen
      </button>
    </div>
  </form>
  <form method="post" id="<?= h($deleteFormId) ?>" class="user-delete-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" value="<?= (int)$openUser['id'] ?>">
  </form>
<?php endif; ?>
