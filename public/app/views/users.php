<h2>Benutzerverwaltung</h2>

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

<h3>Bestehende Benutzer</h3>
<form method="get" class="inline-form" style="margin-bottom: 10px;">
  <input type="hidden" name="page" value="users">
  <label>Suche
    <input name="q" value="<?= h($userSearch ?? '') ?>" placeholder="Vorname, Nachname oder E-Mail">
  </label>
  <button type="submit" class="btn-small">Filtern</button>
  <?php if (!empty($userSearch)): ?>
    <a class="btn-ghost btn-small" href="index.php?page=users">Zurücksetzen</a>
  <?php endif; ?>
</form>
<div class="user-list">
  <?php foreach ($users as $u): ?>
    <?php $deleteFormId = 'delete-user-' . (int)$u['id']; ?>
    <form method="post" class="user-item">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

      <div class="grid-form user-grid">
        <label>Vorname
          <input name="first_name" value="<?= h($u['first_name']) ?>" required>
        </label>
        <label>Nachname
          <input name="last_name" value="<?= h($u['last_name']) ?>" required>
        </label>
        <label>E-Mail
          <input type="email" name="email" value="<?= h($u['email']) ?>" required>
        </label>
        <label>Status
          <select name="is_active">
            <option value="1" <?= (int)$u['is_active'] === 1 ? 'selected' : '' ?>>Aktiv</option>
            <option value="0" <?= (int)$u['is_active'] === 0 ? 'selected' : '' ?>>Deaktiviert</option>
          </select>
        </label>
        <label>Neues Passwort (optional)
          <input type="password" name="new_password" minlength="8" placeholder="mind. 8 Zeichen">
        </label>
      </div>

      <label>Rolle
        <div class="checks checks-inline role-group">
          <label class="checkline"><input type="checkbox" name="roles[]" value="pilot" <?= in_array('pilot', $u['roles'], true) ? 'checked' : '' ?>> Pilot</label>
          <label class="checkline"><input type="checkbox" name="roles[]" value="accounting" <?= in_array('accounting', $u['roles'], true) ? 'checked' : '' ?>> Buchhaltung</label>
          <label class="checkline"><input type="checkbox" name="roles[]" value="admin" <?= in_array('admin', $u['roles'], true) ? 'checked' : '' ?>> Admin</label>
        </div>
      </label>

      <div class="inline-form user-meta">
        <span>ID: <?= (int)$u['id'] ?></span>
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
      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>
