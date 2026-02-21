<h2>Positionen</h2>

<h3>Position erfassen</h3>
<form method="post" class="grid-form">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <?php
  $roleLabels = [
    'pilot' => 'Pilot',
    'accounting' => 'Buchhaltung',
    'admin' => 'Admin',
    'board' => 'Vorstand',
    'member' => 'Mitglied',
  ];
  ?>
  <?php if (!empty($editPosition)): ?>
    <input type="hidden" name="action" value="update_position">
    <input type="hidden" name="position_id" value="<?= (int)$editPosition['id'] ?>">
  <?php else: ?>
    <input type="hidden" name="action" value="create_positions">
  <?php endif; ?>

  <label>Titel
    <input name="description" value="<?= h((string)($editPosition['description'] ?? '')) ?>" required>
  </label>
  <label>Datum
    <input type="date" name="position_date" value="<?= h((string)($editPosition['position_date'] ?? $defaultPositionDate)) ?>" required>
  </label>
  <label>Betrag
    <input type="number" step="0.01" min="0" name="amount" value="<?= h((string)($editPosition['amount'] ?? '')) ?>" required>
  </label>
  <label>Notiz (optional)
    <input name="notes" value="<?= h((string)($editPosition['notes'] ?? '')) ?>">
  </label>

  <?php if (!empty($editPosition)): ?>
    <label>Benutzer
      <select name="user_id" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($activeUsers as $user): ?>
          <option value="<?= (int)$user['id'] ?>" <?= (int)($editPosition['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
            <?= h($user['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="inline-form">
      <button class="btn-small">Änderung speichern</button>
      <a class="btn-ghost btn-small" href="index.php?page=positions">Bearbeitung abbrechen</a>
    </div>
  <?php else: ?>
    <label>Gültigkeit
      <select name="scope" id="position-scope">
        <option value="role">Rollen</option>
        <option value="user">Personen</option>
      </select>
    </label>

    <div id="scope-roles" style="grid-column: 1 / -1;">
      <label>Rollen auswählen</label>
      <div class="checks">
        <?php foreach ($rolesList as $role): ?>
          <label class="checkline">
            <input type="checkbox" name="role_names[]" value="<?= h($role['name']) ?>">
            <?= h($roleLabels[$role['name']] ?? $role['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="scope-users" style="grid-column: 1 / -1; display: none;">
      <label style="margin-bottom: 10px;">Suche
        <input type="text" id="position-user-search" placeholder="Nachname suchen">
      </label>
      <div class="checks" id="position-user-list">
        <?php foreach ($activeUsers as $user): ?>
          <label class="checkline" data-name="<?= h(strtolower($user['name'])) ?>">
            <input type="checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>">
            <?= h($user['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="inline-form">
      <button class="btn-small btn-danger-solid">Position erstellen</button>
    </div>
  <?php endif; ?>
</form>

<?php if (empty($editPosition)): ?>
  <script>
    const scopeSelect = document.getElementById('position-scope');
    const scopeRoles = document.getElementById('scope-roles');
    const scopeUsers = document.getElementById('scope-users');
    const userSearch = document.getElementById('position-user-search');
    const userList = document.getElementById('position-user-list');

    const updateScope = () => {
      const scope = scopeSelect.value;
      scopeRoles.style.display = scope === 'role' ? 'block' : 'none';
      scopeUsers.style.display = scope === 'user' ? 'block' : 'none';
    };
    scopeSelect.addEventListener('change', updateScope);
    updateScope();

    if (userSearch && userList) {
      userSearch.addEventListener('input', () => {
        const query = userSearch.value.toLowerCase().trim();
        userList.querySelectorAll('label[data-name]').forEach((label) => {
          const name = label.getAttribute('data-name') || '';
          label.style.display = name.includes(query) ? 'inline-flex' : 'none';
        });
      });
    }
  </script>
<?php endif; ?>

<h3>Offene Positionen</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Benutzer</th>
        <th>Datum</th>
        <th>Beschreibung</th>
        <th>Betrag</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($positions)): ?>
        <tr><td colspan="5">Keine offenen Positionen vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($positions as $p): ?>
          <tr>
            <td><?= h($p['user_name']) ?></td>
            <td><?= h(date('d.m.Y', strtotime((string)$p['position_date']))) ?></td>
            <td><?= h((string)$p['description']) ?></td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= h(number_format((float)$p['amount'], 2, '.', '')) ?></td>
            <td>
              <a class="btn-small" href="index.php?page=positions&edit_position_id=<?= (int)$p['id'] ?>">Bearbeiten</a>
              <form method="post" class="inline-form" onsubmit="return confirm('Position wirklich löschen?');">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_position">
                <input type="hidden" name="position_id" value="<?= (int)$p['id'] ?>">
                <button class="btn-ghost btn-small">Löschen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<h3>Bereits verrechnet</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Benutzer</th>
        <th>Datum</th>
        <th>Beschreibung</th>
        <th>Betrag</th>
        <th>Rechnung</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($settledPositions)): ?>
        <tr><td colspan="5">Keine verrechneten Positionen vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($settledPositions as $p): ?>
          <tr>
            <td><?= h($p['user_name']) ?></td>
            <td><?= h(date('d.m.Y', strtotime((string)$p['position_date']))) ?></td>
            <td><?= h((string)$p['description']) ?></td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= h(number_format((float)$p['amount'], 2, '.', '')) ?></td>
            <td><?= h((string)$p['invoice_number']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
