<h2>Mitglieder</h2>

<form method="get" class="inline-form user-search-form" style="margin-bottom: 12px;">
  <input type="hidden" name="page" value="members">
  <label>Suche
    <input name="q" value="<?= h($membersSearch ?? '') ?>" placeholder="Name, Vorname oder Telefonnummer">
  </label>
  <button type="submit" class="btn-small">Filtern</button>
  <?php if (!empty($membersSearch)): ?>
    <a class="btn-ghost btn-small" href="index.php?page=members">Zurücksetzen</a>
  <?php endif; ?>
</form>

<div class="table-wrap">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Vorname</th>
        <th>Telefonnummer</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($members)): ?>
        <tr><td colspan="3">Keine Mitglieder vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($members as $member): ?>
          <tr>
            <td><?= h((string)$member['last_name']) ?></td>
            <td><?= h((string)$member['first_name']) ?></td>
            <td><?= h((string)($member['phone'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
