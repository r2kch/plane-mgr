<h2>Flotte</h2>
<?php if (can('admin.access')): ?><p class="access-note">Zugang: <code>logbook.full</code> oder <code>logbook.pilot</code></p><?php endif; ?>
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

<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Immatrikulation</th>
        <th>Typ</th>
        <th>Stundentotal</th>
        <th>Landungen total</th>
        <th style="text-align: right;">Logbuch</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($aircraft ?? []) as $a): ?>
        <tr>
          <td><?= h((string)$a['immatriculation']) ?></td>
          <td><?= h((string)$a['type']) ?></td>
          <td><?= h($formatHobbsClock((float)($a['hours_total'] ?? 0))) ?></td>
          <td><?= (int)($a['landings_total'] ?? 0) ?></td>
          <td style="text-align: right;">
            <a class="btn-small" href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$a['id'] ?>">Logbuch</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
