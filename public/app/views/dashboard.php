<h2>Dashboard</h2>
<?php
  $showReservationsModule = (bool)($showReservationsModule ?? true);
  $showBillingModule = (bool)($showBillingModule ?? true);
  $canViewCalendar = (bool)($canViewCalendar ?? false);
  $isDayView = (bool)($isDayView ?? false);
  $isMobileDevice = (bool)($isMobileDevice ?? false);
  $invoicesOpen = (int)$counts['invoices_open'];
  $invoiceTarget = 'index.php?page=my_invoices';
?>
<?php if ($showBillingModule): ?>
  <div class="cards">
    <article class="card">
      <h3>Offene Rechnungen</h3>
      <p class="stat stat-center <?= $invoicesOpen > 1 ? 'stat-danger' : '' ?>">
        <?php if ($invoicesOpen > 0): ?>
          <a class="stat-link" href="<?= h($invoiceTarget) ?>"><?= $invoicesOpen ?></a>
        <?php else: ?>
          <?= $invoicesOpen ?>
        <?php endif; ?>
      </p>
    </article>
  </div>
<?php endif; ?>

<?php if ($showReservationsModule): ?>
  <?php if ($canViewCalendar): ?>
    <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
      <h3>Kalender</h3>
      <?php if (!$isMobileDevice): ?>
        <?php
          $toggleView = $isDayView ? 'week' : 'day';
          $toggleLabel = $isDayView ? 'Wochenansicht' : 'Tagesansicht';
          $toggleHref = 'index.php?page=dashboard&view=' . $toggleView . '&calendar_start=' . urlencode((string)($calendarStartDate ?? date('Y-m-d')));
        ?>
        <a class="btn-small" href="<?= h($toggleHref) ?>"><?= h($toggleLabel) ?></a>
      <?php endif; ?>
    </div>
    <form method="get" class="form-row dashboard-calendar-filter" id="dashboard-calendar-form">
      <input type="hidden" name="page" value="dashboard">
      <label>Startdatum
        <input type="date" name="calendar_start" id="dashboard-calendar-start" value="<?= h($calendarStartDate ?? date('Y-m-d')) ?>">
      </label>
      <?php if (!$isMobileDevice): ?>
        <input type="hidden" name="view" value="<?= $isDayView ? 'day' : 'week' ?>">
      <?php endif; ?>
      <button type="submit">Anzeigen</button>
    </form>

<?php
    $calendarDaysCount = max(1, (int)($calendarDaysCount ?? 7));
    $calendarStartTs = strtotime(($calendarStartDate ?? date('Y-m-d')) . ' 00:00:00');
    $calendarEndTs = strtotime(($calendarEndDate ?? date('Y-m-d')) . ' 23:59:59');
    $calendarDuration = max(1, (int)(($calendarEndTs - $calendarStartTs) / 60));
    $calendarDays = [];
    for ($d = 0; $d < $calendarDaysCount; $d++) {
        $dayTs = strtotime(($calendarStartDate ?? date('Y-m-d')) . ' +' . $d . ' days');
        $calendarDays[] = [
            'date' => date('Y-m-d', $dayTs),
            'label' => date('d.m', $dayTs),
            'month' => date('Y-m', $dayTs),
        ];
    }
