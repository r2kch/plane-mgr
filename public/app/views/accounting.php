<h2>Buchhaltung</h2>
<?php if (can('admin.access')): ?><p class="access-note">Zugang: <code>billing.access</code></p><?php endif; ?>
<div class="cards admin-hub-cards">
  <article class="card">
    <h3>Flüge</h3>
    <p>Flugzeuge öffnen und Verrechenbarkeit im Logbuch steuern.</p>
    <a class="btn-small" href="index.php?page=accounting_flights">Öffnen</a>
  </article>
  <article class="card">
    <h3>Abrechnung</h3>
    <p>Offene Stunden einsehen und Rechnungen erstellen.</p>
    <a class="btn-small" href="index.php?page=invoices">Öffnen</a>
  </article>
  <article class="card">
    <h3>Gutschrift</h3>
    <p>Spesen/Barausgaben erfassen und offen verwalten.</p>
    <a class="btn-small" href="index.php?page=credits">Öffnen</a>
  </article>
  <article class="card">
    <h3>Positionen</h3>
    <p>Besondere Positionen (z. B. Mitgliederbeiträge) verwalten.</p>
    <a class="btn-small" href="index.php?page=positions">Öffnen</a>
  </article>
  <?php if (can('rates.manage')): ?>
    <article class="card">
      <h3>Preise</h3>
      <p>Preise pro Pilot und Flugzeug verwalten.</p>
      <a class="btn-small" href="index.php?page=rates">Öffnen</a>
    </article>
  <?php endif; ?>
</div>
