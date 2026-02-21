<h2>Flüge</h2>
<p>Logbuch pro Flugzeug öffnen und Flüge als verrechenbar oder nicht verrechenbar markieren.</p>
<div class="section-head-actions">
  <a class="btn-small btn-danger-solid" href="index.php?page=manual_flight">Flug händisch eintragen</a>
</div>

<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Immatrikulation</th>
        <th>Typ</th>
        <th>Status</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($aircraft)): ?>
        <tr><td colspan="4">Keine Flugzeuge vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($aircraft as $a): ?>
          <?php
          $statusLabel = match ((string)$a['status']) {
            'active' => 'Aktiv',
            'disabled' => 'Deaktiviert',
            'maintenance' => 'Maintenance',
            default => (string)$a['status'],
          };
          ?>
          <tr>
            <td><?= h($a['immatriculation']) ?></td>
            <td><?= h($a['type']) ?></td>
            <td><?= h($statusLabel) ?></td>
            <td>
              <a class="btn-small" href="index.php?page=aircraft_flights&aircraft_id=<?= (int)$a['id'] ?>">Logbuch</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
