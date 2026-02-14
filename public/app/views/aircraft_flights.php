<h2>Durchgeführte Flüge: <?= h($aircraft['immatriculation']) ?> (<?= h($aircraft['type']) ?>)</h2>
<p><a href="index.php?page=aircraft">Zurück zu Flugzeuge</a></p>

<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Start</th>
        <th>Landung</th>
        <th>Pilot</th>
        <th>Von</th>
        <th>Nach</th>
        <th>Landungen</th>
        <th>Hobs von</th>
        <th>Hobs bis</th>
        <th>Hobs h</th>
        <th>Reservierung</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($flights as $f): ?>
        <?php if (!empty($editFlightId) && (int)$editFlightId === (int)$f['id']): ?>
          <tr>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" type="datetime-local" name="start_time" step="60" value="<?= h(date('Y-m-d\TH:i', strtotime($f['start_time']))) ?>" required></td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" type="datetime-local" name="landing_time" step="60" value="<?= h(date('Y-m-d\TH:i', strtotime($f['landing_time']))) ?>" required></td>
            <td>
              <select form="edit-flight-<?= (int)$f['id'] ?>" name="pilot_user_id" required>
                <?php foreach ($pilots as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$f['pilot_user_id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" name="from_airfield" maxlength="10" value="<?= h($f['from_airfield']) ?>" required></td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" name="to_airfield" maxlength="10" value="<?= h($f['to_airfield']) ?>" required></td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" type="number" name="landings_count" min="1" step="1" value="<?= (int)($f['landings_count'] ?? 1) ?>" required></td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" name="hobbs_start" pattern="^[0-9]+:[0-5][0-9]$" value="<?= h($f['hobbs_start_clock']) ?>" required></td>
            <td><input form="edit-flight-<?= (int)$f['id'] ?>" name="hobbs_end" pattern="^[0-9]+:[0-5][0-9]$" value="<?= h($f['hobbs_end_clock']) ?>" required></td>
            <td><?= number_format((float)$f['hobbs_hours'], 2, '.', '') ?></td>
            <td>#<?= (int)$f['reservation_id'] ?></td>
            <td>
              <div class="inline-form">
                <form method="post" id="edit-flight-<?= (int)$f['id'] ?>">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_flight">
                  <input type="hidden" name="flight_id" value="<?= (int)$f['id'] ?>">
                  <button class="btn-small" type="submit">Speichern</button>
                </form>
                <a class="btn-ghost btn-small" href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$aircraft['id'] ?>">Abbrechen</a>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td><?= h(date('d.m.Y H:i', strtotime($f['start_time']))) ?></td>
            <td><?= h(date('d.m.Y H:i', strtotime($f['landing_time']))) ?></td>
            <td><?= h($f['pilot_name']) ?></td>
            <td><?= h($f['from_airfield']) ?></td>
            <td><?= h($f['to_airfield']) ?></td>
            <td><?= (int)($f['landings_count'] ?? 1) ?></td>
            <td><?= h($f['hobbs_start_clock']) ?></td>
            <td><?= h($f['hobbs_end_clock']) ?></td>
            <td><?= number_format((float)$f['hobbs_hours'], 2, '.', '') ?></td>
            <td>#<?= (int)$f['reservation_id'] ?></td>
            <td>
              <div class="inline-form">
                <a class="btn-small" href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$aircraft['id'] ?>&edit_id=<?= (int)$f['id'] ?>">Bearbeiten</a>
                <form method="post" class="inline-form" onsubmit="return confirm('Eintrag wirklich löschen?');">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_flight">
                  <input type="hidden" name="flight_id" value="<?= (int)$f['id'] ?>">
                  <button type="submit" class="btn-ghost btn-small">Löschen</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
