<h2>Preisverwaltung pro Pilot & Flugzeug</h2>
<?php if (can('admin.access')): ?><p class="access-note">Zugang: <code>rates.manage</code></p><?php endif; ?>
<p>Wenn kein individueller Preis gesetzt ist, gilt automatisch der Basispreis des Flugzeugs.</p>
<?php
  $isEditRate = !empty($editRate);
  $selectedPilotId = $isEditRate ? (int)$editRate['user_id'] : 0;
  $selectedAircraftId = $isEditRate ? (int)$editRate['aircraft_id'] : 0;
  $editHourlyRate = $isEditRate ? number_format((float)$editRate['hourly_rate'], 2, '.', '') : '';
?>

<h3>Preis setzen oder aktualisieren</h3>
<form method="post" class="grid-form">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">

  <label>Pilot
    <select name="user_id" required>
      <?php foreach ($pilots as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $selectedPilotId ? 'selected' : '' ?>><?= h($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Flugzeug
    <select name="aircraft_id" required>
      <?php foreach ($aircraft as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === $selectedAircraftId ? 'selected' : '' ?>><?= h($a['immatriculation']) ?> (Basis: <?= number_format((float)$a['base_hourly_rate'], 2, '.', '') ?> CHF)</option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Stundenpreis CHF
    <input type="number" min="0" step="0.01" name="hourly_rate" value="<?= h($editHourlyRate) ?>" required>
  </label>

  <button type="submit">Preis speichern</button>
  <?php if ($isEditRate): ?>
    <a class="btn-ghost" href="index.php?page=rates">Bearbeitung abbrechen</a>
  <?php endif; ?>
</form>

<h3>Aktive Preiszuordnungen</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr><th>Pilot</th><th>Flugzeug</th><th>Basispreis</th><th>Individueller Preis</th><th>Aktion</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rates as $r): ?>
        <tr>
          <td><?= h($r['pilot_name']) ?></td>
          <td><?= h($r['immatriculation']) ?></td>
          <td><?= number_format((float)$r['base_hourly_rate'], 2, '.', '') ?> CHF</td>
          <td><strong><?= number_format((float)$r['hourly_rate'], 2, '.', '') ?> CHF</strong></td>
          <td>
            <form method="post" class="inline-form">
              <a class="btn-small" href="index.php?page=rates&edit_rate_id=<?= (int)$r['id'] ?>">Bearbeiten</a>
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="rate_id" value="<?= (int)$r['id'] ?>">
              <button class="btn-ghost btn-small">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
