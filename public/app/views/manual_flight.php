<h2>Reservierungen</h2>
<h3>Flug erfassen</h3>

<form method="post" class="capture-form">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

  <div class="capture-layout">
    <div class="flight-cards">
      <div class="flight-card">
        <div class="flight-row flight-row-3">
          <label>Pilot
            <select name="pilot_user_id" required>
              <?php foreach ($pilots as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Flugzeug
            <select name="aircraft_id" id="manual-aircraft" required>
              <?php foreach ($aircraft as $a): ?>
                <?php
                  $defaults = $manualDefaultsByAircraft[(int)$a['id']] ?? ['hobbs_start' => '', 'from_airfield' => ''];
                ?>
                <option
                  value="<?= (int)$a['id'] ?>"
                  data-default-hobbs-start="<?= h((string)$defaults['hobbs_start']) ?>"
                  data-default-from-airfield="<?= h((string)$defaults['from_airfield']) ?>"
                ><?= h($a['immatriculation']) ?> (<?= h($a['type']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notiz
            <input name="notes" maxlength="500" placeholder="Optional">
          </label>
        </div>

        <div class="flight-row flight-row-3">
          <label>Von
            <input id="manual-from-airfield" name="from_airfield" maxlength="10" placeholder="z.B. LSZH" required>
          </label>
          <label>Nach
            <input name="to_airfield" maxlength="10" placeholder="z.B. LSZR" required>
          </label>
          <label>Anzahl Landungen
            <input type="number" name="landings_count" min="1" step="1" value="1" required>
          </label>
        </div>

        <div class="flight-row flight-row-2">
          <label>Uhrzeit von
            <input type="datetime-local" name="start_time" step="60" required>
          </label>
          <label>Uhrzeit bis
            <input type="datetime-local" name="landing_time" step="60" required>
          </label>
        </div>

        <div class="flight-row flight-row-2">
          <label>Hobs von
            <input id="manual-hobbs-start" name="hobbs_start" placeholder="z.B. 93:12" pattern="^[0-9]+:[0-5][0-9]$" required>
          </label>
          <label>Hobs bis
            <input name="hobbs_end" placeholder="z.B. 94:01" pattern="^[0-9]+:[0-5][0-9]$" required>
          </label>
        </div>
      </div>
    </div>

    <div class="capture-actions">
      <button type="submit" class="btn-small btn-danger-solid">Speichern und abrechenbar erfassen</button>
      <a class="btn-ghost btn-small" href="index.php?page=invoices">Abbrechen</a>
    </div>
  </div>
</form>

<script>
  (function () {
    function syncDefaultsForAircraft() {
      const aircraftSelect = document.getElementById('manual-aircraft');
      const fromInput = document.getElementById('manual-from-airfield');
      const hobsStartInput = document.getElementById('manual-hobbs-start');
      if (!aircraftSelect || !fromInput || !hobsStartInput) return;

      const option = aircraftSelect.options[aircraftSelect.selectedIndex];
      if (!option) return;

      fromInput.value = option.dataset.defaultFromAirfield || '';
      hobsStartInput.value = option.dataset.defaultHobbsStart || '';
    }

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

    function autoFillHobsEnd() {
      const startTimeInput = document.querySelector('input[name="start_time"]');
      const landingTimeInput = document.querySelector('input[name="landing_time"]');
      const hobsStartInput = document.querySelector('input[name="hobbs_start"]');
      const hobsEndInput = document.querySelector('input[name="hobbs_end"]');
      if (!startTimeInput || !landingTimeInput || !hobsStartInput || !hobsEndInput) return;

      const startDate = startTimeInput.value ? new Date(startTimeInput.value) : null;
      const landingDate = landingTimeInput.value ? new Date(landingTimeInput.value) : null;
      const hobsStartMinutes = parseHobsToMinutes(hobsStartInput.value);
      if (!startDate || !landingDate || Number.isNaN(startDate.getTime()) || Number.isNaN(landingDate.getTime()) || hobsStartMinutes === null) return;

      const diffMinutes = Math.round((landingDate.getTime() - startDate.getTime()) / 60000);
      if (diffMinutes <= 0) return;

      hobsEndInput.value = formatMinutesToHobs(hobsStartMinutes + diffMinutes);
    }

    ['start_time', 'landing_time', 'hobbs_start'].forEach((fieldName) => {
      const field = document.querySelector(`input[name="${fieldName}"]`);
      if (!field) return;
      field.addEventListener('change', autoFillHobsEnd);
      field.addEventListener('blur', autoFillHobsEnd);
    });

    const aircraftSelect = document.getElementById('manual-aircraft');
    if (aircraftSelect) {
      aircraftSelect.addEventListener('change', function () {
        syncDefaultsForAircraft();
        autoFillHobsEnd();
      });
    }

    syncDefaultsForAircraft();
    autoFillHobsEnd();
  }());
</script>
