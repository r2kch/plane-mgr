<h2>Rollen & Berechtigungen</h2>
<?php if (can('admin.access')): ?><p class="access-note">Zugang: <code>roles.manage</code> · Geschützt: <code>roles.manage.protected</code></p><?php endif; ?>
<p>Admin hat immer alle Rechte. Änderungen hier wirken sofort.</p>

<h3>Neue Rolle</h3>
<form method="post" class="inline-form" style="margin-bottom: 16px; align-items: flex-end;">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="create_role">
  <label>Rollenname
    <input name="role_name" placeholder="z.B. trainer" required>
  </label>
  <button class="btn-small" style="height: 44px;">Erstellen</button>
</form>

<?php if (empty($permissions ?? [])): ?>
  <div class="flash flash-error">Keine Berechtigungen gefunden. Bitte Seeds prüfen.</div>
<?php endif; ?>

<h3>Rollen</h3>
<div class="table-wrap" style="margin-bottom: 16px;">
  <table class="entries-table">
    <thead>
      <tr>
        <th>Rolle</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($roles ?? []) as $role): ?>
        <?php
        $roleId = (int)$role['id'];
        $roleName = (string)$role['name'];
        if ($roleName === 'admin') {
            continue;
        }
        $isProtectedRole = $roleName === 'admin';
        $canManageProtected = can('roles.manage.protected');
        $openHref = 'index.php?page=permissions&open_role_id=' . $roleId;
        ?>
        <tr>
          <td>
            <?php if ($isProtectedRole && !$canManageProtected): ?>
              <?= h(role_label($roleName)) ?> <?php if (can('admin.access')): ?><span class="access-note">(geschützt)</span><?php endif; ?>
            <?php else: ?>
              <a href="<?= h($openHref) ?>"><?= h(role_label($roleName)) ?></a>
              <?php if ($isProtectedRole && can('admin.access')): ?> <span class="access-note">(geschützt)</span><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$openRole = null;
foreach (($roles ?? []) as $candidate) {
    if ((int)$candidate['id'] === (int)($openRoleId ?? 0)) {
        $openRole = $candidate;
        break;
    }
}
?>

<?php if ($openRole): ?>
  <?php
  $roleId = (int)$openRole['id'];
  $roleName = (string)$openRole['name'];
  $isProtectedRole = $roleName === 'admin';
  $canManageProtected = can('roles.manage.protected');
  ?>
  <article class="card" style="margin-bottom: 12px;">
    <h3><?= h(role_label($roleName)) ?></h3>
    <?php if ($isProtectedRole && can('admin.access')): ?>
      <?php if (can('admin.access')): ?><p class="access-note">Geschützte Rolle (Zugang: <code>roles.manage.protected</code>)</p><?php endif; ?>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="role_id" value="<?= $roleId ?>">
      <div class="table-wrap" style="margin-bottom: 10px;">
        <table class="entries-table">
          <thead>
            <tr>
              <th>Berechtigungen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($permissions ?? []) as $permission): ?>
              <?php
              $permissionId = (int)$permission['id'];
              $permissionName = (string)$permission['name'];
              $permissionLabel = (string)($permission['label'] ?? '');
              $checked = !empty($rolePermissionsByRoleId[$roleId][$permissionId]);
              ?>
              <tr>
                <td>
                  <details>
                    <summary class="checkline">
                      <input type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>" <?= $checked ? 'checked' : '' ?> <?= ($isProtectedRole && !$canManageProtected) ? 'disabled' : '' ?>>
                      <span><?= h($permissionLabel !== '' ? $permissionLabel : $permissionName) ?></span>
                    </summary>
                    <div style="opacity: 0.7; padding-left: 24px; padding-top: 6px;">
                      Technischer Name: <?= h($permissionName) ?>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="inline-form">
        <?php if (!$isProtectedRole || $canManageProtected): ?>
          <button class="btn-small">Speichern</button>
        <?php endif; ?>
        <?php if (!in_array($roleName, ['admin', 'accounting'], true)): ?>
          <button class="btn-ghost btn-small" type="submit" form="delete-role-<?= $roleId ?>">Löschen</button>
        <?php endif; ?>
      </div>
    </form>
    <?php if (!in_array($roleName, ['admin', 'accounting'], true)): ?>
      <form method="post" id="delete-role-<?= $roleId ?>" onsubmit="return confirm('Rolle wirklich löschen? Alle Zuweisungen gehen verloren.');" style="display:none;">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_role">
        <input type="hidden" name="role_id" value="<?= $roleId ?>">
      </form>
    <?php endif; ?>
  </article>
<?php endif; ?>

<script>
  document.querySelectorAll('details summary input[type="checkbox"]').forEach((checkbox) => {
    checkbox.addEventListener('click', (event) => {
      event.stopPropagation();
    });
  });
</script>
