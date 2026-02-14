<section class="invoice-sheet">
  <h2>Rechnung <?= h($invoice['invoice_number']) ?></h2>
  <p>Kunde: <?= h($invoice['customer_name']) ?> (<?= h($invoice['email']) ?>)</p>
  <p>Periode: <?= h($invoice['period_from']) ?> bis <?= h($invoice['period_to']) ?></p>

  <table>
    <thead>
      <tr><th>Beschreibung</th><th>Stunden</th><th>Preis</th><th>Total</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= h($item['description']) ?></td>
          <td><?= number_format((float)$item['hours'], 2, '.', '') ?></td>
          <td><?= number_format((float)$item['unit_price'], 2, '.', '') ?> CHF</td>
          <td><?= number_format((float)$item['line_total'], 2, '.', '') ?> CHF</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3>Gesamt: <?= number_format((float)$invoice['total_amount'], 2, '.', '') ?> CHF</h3>
  <p>Dies ist ein HTML-Rechnungsentwurf. Für echtes PDF auf Hosting Dompdf/mPDF ergänzen.</p>
</section>
