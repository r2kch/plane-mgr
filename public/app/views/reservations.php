<h2>Reservierungen</h2>
<?php $today = date('Y-m-d'); ?>
<?php $nowTs = time(); ?>
<?php $isCaptureMode = !empty($completeReservation); ?>

<?php if (can('reservation.create') && !$isCaptureMode): ?>
  <?php
    $isEdit = !empty($editReservation);
    $formAction = $isEdit ? 'update' : 'create';
    $prefillAircraft = isset($prefillAircraftId) ? (int)$prefillAircraftId : 0;
    $selectedAircraftId = $isEdit ? (int)$editReservation['aircraft_id'] : $prefillAircraft;
    $selectedUserId = $isEdit ? (int)$editReservation['user_id'] : (int)current_user()['id'];
    $showPilotSelect = has_role('admin') || !has_role('pilot');
    $prefillStart = isset($prefillStartDate) ? (string)$prefillStartDate : '';
    $baseStartDate = $prefillStart !== '' ? $prefillStart : $today;
    $startTsForm = $isEdit ? strtotime((string)$editReservation['starts_at']) : strtotime($baseStartDate . ' 08:00:00');
    $endTsForm = $isEdit ? strtotime((string)$editReservation['ends_at']) : strtotime($baseStartDate . ' 17:00:00');
    $noteValue = $isEdit ? (string)$editReservation['notes'] : '';
  ?>
  <h3><?= $isEdit ? 'Reservierung bearbeiten' : 'Neue Reservierung' ?></h3>
  <form method="post" class="grid-form reservation-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= h($formAction) ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="reservation_id" value="<?= (int)$editReservation['id'] ?>">
    <?php endif; ?>

    <label class="reservation-aircraft <?= $showPilotSelect ? '' : 'reservation-aircraft-full' ?>">Flugzeug
      <select name="aircraft_id" required>
        <?php foreach ($aircraft as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === $selectedAircraftId ? 'selected' : '' ?>><?= h($a['immatriculation']) ?> (<?= h($a['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>

    <?php if ($showPilotSelect): ?>
      <label class="reservation-pilot">Pilot
        <select name="user_id" required>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $selectedUserId ? 'selected' : '' ?>><?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <label class="reservation-time reservation-start">Start
      <div class="time-controls">
        <input type="date" name="start_date" value="<?= h(date('Y-m-d', (int)$startTsForm)) ?>" required>
        <select name="start_hour" required aria-label="Start Stunde">
          <?php for ($h = 0; $h < 24; $h++): ?>
            <option value="<?= $h ?>" <?= $h === (int)date('H', (int)$startTsForm) ? 'selected' : '' ?>><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></option>
          <?php endfor; ?>
        </select>
        <span class="time-sep">:</span>
        <select name="start_minute" required aria-label="Start Minute">
          <?php foreach ([0, 15, 30, 45] as $m): ?>
            <option value="<?= $m ?>" <?= $m === (int)date('i', (int)$startTsForm) ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </label>
    <label class="reservation-time reservation-end">Ende
      <div class="time-controls">
        <input type="date" name="end_date" value="<?= h(date('Y-m-d', (int)$endTsForm)) ?>" required>
        <select name="end_hour" required aria-label="Ende Stunde">
          <?php for ($h = 0; $h < 24; $h++): ?>
            <option value="<?= $h ?>" <?= $h === (int)date('H', (int)$endTsForm) ? 'selected' : '' ?>><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></option>
          <?php endfor; ?>
        </select>
        <span class="time-sep">:</span>
        <select name="end_minute" required aria-label="Ende Minute">
          <?php foreach ([0, 15, 30, 45] as $m): ?>
            <option value="<?= $m ?>" <?= $m === (int)date('i', (int)$endTsForm) ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </label>
    <label class="reservation-notes">Notiz
      <input name="notes" maxlength="500" value="<?= h($noteValue) ?>">
    </label>
    <button type="submit" class="reservation-submit <?= $isEdit ? 'btn-small' : '' ?>"><?= $isEdit ? 'Änderungen speichern' : 'Reservieren' ?></button>
    <?php if ($isEdit): ?>
      <a class="btn-ghost btn-small" href="index.php?page=reservations&month=<?= h($month) ?>">Bearbeitung abbrechen</a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($isCaptureMode): ?>
  <h3>Durchführung erfassen</h3>
  <?php if (!empty($completeLastReservationFlight)): ?>
    <?php
      $lastHobsFrom = (float)$completeLastReservationFlight['hobbs_start'];
      $lastHobsTo = (float)$completeLastReservationFlight['hobbs_end'];
      $lastHobsFromHours = (int)floor($lastHobsFrom);
      $lastHobsFromMinutes = (int)round(($lastHobsFrom - $lastHobsFromHours) * 60);
      if ($lastHobsFromMinutes === 60) { $lastHobsFromHours++; $lastHobsFromMinutes = 0; }
      $lastHobsToHours = (int)floor($lastHobsTo);
      $lastHobsToMinutes = (int)round(($lastHobsTo - $lastHobsToHours) * 60);
      if ($lastHobsToMinutes === 60) { $lastHobsToHours++; $lastHobsToMinutes = 0; }
    ?>
    <div class="flash flash-success">
      Letzter Flug in dieser Reservierung:
      <?= h(date('d.m.Y H:i', strtotime((string)$completeLastReservationFlight['start_time']))) ?>
      bis
      <?= h(date('d.m.Y H:i', strtotime((string)$completeLastReservationFlight['landing_time']))) ?>,
      <?= h((string)$completeLastReservationFlight['pilot_name']) ?>,
      <?= h((string)$completeLastReservationFlight['from_airfield']) ?> → <?= h((string)$completeLastReservationFlight['to_airfield']) ?>,
      Hobs <?= h(sprintf('%d:%02d', $lastHobsFromHours, $lastHobsFromMinutes)) ?> → <?= h(sprintf('%d:%02d', $lastHobsToHours, $lastHobsToMinutes)) ?>
    </div>
  <?php endif; ?>
  <form method="post" class="capture-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="complete_save">
    <input type="hidden" name="reservation_id" value="<?= (int)$completeReservation['id'] ?>">

    <div class="capture-layout">
      <div id="flights-body" class="flight-cards">
        <div class="flight-card">
          <div class="flight-row flight-row-3">
            <label>Pilot
              <select name="flight_pilot_id[]" required>
                <?php foreach ($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)$completeReservation['user_id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Von
              <input name="flight_from[]" maxlength="10" placeholder="z.B. LSZH" value="<?= h($completeDefaultFromAirfield ?? '') ?>" required>
            </label>
            <label>Nach
              <input name="flight_to[]" maxlength="10" placeholder="z.B. LSZR" required>
            </label>
          </div>

          <div class="flight-row flight-row-2">
            <label>Uhrzeit von
              <input type="datetime-local" name="flight_start_time[]" step="60" required>
            </label>
            <label>Uhrzeit bis
              <input type="datetime-local" name="flight_landing_time[]" step="60" required>
            </label>
          </div>

          <div class="flight-row flight-row-2">
            <label>Hobs von
              <input name="flight_hobbs_start[]" placeholder="z.B. 93:12" pattern="^[0-9]+:[0-5][0-9]$" value="<?= h($completeDefaultHobsStart ?? '') ?>" required>
            </label>
            <label>Hobs bis
              <input name="flight_hobbs_end[]" placeholder="z.B. 94:01" pattern="^[0-9]+:[0-5][0-9]$" required>
            </label>
          </div>
        </div>
      </div>

      <div class="capture-actions">
        <button type="submit" class="btn-small" name="complete_mode" value="next">Speichern und nächsten Flug</button>
        <button type="submit" class="btn-small btn-danger-solid" name="complete_mode" value="finish">Abschluss</button>
        <a class="btn-ghost btn-small" href="index.php?page=reservations&month=<?= h($month) ?>">Abbrechen</a>
      </div>
    </div>
  </form>

  <script>
    (function () {
      function parseHobsToMinutes(value) {
        const text = String(value || '').trim();
        const match = text.match(/^(\d+):([0-5]\d)$/);
        if (!match) return null;
        return (parseInt(match[1], 10) * 60) + parseInt(match[2], 10);
      }

      function formatMinutesToHobs(totalMinutes) {
        if (!Number.isFinite(totalMinutes) || totalMinutes < 0) return '';
        const rounded = Math.round(totalMinutes);
        const hours = Math.floor(rounded / 60);
        const minutes = rounded % 60;
        return `${hours}:${String(minutes).padStart(2, '0')}`;
      }

      function autoFillHobsEnd(card) {
        if (!card) return;
        const startTimeInput = card.querySelector('input[name="flight_start_time[]"]');
        const landingTimeInput = card.querySelector('input[name="flight_landing_time[]"]');
        const hobsStartInput = card.querySelector('input[name="flight_hobbs_start[]"]');
        const hobsEndInput = card.querySelector('input[name="flight_hobbs_end[]"]');
        if (!startTimeInput || !landingTimeInput || !hobsStartInput || !hobsEndInput) return;

        const startDate = startTimeInput.value ? new Date(startTimeInput.value) : null;
        const landingDate = landingTimeInput.value ? new Date(landingTimeInput.value) : null;
        const hobsStartMinutes = parseHobsToMinutes(hobsStartInput.value);
        if (!startDate || !landingDate || Number.isNaN(startDate.getTime()) || Number.isNaN(landingDate.getTime()) || hobsStartMinutes === null) return;

        const diffMinutes = Math.round((landingDate.getTime() - startDate.getTime()) / 60000);
        if (diffMinutes <= 0) return;

        hobsEndInput.value = formatMinutesToHobs(hobsStartMinutes + diffMinutes);
      }

      function bindAutoFill(card) {
        const fields = card.querySelectorAll('input[name="flight_start_time[]"], input[name="flight_landing_time[]"], input[name="flight_hobbs_start[]"]');
        fields.forEach((field) => {
          field.addEventListener('change', () => autoFillHobsEnd(card));
          field.addEventListener('blur', () => autoFillHobsEnd(card));
        });
      }

      document.querySelectorAll('#flights-body .flight-card').forEach(bindAutoFill);
    }());
  </script>

<?php endif; ?>

<h3>Einträge</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Von</th><th>Bis</th><th>Flugzeug</th><th>Pilot</th><th>Notiz</th><th>Status</th><th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reservations as $r): ?>
        <?php $isMine = (int)$r['user_id'] === (int)current_user()['id']; ?>
        <?php $isFuture = strtotime($r['starts_at']) > $nowTs; ?>
        <?php $canComplete = has_role('admin') || (can('reservation.complete.own') && $isMine); ?>
        <tr>
          <td><?= h(date('d.m.Y H:i', strtotime($r['starts_at']))) ?></td>
          <td><?= h(date('d.m.Y H:i', strtotime($r['ends_at']))) ?></td>
          <td><?= h($r['immatriculation']) ?></td>
          <td><?= h($r['pilot_name']) ?></td>
          <td class="cell-wrap"><?= h((string)$r['notes']) ?></td>
          <td><span class="status-chip <?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
          <td>
            <?php if ($r['status'] === 'booked'): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Reservierung wirklich löschen?');">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                <?php if (($isMine || has_role('admin')) && $isFuture): ?>
                  <a class="btn-small" href="index.php?page=reservations&month=<?= h($month) ?>&edit_id=<?= (int)$r['id'] ?>">Bearbeiten</a>
                <?php endif; ?>
                <?php if ($canComplete): ?>
                  <a class="btn-small" href="index.php?page=reservations&month=<?= h($month) ?>&complete_id=<?= (int)$r['id'] ?>">Durchgeführt</a>
                <?php endif; ?>
                <?php if (($isMine || has_role('admin')) && $isFuture): ?>
                  <button name="action" value="delete" class="btn-ghost btn-small">Löschen</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
