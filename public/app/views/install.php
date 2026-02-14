<section class="auth-wrap">
  <h2>Erstinstallation</h2>
  <p>Einmalig den ersten Admin-Benutzer anlegen.</p>
  <form method="post" class="grid-form narrow">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label>Vorname
      <input name="first_name" required>
    </label>
    <label>Nachname
      <input name="last_name" required>
    </label>
    <label>E-Mail
      <input type="email" name="email" required>
    </label>
    <label>Passwort (mind. 8 Zeichen)
      <input type="password" name="password" minlength="8" required>
    </label>
    <button type="submit">Admin anlegen</button>
  </form>
</section>
