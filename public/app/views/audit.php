<h2>Audit-Log</h2>
<div class="table-wrap">
  <table>
    <thead><tr><th>Zeit</th><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>Meta</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= h($log['created_at']) ?></td>
          <td><?= h($log['actor']) ?></td>
          <td><?= h($log['action']) ?></td>
          <td><?= h($log['entity']) ?></td>
          <td><?= h((string)$log['entity_id']) ?></td>
          <td class="cell-wrap"><code><?= h((string)$log['meta_json']) ?></code></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
