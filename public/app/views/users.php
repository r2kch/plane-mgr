<h2>Benutzerverwaltung</h2>
<?php if (can('admin.access')): ?><p class="access-note">Zugang: <code>users.manage</code> · Geschützt: <code>users.manage.protected</code></p><?php endif; ?>
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
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="grid-form" style="grid-template-columns: repeat(3, minmax(220px, 1fr)); margin-bottom: 10px;">
      <label>Vorname
        <input name="first_name" required>
      </label>
      <label>Name
        <input name="last_name" required>
      </label>
      <label>E-Mail
        <input type="email" name="email" required>
      </label>
    </div>

    <div class="grid-form" style="grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.6fr) minmax(140px, 0.8fr) minmax(180px, 1fr) minmax(180px, 1fr); margin-bottom: 10px;">
      <label>Strasse
        <input name="street">
      </label>
      <label>Hausnummer
        <input name="house_number">
      </label>
      <label>Postleitzahl
        <input name="postal_code">
      </label>
      <label>Ort
        <input name="city">
      </label>
      <label>Land
        <select name="country_code">
          <?php foreach (($countryOptions ?? []) as $countryCode => $countryName): ?>
            <option value="<?= h((string)$countryCode) ?>" <?= (string)$countryCode === 'CH' ? 'selected' : '' ?>><?= h((string)$countryName) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="grid-form" style="grid-template-columns: repeat(2, minmax(220px, 1fr)); margin-bottom: 10px;">
      <label>Telefonnummer
        <input name="phone">
      </label>
      <label>Initiales Passwort
        <input type="password" name="password" minlength="8" required>
      </label>
    </div>

    <label>Rolle
      <div class="checks role-group">
        <?php foreach (($rolesList ?? []) as $roleName): ?>
          <label class="checkline">
            <input type="checkbox" name="roles[]" value="<?= h((string)$roleName) ?>" <?= (string)$roleName === 'pilot' ? 'checked data-pilot-toggle="create"' : '' ?>>
            <span><?= h(role_label((string)$roleName)) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </label>
    <label class="pilot-groups" data-pilot-target="create">Gruppen (nur für Pilot)
      <div class="checks role-group">
        <?php foreach (($allGroups ?? []) as $group): ?>
          <label class="checkline"><input type="checkbox" name="group_ids[]" value="<?= (int)$group['id'] ?>"><span><?= h((string)$group['name']) ?></span></label>
        <?php endforeach; ?>
        <?php if (empty($allGroups)): ?>
          <span>Keine Gruppen vorhanden.</span>
        <?php endif; ?>
      </div>
    </label>
    <button type="submit" class="btn-small">Benutzer erstellen</button>
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
            <td><?= h(implode(', ', array_map('role_label', $u['roles']))) ?></td>
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
  <?php $openUserGroupIds = $userGroupIdsByUser[(int)$openUser['id']] ?? []; ?>
  <h3>Benutzer bearbeiten</h3>
  <form method="post" class="user-item">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="user_id" value="<?= (int)$openUser['id'] ?>">

    <div class="grid-form" style="grid-template-columns: repeat(3, minmax(220px, 1fr)); margin-bottom: 10px;">
      <label>Vorname
        <input name="first_name" value="<?= h($openUser['first_name']) ?>" required>
      </label>
      <label>Name
        <input name="last_name" value="<?= h($openUser['last_name']) ?>" required>
      </label>
      <label>E-Mail
        <input type="email" name="email" value="<?= h($openUser['email']) ?>" required>
      </label>
    </div>

    <div class="grid-form" style="grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.6fr) minmax(140px, 0.8fr) minmax(180px, 1fr) minmax(180px, 1fr); margin-bottom: 10px;">
      <label>Strasse
        <input name="street" value="<?= h((string)($openUser['street'] ?? '')) ?>">
      </label>
      <label>Hausnummer
        <input name="house_number" value="<?= h((string)($openUser['house_number'] ?? '')) ?>">
      </label>
      <label>Postleitzahl
        <input name="postal_code" value="<?= h((string)($openUser['postal_code'] ?? '')) ?>">
      </label>
      <label>Ort
        <input name="city" value="<?= h((string)($openUser['city'] ?? '')) ?>">
      </label>
      <label>Land
        <select name="country_code">
          <?php foreach (($countryOptions ?? []) as $countryCode => $countryName): ?>
            <option value="<?= h((string)$countryCode) ?>" <?= (string)$countryCode === (string)($openUser['country_code'] ?? 'CH') ? 'selected' : '' ?>><?= h((string)$countryName) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="grid-form" style="grid-template-columns: repeat(3, minmax(220px, 1fr)); margin-bottom: 10px;">
      <label>Telefonnummer
        <input name="phone" value="<?= h((string)($openUser['phone'] ?? '')) ?>">
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
      <div class="checks role-group">
        <?php foreach (($rolesList ?? []) as $roleName): ?>
          <label class="checkline">
            <input type="checkbox" name="roles[]" value="<?= h((string)$roleName) ?>" <?= in_array((string)$roleName, $openUser['roles'], true) ? 'checked' : '' ?> <?= (string)$roleName === 'pilot' ? 'data-pilot-toggle="edit"' : '' ?>>
            <span><?= h(role_label((string)$roleName)) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </label>
    <label class="pilot-groups" data-pilot-target="edit">Gruppen (nur für Pilot)
      <div class="checks role-group">
        <?php foreach (($allGroups ?? []) as $group): ?>
          <label class="checkline"><input type="checkbox" name="group_ids[]" value="<?= (int)$group['id'] ?>" <?= in_array((int)$group['id'], $openUserGroupIds, true) ? 'checked' : '' ?>><span><?= h((string)$group['name']) ?></span></label>
        <?php endforeach; ?>
        <?php if (empty($allGroups)): ?>
          <span>Keine Gruppen vorhanden.</span>
        <?php endif; ?>
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

<script>
  (function () {
    function updatePilotGroupsVisibility(mode) {
      const toggle = document.querySelector('input[data-pilot-toggle="' + mode + '"]');
      const target = document.querySelector('[data-pilot-target="' + mode + '"]');
      if (!toggle || !target) return;
      target.style.display = toggle.checked ? 'flex' : 'none';
    }

    ['create', 'edit'].forEach(function (mode) {
      const toggle = document.querySelector('input[data-pilot-toggle="' + mode + '"]');
      if (!toggle) return;
      toggle.addEventListener('change', function () {
        updatePilotGroupsVisibility(mode);
      });
      updatePilotGroupsVisibility(mode);
    });
  }());
</script>
