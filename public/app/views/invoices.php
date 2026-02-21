<h2>Abrechnung</h2>
<div class="section-head-actions">
  <a class="btn-small btn-danger-solid" href="index.php?page=manual_flight">Flug händisch eintragen</a>
  <form method="post" class="inline-form" onsubmit="return confirm('Alle Rechnungen erstellen und versenden?');">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="generate_all_open">
    <button type="submit" class="btn-small btn-danger-solid">Alle Rechnungen erstellen</button>
  </form>
</div>

<h3>Offene Positionen</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Pilot</th>
        <th>Offene Stunden</th>
        <th>Offene Positionen</th>
        <th>Per Mail senden</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($unbilledPilotHours)): ?>
        <tr>
          <td colspan="5">Keine offenen Positionen vorhanden.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($unbilledPilotHours as $row): ?>
          <?php $isOpenPilot = (int)($openPilotId ?? 0) === (int)$row['pilot_user_id']; ?>
          <?php $pilotToggleHref = $isOpenPilot
            ? 'index.php?page=invoices'
            : 'index.php?page=invoices&open_pilot_id=' . (int)$row['pilot_user_id']; ?>
          <tr>
            <td><a href="<?= h($pilotToggleHref) ?>"><?= h($row['pilot_name']) ?></a></td>
            <td><?= number_format((float)$row['open_hours'], 2, '.', '') ?> h</td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= number_format((float)$row['open_positions'], 2, '.', '') ?></td>
            <td>
              <input type="checkbox" form="generate-invoice-<?= (int)$row['pilot_user_id'] ?>" name="send_by_mail" value="1" checked>
            </td>
            <td>
              <form method="post" class="inline-form" id="generate-invoice-<?= (int)$row['pilot_user_id'] ?>">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="generate_open_for_pilot">
                <input type="hidden" name="user_id" value="<?= (int)$row['pilot_user_id'] ?>">
                <button type="submit" class="btn-small">Rechnung erzeugen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (!empty($openPilotFlights)): ?>
  <h3>Offene Flüge: <?= h($openPilotName) ?></h3>
  <div class="table-wrap">
    <table class="entries-table">
      <thead>
        <tr>
          <th>Von (Zeit)</th>
          <th>Bis (Zeit)</th>
          <th>Flugzeug</th>
          <th>Von (Flugplatz)</th>
          <th>Nach (Flugplatz)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($openPilotFlights as $f): ?>
          <tr>
            <td><?= h(date('d.m.Y H:i', strtotime($f['start_time']))) ?></td>
            <td><?= h(date('d.m.Y H:i', strtotime($f['landing_time']))) ?></td>
            <td><?= h($f['immatriculation']) ?></td>
            <td>(<?= h(strtoupper((string)$f['from_airfield'])) ?>)</td>
            <td>(<?= h(strtoupper((string)$f['to_airfield'])) ?>)</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php if (!empty($openPilotPositions)): ?>
  <h3>Offene Positionen: <?= h($openPilotName) ?></h3>
  <div class="table-wrap">
    <table class="entries-table">
      <thead>
        <tr>
          <th>Datum</th>
          <th>Beschreibung</th>
          <th>Betrag</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($openPilotPositions as $p): ?>
          <tr>
            <td><?= h(date('d.m.Y', strtotime((string)$p['position_date']))) ?></td>
            <td><?= h((string)$p['description']) ?></td>
            <td><?= h((string)config('invoice.currency', 'CHF')) ?> <?= number_format((float)$p['amount'], 2, '.', '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h3>Rechnungen</h3>
<form method="get" class="form-row">
  <input type="hidden" name="page" value="invoices">
  <label>Status
    <select name="invoice_status">
      <option value="unpaid" <?= ($invoiceStatusFilter ?? 'unpaid') === 'unpaid' ? 'selected' : '' ?>>Nicht bezahlt</option>
      <option value="all" <?= ($invoiceStatusFilter ?? 'unpaid') === 'all' ? 'selected' : '' ?>>Alle</option>
      <option value="open" <?= ($invoiceStatusFilter ?? 'unpaid') === 'open' ? 'selected' : '' ?>>Offen</option>
      <option value="overdue" <?= ($invoiceStatusFilter ?? 'unpaid') === 'overdue' ? 'selected' : '' ?>>Überfällig</option>
      <option value="paid" <?= ($invoiceStatusFilter ?? 'unpaid') === 'paid' ? 'selected' : '' ?>>Bezahlt</option>
    </select>
  </label>
  <label>Suche
    <input name="invoice_q" value="<?= h($invoiceSearch ?? '') ?>" placeholder="Rechnungsnummer oder Kunde">
  </label>
  <button type="submit">Filtern</button>
</form>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr><th>Nr.</th><th>Kunde</th><th>Periode</th><th>Total</th><th>Status</th><th>Aktion</th></tr>
    </thead>
    <tbody>
      <?php if (empty($invoices)): ?>
        <tr><td colspan="6">Keine Rechnungen gefunden.</td></tr>
      <?php else: ?>
        <?php foreach ($invoices as $i): ?>
          <tr>
            <td><?= h($i['invoice_number']) ?></td>
            <td><?= h($i['customer_name']) ?></td>
            <td><?= h($i['period_from']) ?> - <?= h($i['period_to']) ?></td>
            <td><?= number_format((float)$i['total_amount'], 2, '.', '') ?> CHF</td>
            <td>
              <span class="status-chip <?= h($i['payment_status']) ?>"><?= h($i['payment_status']) ?></span>
            </td>
            <td>
              <div class="invoice-actions">
                <div class="invoice-actions-row">
                  <form method="post" class="inline-form invoice-status-form">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>">
                    <select name="payment_status">
                      <?php foreach (['open' => 'Offen', 'paid' => 'Bezahlt', 'overdue' => 'Überfällig'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $i['payment_status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn-small">Setzen</button>
                  </form>
                  <?php if (($i['payment_status'] ?? '') === 'overdue'): ?>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="send_reminder">
                      <input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>">
                      <button type="submit" class="btn-small btn-danger-solid">Zahlungserinnerung</button>
                    </form>
                  <?php endif; ?>
                </div>
                <div class="invoice-actions-row">
                  <a class="btn-small" href="index.php?page=invoice_html&id=<?= (int)$i['id'] ?>" target="_blank">Rechnung HTML</a>
                  <a class="btn-small" href="index.php?page=invoice_pdf&id=<?= (int)$i['id'] ?>" target="_blank">Rechnung PDF</a>
                  <form method="post" class="inline-form" onsubmit="return confirm('Rechnung wirklich stornieren? Stunden werden wieder unverrechnet.');">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel_invoice">
                    <input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>">
                    <button type="submit" class="btn-ghost btn-small">Stornieren</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
