<section class="auth-wrap">
  <h2>Anmelden</h2>
  <?php $installationOpen = ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn()) === 0; ?>
  <form method="post" class="grid-form auth-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label>E-Mail
      <input type="email" name="email" required>
    </label>
    <label>Passwort
      <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
  </form>
  <?php if ($installationOpen): ?>
    <p><a href="setup.php">Setup (Admin anlegen)</a></p>
  <?php endif; ?>
</section>
