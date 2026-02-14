<h2>Gruppen</h2>
<div class="section-head-actions">
  <a class="btn-small" href="index.php?page=groups&new=<?= ((int)($showNewGroupForm ?? 0) === 1) ? '0' : '1' ?>">Neue Gruppe</a>
</div>

<?php if ((int)($showNewGroupForm ?? 0) === 1): ?>
  <h3>Neue Gruppe anlegen</h3>
  <form method="post" class="user-item">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="grid-form group-grid">
      <label>Name
        <input name="name" required maxlength="100">
      </label>
    </div>

    <label>Zugeordnete Flugzeuge
      <div class="checks role-group">
        <?php foreach (($aircraft ?? []) as $a): ?>
          <label class="checkline">
            <input type="checkbox" name="aircraft_ids[]" value="<?= (int)$a['id'] ?>">
            <span><?= h((string)$a['immatriculation']) ?> (<?= h((string)$a['type']) ?>)</span>
          </label>
        <?php endforeach; ?>
        <?php if (empty($aircraft)): ?>
          <span>Keine Flugzeuge vorhanden.</span>
        <?php endif; ?>
      </div>
    </label>

    <div class="inline-form user-meta">
      <button type="submit" class="btn-small">Speichern</button>
      <a class="btn-ghost btn-small" href="index.php?page=groups">Abbrechen</a>
    </div>
  </form>
<?php endif; ?>

<h3>Bestehende Gruppen</h3>
<div class="table-wrap" style="margin-bottom: 12px;">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Flugzeuge</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($groups)): ?>
        <tr><td colspan="3">Keine Gruppen vorhanden.</td></tr>
      <?php else: ?>
        <?php foreach ($groups as $g): ?>
          <?php $openHref = 'index.php?page=groups&open_group_id=' . (int)$g['id']; ?>
          <tr>
            <td><a href="<?= h($openHref) ?>"><?= h((string)$g['name']) ?></a></td>
            <td><?= (int)$g['aircraft_count'] ?></td>
            <td><a class="btn-small" href="<?= h($openHref) ?>">Bearbeiten</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$openGroup = null;
foreach (($groups ?? []) as $candidate) {
    if ((int)$candidate['id'] === (int)($openGroupId ?? 0)) {
        $openGroup = $candidate;
        break;
    }
}
?>

<?php if ($openGroup !== null): ?>
  <?php $deleteGroupFormId = 'delete-group-' . (int)$openGroup['id']; ?>
  <h3>Gruppe bearbeiten</h3>
  <form method="post" class="user-item">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="group_id" value="<?= (int)$openGroup['id'] ?>">

    <div class="grid-form group-grid">
      <label>Name
        <input name="name" required maxlength="100" value="<?= h((string)$openGroup['name']) ?>">
      </label>
    </div>

    <label>Zugeordnete Flugzeuge
      <div class="checks role-group">
        <?php foreach (($aircraft ?? []) as $a): ?>
          <label class="checkline">
            <input
              type="checkbox"
              name="aircraft_ids[]"
              value="<?= (int)$a['id'] ?>"
              <?= (int)($a['aircraft_group_id'] ?? 0) === (int)$openGroup['id'] ? 'checked' : '' ?>
            >
            <span><?= h((string)$a['immatriculation']) ?> (<?= h((string)$a['type']) ?>)</span>
          </label>
        <?php endforeach; ?>
        <?php if (empty($aircraft)): ?>
          <span>Keine Flugzeuge vorhanden.</span>
        <?php endif; ?>
      </div>
    </label>

    <div class="inline-form user-meta">
      <span>ID: <?= (int)$openGroup['id'] ?></span>
      <button type="submit" class="btn-small">Speichern</button>
      <button type="submit" form="<?= h($deleteGroupFormId) ?>" class="btn-ghost btn-small" onclick="return confirm('Gruppe wirklich löschen?');">Löschen</button>
      <a class="btn-ghost btn-small" href="index.php?page=groups">Bearbeitung abbrechen</a>
    </div>
  </form>
  <form method="post" id="<?= h($deleteGroupFormId) ?>" class="user-delete-form">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="group_id" value="<?= (int)$openGroup['id'] ?>">
  </form>
<?php endif; ?>
