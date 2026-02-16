<h2>Gutschrift</h2>

<?php $isEditing = !empty($editCredit); ?>
<h3><?= $isEditing ? 'Gutschrift bearbeiten' : 'Gutschrift erfassen' ?></h3>
<form method="post" class="capture-form">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="<?= $isEditing ? 'update_credit' : 'create_credit' ?>">
  <?php if ($isEditing): ?>
    <input type="hidden" name="credit_id" value="<?= (int)$editCredit['id'] ?>">
  <?php endif; ?>

  <div class="flight-card">
    <div class="flight-row flight-row-3">
      <label>Pilot
        <select name="user_id" required>
          <?php foreach ($pilots as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $isEditing && (int)$editCredit['user_id'] === (int)$p['id'] ? 'selected' : '' ?>>
              <?= h($p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Datum
        <input type="date" name="credit_date" value="<?= h($isEditing ? (string)$editCredit['credit_date'] : (string)$defaultCreditDate) ?>" required>
      </label>
      <label>Betrag (CHF)
        <input type="number" name="amount" min="0.01" step="0.01" value="<?= h($isEditing ? number_format((float)$editCredit['amount'], 2, '.', '') : '') ?>" required>
      </label>
    </div>
    <div class="flight-row flight-row-2">
      <label>Beschreibung
        <input name="description" maxlength="255" value="<?= h($isEditing ? (string)$editCredit['description'] : '') ?>" required>
      </label>
      <label>Notiz (optional)
        <input name="notes" maxlength="500" value="<?= h($isEditing ? (string)$editCredit['notes'] : '') ?>">
      </label>
    </div>
  </div>

  <div class="capture-actions">
    <button type="submit" class="btn-small"><?= $isEditing ? 'Änderungen speichern' : 'Gutschrift speichern' ?></button>
    <?php if ($isEditing): ?>
      <a class="btn-ghost btn-small" href="index.php?page=credits">Bearbeitung abbrechen</a>
      <button type="submit" form="delete-credit-form" class="btn-ghost btn-small" onclick="return confirm('Gutschrift wirklich löschen?');">Löschen</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($isEditing): ?>
  <form method="post" id="delete-credit-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete_credit">
    <input type="hidden" name="credit_id" value="<?= (int)$editCredit['id'] ?>">
  </form>
<?php endif; ?>

<h3>Offene Gutschriften</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Datum</th>
        <th>Pilot</th>
        <th>Beschreibung</th>
        <th>Notiz</th>
        <th>Betrag</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($credits)): ?>
        <tr><td colspan="6">Keine offenen Gutschriften vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($credits as $c): ?>
          <tr>
            <td><?= h(date('d.m.Y', strtotime((string)$c['credit_date']))) ?></td>
            <td><?= h($c['pilot_name']) ?></td>
            <td><?= h($c['description']) ?></td>
            <td><?= h((string)($c['notes'] ?? '')) ?></td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= number_format((float)$c['amount'], 2, '.', '') ?></td>
            <td>
              <a class="btn-small" href="index.php?page=credits&edit_credit_id=<?= (int)$c['id'] ?>">Bearbeiten</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<h3>Bereits verrechnete Gutschriften</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Datum</th>
        <th>Pilot</th>
        <th>Beschreibung</th>
        <th>Betrag</th>
        <th>Rechnung</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($settledCredits)): ?>
        <tr><td colspan="5">Keine verrechneten Gutschriften vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($settledCredits as $c): ?>
          <tr>
            <td><?= h(date('d.m.Y', strtotime((string)$c['credit_date']))) ?></td>
            <td><?= h($c['pilot_name']) ?></td>
            <td><?= h($c['description']) ?></td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= number_format((float)$c['amount'], 2, '.', '') ?></td>
            <td><?= h((string)$c['invoice_number']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
