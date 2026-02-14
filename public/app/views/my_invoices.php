<h2>Meine Rechnungen</h2>

<h3>Alle offenen</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr><th>Nr.</th><th>Periode</th><th>Total</th><th>Status</th><th>Aktion</th></tr>
    </thead>
    <tbody>
      <?php if (empty($openInvoices)): ?>
        <tr><td colspan="5">Keine offenen Rechnungen.</td></tr>
      <?php else: ?>
        <?php foreach ($openInvoices as $i): ?>
          <tr>
            <td><?= h($i['invoice_number']) ?></td>
            <td><?= h($i['period_from']) ?> - <?= h($i['period_to']) ?></td>
            <td><?= number_format((float)$i['total_amount'], 2, '.', '') ?> CHF</td>
            <td><span class="status-chip <?= h($i['payment_status']) ?>"><?= h($i['payment_status']) ?></span></td>
            <td><a class="btn-small" href="index.php?page=invoice_pdf&id=<?= (int)$i['id'] ?>" target="_blank">Rechnung anzeigen</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<h3>Alle bezahlten</h3>
<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr><th>Nr.</th><th>Periode</th><th>Total</th><th>Status</th><th>Aktion</th></tr>
    </thead>
    <tbody>
      <?php if (empty($paidInvoices)): ?>
        <tr><td colspan="5">Keine bezahlten Rechnungen.</td></tr>
      <?php else: ?>
        <?php foreach ($paidInvoices as $i): ?>
          <tr>
            <td><?= h($i['invoice_number']) ?></td>
            <td><?= h($i['period_from']) ?> - <?= h($i['period_to']) ?></td>
            <td><?= number_format((float)$i['total_amount'], 2, '.', '') ?> CHF</td>
            <td><span class="status-chip <?= h($i['payment_status']) ?>"><?= h($i['payment_status']) ?></span></td>
            <td><a class="btn-small" href="index.php?page=invoice_pdf&id=<?= (int)$i['id'] ?>" target="_blank">Rechnung anzeigen</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
