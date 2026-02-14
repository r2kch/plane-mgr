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
  $statusLabel = static function (string $status): string {
    return match ($status) {
      'active' => 'Aktiv',
      'disabled' => 'Deaktiviert',
      'maintenance' => 'Maintenance',
      default => $status,
    };
  };
?>

<div class="section-head-actions">
  <a class="btn-small" href="index.php?page=aircraft&new=<?= ((int)($showNewAircraftForm ?? 0) === 1) ? '0' : '1' ?>">Neues Flugzeug</a>
</div>

<?php if ((int)($showNewAircraftForm ?? 0) === 1): ?>
  <h3>Neues Flugzeug anlegen</h3>
  <form method="post" class="grid-form aircraft-grid">
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
    <label>Start Hobbs
      <input name="start_hobbs" placeholder="z.B. 93:12" pattern="^[0-9]+:[0-5][0-9]$" value="0:00" required>
    </label>
    <label>Start Landings
      <input name="start_landings" type="number" min="1" step="1" value="1" required>
    </label>
    <label>Basispreis/Stunde
      <input name="base_hourly_rate" type="number" step="0.01" min="0" required>
    </label>
    <button type="submit">Speichern</button>
  </form>
<?php endif; ?>

<h3>Bestehende Flugzeuge</h3>
<div class="table-wrap" style="margin-bottom: 12px;">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Immatrikulation</th>
        <th>Typ</th>
        <th>Status</th>
        <th>Start Hobbs</th>
        <th>Start Landings</th>
        <th>Basispreis</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($aircraft)): ?>
        <tr><td colspan="7">Keine Flugzeuge vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($aircraft as $a): ?>
          <?php $openHref = 'index.php?page=aircraft&open_aircraft_id=' . (int)$a['id']; ?>
          <tr>
            <td><a href="<?= h($openHref) ?>"><?= h($a['immatriculation']) ?></a></td>
            <td><a href="<?= h($openHref) ?>"><?= h($a['type']) ?></a></td>
            <td><?= h($statusLabel((string)$a['status'])) ?></td>
            <td><?= h($formatHobbsClock((float)$a['start_hobbs'])) ?></td>
            <td><?= (int)$a['start_landings'] ?></td>
            <td><?= number_format((float)$a['base_hourly_rate'], 2, '.', '') ?> CHF</td>
            <td>
              <div class="inline-form">
                <a class="btn-small" href="<?= h($openHref) ?>">Bearbeiten</a>
                <a class="btn-small" href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$a['id'] ?>">Logbuch</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$openAircraft = null;
foreach ($aircraft as $candidate) {
  if ((int)$candidate['id'] === (int)($openAircraftId ?? 0)) {
    $openAircraft = $candidate;
    break;
  }
}
?>

<?php if ($openAircraft !== null): ?>
  <?php $deleteAircraftFormId = 'delete-aircraft-' . (int)$openAircraft['id']; ?>
  <h3>Flugzeug bearbeiten</h3>
  <form method="post" class="user-item">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$openAircraft['id'] ?>">

    <div class="grid-form aircraft-grid">
      <label>Immatrikulation
        <input name="immatriculation" value="<?= h($openAircraft['immatriculation']) ?>" required>
      </label>
      <label>Typ
        <input name="type" value="<?= h($openAircraft['type']) ?>" required>
      </label>
      <label>Status
        <select name="status">
          <option value="active" <?= (string)$openAircraft['status'] === 'active' ? 'selected' : '' ?>>Aktiv</option>
          <option value="disabled" <?= (string)$openAircraft['status'] === 'disabled' ? 'selected' : '' ?>>Deaktiviert</option>
          <option value="maintenance" <?= (string)$openAircraft['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
        </select>
      </label>
      <label>Start Hobbs
        <input name="start_hobbs" pattern="^[0-9]+:[0-5][0-9]$" value="<?= h($formatHobbsClock((float)$openAircraft['start_hobbs'])) ?>" required>
      </label>
      <label>Start Landings
        <input name="start_landings" type="number" min="1" step="1" value="<?= (int)$openAircraft['start_landings'] ?>" required>
      </label>
      <label>Basispreis/Stunde
        <input name="base_hourly_rate" type="number" step="0.01" min="0" value="<?= h((string)$openAircraft['base_hourly_rate']) ?>" required>
      </label>
    </div>

    <div class="inline-form user-meta">
      <span>ID: <?= (int)$openAircraft['id'] ?></span>
      <button type="submit" class="btn-small">Speichern</button>
      <button type="submit" form="<?= h($deleteAircraftFormId) ?>" class="btn-ghost btn-small" onclick="return confirm('Flugzeug wirklich löschen?');">Löschen</button>
      <a class="btn-ghost btn-small" href="index.php?page=aircraft">Bearbeitung abbrechen</a>
    </div>
  </form>
  <form method="post" id="<?= h($deleteAircraftFormId) ?>" class="user-delete-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$openAircraft['id'] ?>">
  </form>
<?php endif; ?>
