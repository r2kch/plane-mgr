<h2>Mein Profil</h2>

<form method="post">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

  <div class="grid-form" style="grid-template-columns: repeat(2, minmax(220px, 1fr)); margin-bottom: 10px;">
    <label>Vorname
      <input name="first_name" value="<?= h((string)$profile['first_name']) ?>" required>
    </label>
    <label>Name
      <input name="last_name" value="<?= h((string)$profile['last_name']) ?>" required>
    </label>
  </div>

  <div class="grid-form" style="grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.6fr) minmax(140px, 0.8fr) minmax(180px, 1fr) minmax(180px, 1fr); margin-bottom: 10px;">
    <label>Strasse
      <input name="street" value="<?= h((string)($profile['street'] ?? '')) ?>">
    </label>
    <label>Hausnummer
      <input name="house_number" value="<?= h((string)($profile['house_number'] ?? '')) ?>">
    </label>
    <label>Postleitzahl
      <input name="postal_code" value="<?= h((string)($profile['postal_code'] ?? '')) ?>">
    </label>
    <label>Ort
      <input name="city" value="<?= h((string)($profile['city'] ?? '')) ?>">
    </label>
    <label>Land
      <select name="country_code">
        <?php foreach (($countryOptions ?? []) as $countryCode => $countryName): ?>
          <option value="<?= h((string)$countryCode) ?>" <?= (string)$countryCode === (string)($profile['country_code'] ?? 'CH') ? 'selected' : '' ?>><?= h((string)$countryName) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="grid-form" style="grid-template-columns: repeat(2, minmax(220px, 1fr)); margin-bottom: 10px;">
    <label>Telefonnummer
      <input name="phone" value="<?= h((string)($profile['phone'] ?? '')) ?>">
    </label>
    <label>Neues Passwort
      <input type="password" name="new_password" minlength="8" placeholder="mind. 8 Zeichen">
    </label>
  </div>

  <label style="margin-bottom: 10px; max-width: 500px;">E-Mail (nicht änderbar)
    <input type="email" value="<?= h((string)$profile['email']) ?>" disabled>
  </label>

  <button type="submit" class="btn-small">Speichern</button>
</form>
