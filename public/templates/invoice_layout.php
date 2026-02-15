<?php
declare(strict_types=1);

$currency = (string)($invoiceMeta['currency'] ?? 'CHF');
$netTotal = 0.0;
foreach ($items as $item) {
    $netTotal += (float)$item['line_total'];
}
$netTotal = round($netTotal, 2);
$vatAmount = 0.0;
if (!empty($vat['enabled'])) {
    $vatAmount = round($netTotal * ((float)$vat['rate_percent'] / 100), 2);
}
$grossTotal = round($netTotal + $vatAmount, 2);
?>
<!doctype html>
<html lang="de-CH">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h((string)$invoiceMeta['title']) ?> <?= h((string)$invoice['invoice_number']) ?></title>
  <style>
    @page { size: A4; margin: 14mm; }
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
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 14mm;
      margin-bottom: 10mm;
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
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8mm;
      margin-bottom: 10mm;
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
    .totals {
      margin-left: auto;
      width: min(360px, 100%);
      border: 1px solid #d9e0ea;
      border-radius: 10px;
      padding: 8px 10px;
      background: #fbfdff;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      padding: 4px 0;
    }
    .totals-row.grand {
      margin-top: 4px;
      border-top: 1px solid #d9e0ea;
      padding-top: 8px;
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
<body>
  <main class="invoice-page">
    <section class="header">
      <div>
        <?php if ($logoPublicPath !== ''): ?>
          <img class="logo" src="<?= h($logoPublicPath) ?>" alt="Logo">
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
      <div>
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
      <div class="card">
        <table>
                  
          <tr><td>Währung</td><td><?= h($currency) ?></td></tr>
          <tr><td>Zahlungsziel</td><td><?= (int)$invoiceMeta['payment_target_days'] ?> Tage</td></tr>
        </table>
      </div>
      <div class="card">
        <table>
          <tr><td>Zeitraum</td><td><?= h(date('d.m.Y', strtotime((string)$invoice['period_from']))) ?> - <?= h(date('d.m.Y', strtotime((string)$invoice['period_to']))) ?></td></tr>
          <tr><td>MWST</td><td><?= !empty($vat['enabled']) ? h(number_format((float)$vat['rate_percent'], 1, '.', '')) . '%' : 'Nicht anwendbar' ?></td></tr>
          <?php if (!empty($vat['enabled']) && (string)$vat['uid'] !== ''): ?>
            <tr><td>UID</td><td><?= h((string)$vat['uid']) ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
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

    <section class="totals">
      <div class="totals-row"><span>Zwischensumme</span><strong><?= h($currency) ?> <?= h(number_format($netTotal, 2, '.', '')) ?></strong></div>
      <?php if (!empty($vat['enabled'])): ?>
        <div class="totals-row"><span>MWST <?= h(number_format((float)$vat['rate_percent'], 1, '.', '')) ?>%</span><strong><?= h($currency) ?> <?= h(number_format($vatAmount, 2, '.', '')) ?></strong></div>
      <?php endif; ?>
      <div class="totals-row grand"><span>Gesamtsumme</span><strong><?= h($currency) ?> <?= h(number_format($grossTotal, 2, '.', '')) ?></strong></div>
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
