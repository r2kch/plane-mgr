<h2>Flugzeuge</h2>
<?php
  $formatHobbsClock = static function (float $value): string {
    $hours = (int)floor($value);
    $minutes = (int)round(($value - $hours) * 60);
    if ($minutes === 60) {
      $hours++;
      $minutes = 0;
    }
    return sprintf('%d:%02d', $hours, $minutes);
  };
?>

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
  <label>Start HOBBS
    <input name="start_hobbs" placeholder="z.B. 93:12" pattern="^[0-9]+:[0-5][0-9]$" value="0:00" required>
  </label>
  <label>Start Landing
    <input name="start_landings" type="number" min="1" step="1" value="1" required>
  </label>
  <label>Basispreis/Stunde
    <input name="base_hourly_rate" type="number" step="0.01" min="0" required>
  </label>
  <button type="submit">Speichern</button>
</form>

<div class="table-wrap">
  <table class="entries-table">
    <thead><tr><th>Immatrikulation</th><th>Typ</th><th>Status</th><th>Start HOBBS</th><th>Start Landing</th><th>Basispreis</th><th>Aktion</th></tr></thead>
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
          <input name="start_hobbs" pattern="^[0-9]+:[0-5][0-9]$" value="<?= h($formatHobbsClock((float)$a['start_hobbs'])) ?>" required form="<?= h($formId) ?>" class="btn-small">
        </td>
        <td>
          <input name="start_landings" type="number" min="1" step="1" value="<?= (int)$a['start_landings'] ?>" required form="<?= h($formId) ?>" class="btn-small">
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
