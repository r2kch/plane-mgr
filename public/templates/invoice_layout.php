<?php
declare(strict_types=1);

$currency = (string)($invoiceMeta['currency'] ?? 'CHF');
$flightsSubtotal = 0.0;
foreach ($items as $item) {
    $flightsSubtotal += (float)$item['line_total'];
}
$flightsSubtotal = round($flightsSubtotal, 2);
$creditsSubtotal = 0.0;
foreach (($credits ?? []) as $credit) {
    $creditsSubtotal += (float)$credit['amount'];
}
$creditsSubtotal = round($creditsSubtotal, 2);

$summaryFlights = round((float)($summary['flights_subtotal'] ?? $flightsSubtotal), 2);
$summaryCredits = round((float)($summary['credits_total'] ?? $creditsSubtotal), 2);
$summaryVat = round((float)($summary['vat_amount'] ?? 0), 2);
if ($summaryVat === 0.0 && !empty($vat['enabled'])) {
    $summaryVat = round(($summaryFlights - $summaryCredits) * ((float)$vat['rate_percent'] / 100), 2);
}
$summaryTotal = round((float)($summary['total_amount'] ?? ($summaryFlights - $summaryCredits + $summaryVat)), 2);
$isPdf = (($renderMode ?? 'html') === 'pdf');
?>
<!doctype html>
<html lang="de-CH">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h((string)$invoiceMeta['title']) ?> <?= h((string)$invoice['invoice_number']) ?></title>
  <style>
    @page { size: A4; margin: <?= $isPdf ? '9mm' : '14mm' ?>; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Manrope", "Segoe UI", sans-serif;
      color: #172034;
      background: #fff;
      line-height: 1.35;
    }
    .invoice-page {
      width: 100%;
      max-width: 190mm;
      margin: 0 auto;
      border: 1px solid #d9e0ea;
      border-radius: 12px;
      padding: 12mm;
    }
    .header {
      display: table;
      width: 100%;
      table-layout: fixed;
      border-collapse: separate;
      border-spacing: 10mm 0;
      margin: 0 -10mm 10mm -10mm;
    }
    .header-col {
      display: table-cell;
      width: 50%;
      vertical-align: top;
      padding: 0 10mm;
    }
    .logo {
      max-width: 220px;
      max-height: 80px;
      object-fit: contain;
      margin-bottom: 8px;
    }
    .muted { color: #5c687b; }
    .title {
      font-size: 2rem;
      margin: 0 0 4px 0;
      color: #1b263b;
    }
    .block-title {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #5c687b;
      margin: 0 0 4px 0;
    }
    .card {
      border: 1px solid #d9e0ea;
      border-radius: 10px;
      padding: 10px;
      background: #fbfdff;
    }
    .meta {
      display: table;
      width: 100%;
      table-layout: fixed;
      border-collapse: separate;
      border-spacing: 8mm 0;
      margin: 0 -8mm 10mm -8mm;
    }
    .meta-col {
      display: table-cell;
      width: 50%;
      vertical-align: top;
      padding: 0 8mm;
    }
    .meta table {
      width: 100%;
      border-collapse: collapse;
    }
    .meta td {
      padding: 3px 0;
      vertical-align: top;
    }
    .meta td:first-child {
      width: 42%;
      color: #5c687b;
    }
    .items {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #d9e0ea;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 8mm;
      font-size: 0.92rem;
    }
    .items th, .items td {
      padding: 8px 9px;
      border-bottom: 1px solid #e1e6ef;
      text-align: left;
      vertical-align: top;
    }
    .items th {
      background: #f3f7fc;
      color: #4f5d72;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .items td.num {
      text-align: right;
      white-space: nowrap;
    }
    .items td.route-cell {
      white-space: nowrap;
    }
    .credits {
      margin-bottom: 8mm;
    }
    .credits h3 {
      margin: 0 0 6px 0;
      font-size: 1rem;
    }
    .credits table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #d9e0ea;
      border-radius: 10px;
      overflow: hidden;
      font-size: 0.9rem;
    }
    .credits th, .credits td {
      padding: 7px 8px;
      border-bottom: 1px solid #e1e6ef;
      text-align: left;
    }
    .credits th {
      background: #f3f7fc;
      color: #4f5d72;
      font-size: 0.84rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .credits td:last-child {
      text-align: right;
      white-space: nowrap;
      font-weight: 700;
    }
    .credits tr:last-child td { border-bottom: 0; }
    .totals {
      margin-left: auto;
      width: 360px;
      max-width: 100%;
      border: 1px solid #d9e0ea;
      border-radius: 10px;
      background: #fbfdff;
      overflow: hidden;
    }
    .totals table {
      width: 100%;
      border-collapse: collapse;
    }
    .totals td {
      padding: 6px 10px;
      border-bottom: 1px solid #e1e6ef;
    }
    .totals td:last-child {
      text-align: right;
      white-space: nowrap;
      font-weight: 700;
    }
    .totals tr:last-child td {
      border-bottom: 0;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .bank {
      margin-top: 10mm;
      border-top: 1px dashed #c8d2e0;
      padding-top: 8mm;
      font-size: 0.92rem;
    }
    .bank h3 {
      margin: 0 0 6px 0;
      font-size: 1rem;
    }
    body.mode-pdf {
      font-size: 11px;
      line-height: 1.25;
    }
    .mode-pdf .invoice-page {
      border: 0;
      border-radius: 0;
      max-width: none;
      padding: 0;
    }
    .mode-pdf .header {
      border-spacing: 7mm 0;
      margin: 0 -7mm 4mm -7mm;
    }
    .mode-pdf .header-col {
      padding: 0 7mm;
    }
    .mode-pdf .logo {
      max-width: 160px;
      max-height: 48px;
      margin-bottom: 4px;
    }
    .mode-pdf .title {
      font-size: 1.5rem;
      margin-bottom: 2px;
    }
    .mode-pdf .card {
      padding: 6px 8px;
    }
    .mode-pdf .meta {
      border-spacing: 5mm 0;
      margin: 0 -5mm 4mm -5mm;
    }
    .mode-pdf .meta-col {
      padding: 0 5mm;
    }
    .mode-pdf .meta td {
      padding: 1px 0;
      font-size: 0.9rem;
    }
    .mode-pdf .items {
      margin-bottom: 4mm;
      font-size: 0.84rem;
    }
    .mode-pdf .items th,
    .mode-pdf .items td {
      padding: 5px 6px;
    }
    .mode-pdf .credits {
      margin-bottom: 4mm;
    }
    .mode-pdf .credits h3 {
      margin: 0 0 3px 0;
      font-size: 0.9rem;
    }
    .mode-pdf .credits th,
    .mode-pdf .credits td {
      padding: 4px 6px;
      font-size: 0.82rem;
    }
    .mode-pdf .totals {
      width: 320px;
    }
    .mode-pdf .totals td {
      padding: 4px 8px;
      font-size: 0.9rem;
    }
    .mode-pdf .totals tr:last-child td {
      font-size: 1rem;
    }
    .mode-pdf .bank {
      margin-top: 5mm;
      padding-top: 4mm;
      font-size: 0.84rem;
    }
    .mode-pdf .bank h3 {
      margin-bottom: 4px;
      font-size: 0.92rem;
    }
    @media print {
      .invoice-page {
        border: 0;
        border-radius: 0;
        padding: 0;
        max-width: none;
      }
    }
  </style>
</head>
<body class="<?= $isPdf ? 'mode-pdf' : 'mode-html' ?>">
  <main class="invoice-page">
    <section class="header">
      <div class="header-col">
        <?php if (($logoSrc ?? '') !== ''): ?>
          <img class="logo" src="<?= h((string)$logoSrc) ?>" alt="Logo">
        <?php endif; ?>
        <p class="block-title">Absender</p>
        <div class="card">
          <div><strong><?= h((string)$issuer['name']) ?></strong></div>
          <div><?= h(trim((string)$issuer['street'] . ' ' . (string)$issuer['house_number'])) ?></div>
          <div><?= h(trim((string)$issuer['postal_code'] . ' ' . (string)$issuer['city'])) ?></div>
          <div><?= h((string)$issuer['country']) ?></div>
          <?php if ((string)$issuer['email'] !== ''): ?><div>E-Mail: <?= h((string)$issuer['email']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="header-col">
        <h1 class="title"><?= h((string)$invoiceMeta['title']) ?></h1>
        <div class="card">
          <div><strong><?= h((string)$invoice['invoice_number']) ?></strong></div>
          <div class="muted">Rechnungsdatum: <?= h(date('d.m.Y', strtotime((string)$invoice['created_at']))) ?></div>
          <div class="muted">Zahlbar bis: <?= h((string)$invoiceMeta['due_date']) ?></div>
        </div>
        <p class="block-title" style="margin-top:8mm;">Empfänger</p>
        <div class="card">
          <div><strong><?= h((string)$customerAddress['name']) ?></strong></div>
          <div><?= h((string)$customerAddress['street_line']) ?></div>
          <div><?= h((string)$customerAddress['city_line']) ?></div>
          <div><?= h((string)$customerAddress['country']) ?></div>
          <?php if ((string)$customerAddress['email'] !== ''): ?><div>E-Mail: <?= h((string)$customerAddress['email']) ?></div><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="meta">
      <div class="meta-col">
        <div class="card">
        <table>
                  
          <tr><td>Währung</td><td><?= h($currency) ?></td></tr>
          <tr><td>Zahlungsziel</td><td><?= (int)$invoiceMeta['payment_target_days'] ?> Tage</td></tr>
        </table>
        </div>
      </div>
      <div class="meta-col">
        <div class="card">
        <table>
          <tr><td>Zeitraum</td><td><?= h(date('d.m.Y', strtotime((string)$invoice['period_from']))) ?> - <?= h(date('d.m.Y', strtotime((string)$invoice['period_to']))) ?></td></tr>
          <tr><td>MWST</td><td><?= !empty($vat['enabled']) ? h(number_format((float)$vat['rate_percent'], 1, '.', '')) . '%' : 'Nicht anwendbar' ?></td></tr>
          <?php if (!empty($vat['enabled']) && (string)$vat['uid'] !== ''): ?>
            <tr><td>UID</td><td><?= h((string)$vat['uid']) ?></td></tr>
          <?php endif; ?>
        </table>
        </div>
      </div>
    </section>

    <section class="credits">
      <h3>Flüge</h3>
    </section>
    <table class="items">
      <thead>
        <tr>
          <th>Datum</th>
          <th>Flugzeugtyp</th>
          <th>Route</th>
          <th class="num">Hobbs</th>
          <th class="num">Stundensatz</th>
          <th class="num">Einzelpreis</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items === []): ?>
          <tr><td colspan="6">Keine Positionen vorhanden.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php
            $flightDate = (string)($item['flight_date'] ?? '');
            $route = trim((string)($item['from_airfield'] ?? '') . ((string)($item['from_airfield'] ?? '') !== '' || (string)($item['to_airfield'] ?? '') !== '' ? ' - ' : '') . (string)($item['to_airfield'] ?? ''));
            $aircraftTypeLabel = trim((string)($item['aircraft_immatriculation'] ?? '') . ' / ' . (string)($item['aircraft_type'] ?? ''));
            ?>
            <tr>
              <td><?= h($flightDate !== '' ? date('d.m.Y', strtotime($flightDate)) : '-') ?></td>
              <td><?= h($aircraftTypeLabel !== '/' ? trim($aircraftTypeLabel, ' /') : '-') ?></td>
              <td class="route-cell"><?= h($route !== '' ? $route : '-') ?></td>
              <td class="num"><?= h(number_format((float)$item['hours'], 2, '.', '')) ?></td>
              <td class="num"><?= h($currency) ?> <?= h(number_format((float)$item['unit_price'], 2, '.', '')) ?></td>
              <td class="num"><?= h($currency) ?> <?= h(number_format((float)$item['line_total'], 2, '.', '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if (!empty($credits)): ?>
      <section class="credits">
        <h3>Gutschriften</h3>
        <table>
          <thead>
            <tr>
              <th>Datum</th>
              <th>Beschreibung</th>
              <th>Betrag</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($credits as $credit): ?>
              <tr>
                <td><?= h(date('d.m.Y', strtotime((string)$credit['credit_date']))) ?></td>
                <td><?= h((string)$credit['description']) ?></td>
                <td>- <?= h($currency) ?> <?= h(number_format((float)$credit['amount'], 2, '.', '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <section class="totals">
      <table>
        <tbody>
          <tr>
            <td>Zwischensumme Flüge</td>
            <td><?= h($currency) ?> <?= h(number_format($summaryFlights, 2, '.', '')) ?></td>
          </tr>
          <?php if (!empty($summaryCredits)): ?>
            <tr>
              <td>Abzug Gutschriften</td>
              <td>- <?= h($currency) ?> <?= h(number_format($summaryCredits, 2, '.', '')) ?></td>
            </tr>
          <?php endif; ?>
          <?php if (!empty($vat['enabled'])): ?>
            <tr>
              <td>MWST <?= h(number_format((float)$vat['rate_percent'], 1, '.', '')) ?>%</td>
              <td><?= h($currency) ?> <?= h(number_format($summaryVat, 2, '.', '')) ?></td>
            </tr>
          <?php endif; ?>
          <tr>
            <td>Gesamtsumme</td>
            <td><?= h($currency) ?> <?= h(number_format($summaryTotal, 2, '.', '')) ?></td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="bank">
      <h3>Zahlungsinformationen</h3>
      <div><strong>Empfänger:</strong> <?= h((string)$bank['recipient']) ?></div>
      <div><strong>IBAN:</strong> <?= h((string)$bank['iban']) ?></div>
      <?php if ((string)$bank['bic'] !== ''): ?><div><strong>BIC/SWIFT:</strong> <?= h((string)$bank['bic']) ?></div><?php endif; ?>
      <?php if ((string)$bank['bank_name'] !== ''): ?><div><strong>Bank:</strong> <?= h((string)$bank['bank_name']) ?></div><?php endif; ?>
      <?php if ((string)$bank['bank_address'] !== ''): ?><div><strong>Bankadresse:</strong> <?= h((string)$bank['bank_address']) ?></div><?php endif; ?>
    </section>
  </main>
</body>
</html>
