<h2>Flugzeuge</h2>

<form method="post" class="grid-form">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label>Immatrikulation
    <input name="immatriculation" required>
  </label>
  <label>Typ
    <input name="type" required>
  </label>
  <label>Status
    <select name="status">
      <option value="active">Aktiv</option>
      <option value="disabled">Deaktiviert</option>
      <option value="maintenance">Maintenance</option>
    </select>
  </label>
  <label>Basispreis/Stunde
    <input name="base_hourly_rate" type="number" step="0.01" min="0" required>
  </label>
  <button type="submit">Speichern</button>
</form>

<div class="table-wrap">
  <table class="entries-table">
    <thead><tr><th>Immatrikulation</th><th>Typ</th><th>Status</th><th>Basispreis</th><th>Aktion</th></tr></thead>
    <tbody>
    <?php foreach ($aircraft as $a): ?>
      <?php $formId = 'aircraft-row-' . (int)$a['id']; ?>
      <tr>
        <td><a href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$a['id'] ?>"><?= h($a['immatriculation']) ?></a></td>
        <td><?= h($a['type']) ?></td>
        <td>
          <select name="status" form="<?= h($formId) ?>" class="btn-small">
            <option value="active" <?= $a['status'] === 'active' ? 'selected' : '' ?>>Aktiv</option>
            <option value="disabled" <?= $a['status'] === 'disabled' ? 'selected' : '' ?>>Deaktiviert</option>
            <option value="maintenance" <?= $a['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
          </select>
        </td>
        <td>
          <input name="base_hourly_rate" type="number" step="0.01" min="0" value="<?= h((string)$a['base_hourly_rate']) ?>" required form="<?= h($formId) ?>" class="btn-small">
        </td>
        <td>
          <form method="post" id="<?= h($formId) ?>" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <input type="hidden" name="immatriculation" value="<?= h($a['immatriculation']) ?>">
            <input type="hidden" name="type" value="<?= h($a['type']) ?>">
            <button type="submit" class="btn-small">Speichern</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
