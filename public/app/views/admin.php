<h2>Admin</h2>
<div class="cards admin-hub-cards">
  <article class="card">
    <h3>Flugzeuge</h3>
    <p>Flugzeuge verwalten und Logbuch öffnen.</p>
    <a class="btn-small" href="index.php?page=aircraft">Öffnen</a>
  </article>
  <article class="card">
    <h3>Flugzeug-Gruppen</h3>
    <p>Gruppen anlegen, umbenennen und Flugzeuge zuordnen.</p>
    <a class="btn-small" href="index.php?page=groups">Öffnen</a>
  </article>
  <article class="card">
    <h3>Benutzer</h3>
    <p>Benutzer und Rollen pflegen.</p>
    <a class="btn-small" href="index.php?page=users">Öffnen</a>
  </article>
  <article class="card">
    <h3>Rollen</h3>
    <p>Rollenrechte verwalten (Admin hat immer alles).</p>
    <a class="btn-small" href="index.php?page=permissions">Öffnen</a>
  </article>
  <?php if (module_enabled('audit')): ?>
    <article class="card">
      <h3>Audit-Log</h3>
      <p>Nachverfolgung aller relevanten Aktionen.</p>
      <a class="btn-small" href="index.php?page=audit">Öffnen</a>
    </article>
  <?php endif; ?>
</div>