?>
    <?php if ($isDayView): ?>
      <?php
        $dayStartHour = 6;
        $dayEndHour = 22;
        $dayHours = $dayEndHour - $dayStartHour;
        $dayMinutes = $dayHours * 60;
        $viewStartTs = strtotime(($calendarStartDate ?? date('Y-m-d')) . ' ' . str_pad((string)$dayStartHour, 2, '0', STR_PAD_LEFT) . ':00:00');
        $viewEndTs = strtotime(($calendarStartDate ?? date('Y-m-d')) . ' ' . str_pad((string)$dayEndHour, 2, '0', STR_PAD_LEFT) . ':00:00');
        $nightLeftWidth = null;
        $nightRightLeft = null;
        $nightRightWidth = null;
        $sunriseStartOffsetMinutes = isset($sunriseStartOffsetMinutes) ? (int)$sunriseStartOffsetMinutes : null;
        $sunsetEndOffsetMinutes = isset($sunsetEndOffsetMinutes) ? (int)$sunsetEndOffsetMinutes : null;
        if ($sunriseStartOffsetMinutes !== null && $sunsetEndOffsetMinutes !== null) {
            $dayStartMin = $dayStartHour * 60;
            $dayEndMin = $dayEndHour * 60;
            if ($sunriseStartOffsetMinutes > $dayStartMin) {
                $leftEnd = min($sunriseStartOffsetMinutes, $dayEndMin);
                $nightLeftWidth = max(0, ($leftEnd - $dayStartMin) / $dayMinutes * 100);
            }
            if ($sunsetEndOffsetMinutes < $dayEndMin) {
                $rightStart = max($sunsetEndOffsetMinutes, $dayStartMin);
                $nightRightLeft = max(0, ($rightStart - $dayStartMin) / $dayMinutes * 100);
                $nightRightWidth = max(0, ($dayEndMin - $rightStart) / $dayMinutes * 100);
            }
        }
      ?>
      <div class="table-wrap">
        <div class="calendar-dev-grid" style="--day-hours: <?= (int)$dayHours ?>;">
          <div class="calendar-dev-cell calendar-dev-head">Immatrikulation</div>
          <?php for ($h = $dayStartHour; $h < $dayEndHour; $h++): ?>
            <div class="calendar-dev-cell calendar-dev-head"><?= h(str_pad((string)$h, 2, '0', STR_PAD_LEFT)) ?></div>
          <?php endfor; ?>

          <?php foreach (($calendarAircraft ?? []) as $aircraftRow): ?>
            <?php
              $aircraftId = (int)$aircraftRow['id'];
              $canLink = (bool)($aircraftRow['can_link'] ?? true);
              $forceLinks = false;
              $aircraftLink = sprintf(
                  'index.php?page=reservations&month=%s&prefill_aircraft_id=%d&prefill_start_date=%s',
                  urlencode(date('Y-m', strtotime((string)($calendarStartDate ?? date('Y-m-d'))))),
                  $aircraftId,
                  urlencode((string)($calendarStartDate ?? date('Y-m-d')))
              );
              $aircraftBookings = $calendarReservationsByAircraft[$aircraftId] ?? [];
            ?>
            <div class="calendar-dev-cell">
              <?php if ($canLink || $forceLinks): ?>
                <a href="<?= h($aircraftLink) ?>"><?= h((string)$aircraftRow['immatriculation']) ?></a>
              <?php else: ?>
                <span><?= h((string)$aircraftRow['immatriculation']) ?></span>
              <?php endif; ?>
            </div>
            <div class="calendar-dev-lane" style="grid-column: span <?= (int)$dayHours ?>;">
              <?php if ($nightLeftWidth !== null): ?>
                <div style="position:absolute; left:0; top:0; bottom:0; width: <?= h(number_format($nightLeftWidth, 4, '.', '')) ?>%; background: rgba(120, 130, 140, 0.12);"></div>
              <?php endif; ?>
              <?php if ($nightRightLeft !== null && $nightRightWidth !== null): ?>
                <div style="position:absolute; left: <?= h(number_format($nightRightLeft, 4, '.', '')) ?>%; top:0; bottom:0; width: <?= h(number_format($nightRightWidth, 4, '.', '')) ?>%; background: rgba(120, 130, 140, 0.12);"></div>
              <?php endif; ?>
              <div class="calendar-dev-clickgrid" style="grid-template-columns: repeat(<?= (int)$dayHours ?>, minmax(0, 1fr));">
                <?php for ($h = $dayStartHour; $h < $dayEndHour; $h++): ?>
                  <?php
                    $hourLabel = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
                    $dayTarget = sprintf(
                        'index.php?page=reservations&month=%s&prefill_aircraft_id=%d&prefill_start_date=%s&prefill_start_time=%s&prefill_duration=60',
                        urlencode(date('Y-m', strtotime((string)($calendarStartDate ?? date('Y-m-d'))))),
                        $aircraftId,
                        urlencode((string)($calendarStartDate ?? date('Y-m-d'))),
                        urlencode($hourLabel . ':00')
                    );
                  ?>
                  <?php if ($canLink || $forceLinks): ?>
                    <a class="calendar-dev-slot" href="<?= h($dayTarget) ?>" aria-label="Reservierung für <?= h((string)$aircraftRow['immatriculation']) ?> um <?= h($hourLabel) ?>:00"></a>
                  <?php else: ?>
                    <span class="calendar-dev-slot" aria-hidden="true"></span>
                  <?php endif; ?>
                <?php endfor; ?>
              </div>
              <?php foreach ($aircraftBookings as $booking): ?>
                <?php
                  $startTs = strtotime((string)$booking['starts_at']);
                  $endTs = strtotime((string)$booking['ends_at']);
                  if ($startTs === false || $endTs === false) {
                      continue;
                  }
                  $clampedStart = max($startTs, $viewStartTs);
                  $clampedEnd = min($endTs, $viewEndTs);
                  if ($clampedEnd <= $clampedStart) {
                      continue;
                  }
                  $leftPercent = (($clampedStart - $viewStartTs) / ($dayMinutes * 60)) * 100;
                  $widthPercent = ((($clampedEnd - $clampedStart) / ($dayMinutes * 60)) * 100);
                  $pilotName = trim((string)$booking['pilot_name']);
                  $noteText = trim((string)($booking['notes'] ?? ''));
                  $barText = trim($pilotName . ($noteText !== '' ? ' - ' . $noteText : ''));
                  $tooltipText = 'Pilot: ' . $pilotName;
                  $tooltipText .= ' | Start: ' . date('d.m.Y H:i', $startTs);
                  $tooltipText .= ' | Ende: ' . date('d.m.Y H:i', $endTs);
                  if ($noteText !== '') {
                      $tooltipText .= ' | Notiz: ' . $noteText;
                  }
                ?>
                <div class="timeline-bar" style="left: <?= h(number_format($leftPercent, 4, '.', '')) ?>%; width: <?= h(number_format($widthPercent, 4, '.', '')) ?>%;" title="<?= h($tooltipText) ?>" data-tooltip="<?= h($tooltipText) ?>">
                  <span><?= h($barText) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <div class="calendar-dev-grid" style="--day-hours: <?= (int)$calendarDaysCount ?>;">
          <div class="calendar-dev-cell calendar-dev-head">Immatrikulation</div>
          <?php foreach ($calendarDays as $day): ?>
            <div class="calendar-dev-cell calendar-dev-head"><?= h($day['label']) ?></div>
          <?php endforeach; ?>

          <?php foreach (($calendarAircraft ?? []) as $aircraftRow): ?>
            <?php
              $aircraftId = (int)$aircraftRow['id'];
              $canLink = (bool)($aircraftRow['can_link'] ?? true);
              $forceLinks = false;
              $aircraftBookings = $calendarReservationsByAircraft[$aircraftId] ?? [];
              $aircraftLink = sprintf(
                  'index.php?page=reservations&month=%s&prefill_aircraft_id=%d&prefill_start_date=%s',
                  urlencode(date('Y-m', strtotime((string)($calendarStartDate ?? date('Y-m-d'))))),
                  $aircraftId,
                  urlencode((string)($calendarStartDate ?? date('Y-m-d')))
              );
            ?>
            <div class="calendar-dev-cell">
              <?php if ($canLink || $forceLinks): ?>
                <a href="<?= h($aircraftLink) ?>"><?= h((string)$aircraftRow['immatriculation']) ?></a>
              <?php else: ?>
                <span><?= h((string)$aircraftRow['immatriculation']) ?></span>
              <?php endif; ?>
            </div>
            <div class="calendar-dev-lane" style="grid-column: span <?= (int)$calendarDaysCount ?>;">
              <div class="calendar-dev-clickgrid" style="grid-template-columns: repeat(<?= (int)$calendarDaysCount ?>, minmax(0, 1fr));">
                <?php foreach ($calendarDays as $day): ?>
                  <?php
                    $dayTarget = sprintf(
                        'index.php?page=reservations&month=%s&prefill_aircraft_id=%d&prefill_start_date=%s',
                        urlencode((string)$day['month']),
                        $aircraftId,
                        urlencode((string)$day['date'])
                    );
                  ?>
                  <?php if ($canLink || $forceLinks): ?>
                    <a class="calendar-dev-slot" href="<?= h($dayTarget) ?>" aria-label="Reservierung für <?= h((string)$aircraftRow['immatriculation']) ?> am <?= h((string)$day['label']) ?>"></a>
                  <?php else: ?>
                    <span class="calendar-dev-slot" aria-hidden="true"></span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php foreach ($aircraftBookings as $booking): ?>
                <?php
                  $startTs = strtotime((string)$booking['starts_at']);
                  $endTs = strtotime((string)$booking['ends_at']);
                  if ($startTs === false || $endTs === false) {
                      continue;
                  }
                  $clampedStart = max($startTs, $calendarStartTs);
                  $clampedEnd = min($endTs, $calendarEndTs);
                  if ($clampedEnd <= $clampedStart) {
                      continue;
                  }
                  $leftPercent = (($clampedStart - $calendarStartTs) / 60 / $calendarDuration) * 100;
                  $widthPercent = ((($clampedEnd - $clampedStart) / 60) / $calendarDuration) * 100;
                  $pilotName = trim((string)$booking['pilot_name']);
                  $noteText = trim((string)($booking['notes'] ?? ''));
                  $barText = trim($pilotName . ($noteText !== '' ? ' - ' . $noteText : ''));
                  $tooltipText = 'Pilot: ' . $pilotName;
                  $tooltipText .= ' | Start: ' . date('d.m.Y H:i', $startTs);
                  $tooltipText .= ' | Ende: ' . date('d.m.Y H:i', $endTs);
                  if ($noteText !== '') {
                      $tooltipText .= ' | Notiz: ' . $noteText;
                  }
                ?>
                <div class="timeline-bar" style="left: <?= h(number_format($leftPercent, 4, '.', '')) ?>%; width: <?= h(number_format($widthPercent, 4, '.', '')) ?>%;" title="<?= h($tooltipText) ?>" data-tooltip="<?= h($tooltipText) ?>">
                  <span><?= h($barText) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($canViewCalendar): ?>
    <h3>Zukünftige Reservierungen</h3>
    <div class="table-wrap">
      <table class="entries-table">
        <thead>
          <tr>
            <th>Von</th>
            <th>Bis</th>
            <th>Immatrikulation</th>
            <th>Pilot</th>
            <th>Notiz</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcomingReservations as $r): ?>
            <?php
              $isMine = (int)$r['user_id'] === (int)current_user()['id'];
              $canComplete = has_role('admin') || (can('reservation.complete.own') && $isMine);
              $month = date('Y-m', strtotime($r['starts_at']));
            ?>
            <tr>
              <td><?= h(date('d.m.Y H:i', strtotime($r['starts_at']))) ?></td>
              <td><?= h(date('d.m.Y H:i', strtotime($r['ends_at']))) ?></td>
              <td><?= h((string)$r['immatriculation']) ?></td>
              <td><?= h($r['pilot_name']) ?></td>
              <td class="cell-wrap"><?= h((string)$r['notes']) ?></td>
              <td>
                <?php if ($isMine || has_role('admin') || $canComplete): ?>
                  <div class="inline-form">
                    <a class="btn-small" href="index.php?page=reservations&month=<?= h($month) ?>&edit_id=<?= (int)$r['id'] ?>">Bearbeiten</a>
                    <?php if ($canComplete): ?>
                      <a class="btn-small" href="index.php?page=reservations&month=<?= h($month) ?>&complete_id=<?= (int)$r['id'] ?>">Durchgeführt</a>
                    <?php endif; ?>
                    <form method="post" action="index.php?page=reservations&month=<?= h($month) ?>" class="inline-form" onsubmit="return confirm('Reservierung wirklich löschen?');">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn-ghost btn-small" type="submit">Löschen</button>
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!empty($latestNews)): ?>
    <h3>Neueste News</h3>
    <article class="card">
      <h4><?= h((string)$latestNews['title']) ?></h4>
      <div class="muted" style="margin-bottom:8px;">
        <?= h(date('d.m.Y H:i', strtotime((string)$latestNews['created_at']))) ?>
        <?php if (!empty($latestNews['author_name'])): ?>
          · <?= h((string)$latestNews['author_name']) ?>
        <?php endif; ?>
      </div>
      <div class="cell-wrap"><?= $latestNews['body_html'] ?></div>
      <div style="margin-top:8px;">
        <a class="btn-small" href="index.php?page=news">Alle News</a>
      </div>
    </article>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$showBillingModule && !$showReservationsModule): ?>
  <p>Keine Dashboard-Module aktiv.</p>
<?php endif; ?>

<script>
  (function () {
    const form = document.getElementById('dashboard-calendar-form');
    const input = document.getElementById('dashboard-calendar-start');
    if (!form || !input) return;

    input.addEventListener('change', function () {
      form.submit();
    });
  }());
</script>
