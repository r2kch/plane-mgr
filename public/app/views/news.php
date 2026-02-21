<h2>News</h2>

<?php if ($canManageNews): ?>
  <?php $isEditing = !empty($editNews); ?>
  <div class="section-head-actions" style="justify-content: flex-end; gap: 10px;">
    <button type="button" class="btn-small" id="news-toggle">
      <?= $isEditing ? 'News bearbeiten' : 'News erstellen' ?>
    </button>
  </div>
  <form method="post" class="grid-form news-form" id="news-form" style="<?= $isEditing ? '' : 'display:none;' ?>">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $isEditing ? 'update_news' : 'create_news' ?>">
    <?php if ($isEditing): ?>
      <input type="hidden" name="news_id" value="<?= (int)$editNews['id'] ?>">
    <?php endif; ?>
    <label class="news-title">Titel
      <input name="title" maxlength="200" value="<?= h($isEditing ? (string)$editNews['title'] : '') ?>" required>
    </label>
    <label class="news-editor-field">Beitrag
      <div class="news-toolbar" role="toolbar" aria-label="Editor">
        <button type="button" class="news-tool" data-cmd="bold"><strong>B</strong></button>
        <button type="button" class="news-tool" data-cmd="italic"><em>I</em></button>
        <button type="button" class="news-tool" data-cmd="underline"><u>U</u></button>
        <label class="news-color">
          <span>Farbe</span>
          <input type="color" id="news-color-picker" value="#1b263b">
        </label>
      </div>
      <div class="news-editor" id="news-editor" contenteditable="true" data-initial="<?= h($isEditing ? (string)$editNews['body_html'] : '') ?>"></div>
      <textarea name="body_html" id="news-body" required hidden></textarea>
    </label>
    <div class="news-actions">
      <button type="submit"><?= $isEditing ? 'Änderungen speichern' : 'News veröffentlichen' ?></button>
      <?php if ($isEditing): ?>
        <a class="btn-ghost btn-small" href="index.php?page=news">Bearbeitung abbrechen</a>
        <button type="submit" form="news-delete-form" class="btn-ghost btn-small" onclick="return confirm('News wirklich löschen?');">Löschen</button>
      <?php endif; ?>
    </div>
  </form>
  <?php if ($isEditing): ?>
    <form method="post" id="news-delete-form">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete_news">
      <input type="hidden" name="news_id" value="<?= (int)$editNews['id'] ?>">
    </form>
  <?php endif; ?>
<?php endif; ?>

<h3>Aktuelle News</h3>
<div class="cards news-list">
  <?php if (empty($newsList)): ?>
    <article class="card">
      <p>Keine News vorhanden.</p>
    </article>
  <?php else: ?>
    <?php foreach ($newsList as $news): ?>
      <article class="card">
        <h4><?= h((string)$news['title']) ?></h4>
        <div class="muted" style="margin-bottom:8px;">
          <?= h(date('d.m.Y H:i', strtotime((string)$news['created_at']))) ?>
          <?php if (!empty($news['author_name'])): ?>
            · <?= h((string)$news['author_name']) ?>
          <?php endif; ?>
        </div>
        <div class="cell-wrap"><?= $news['body_html'] ?></div>
        <?php if ($canManageNews): ?>
          <div style="margin-top:10px;">
            <a class="btn-small" href="index.php?page=news&edit_id=<?= (int)$news['id'] ?>">Bearbeiten</a>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
  (function () {
    const form = document.getElementById('news-form');
    const editor = document.getElementById('news-editor');
    const hidden = document.getElementById('news-body');
    if (!form || !editor || !hidden) return;

    const initial = editor.dataset.initial || '';
    editor.innerHTML = initial;
    hidden.value = initial;

    const sync = () => {
      hidden.value = editor.innerHTML;
    };

    editor.addEventListener('input', sync);
    editor.addEventListener('blur', sync);
    form.addEventListener('submit', sync);

    const toolbar = form.querySelector('.news-toolbar');
    if (toolbar) {
      toolbar.addEventListener('click', (event) => {
        const target = event.target.closest('button[data-cmd]');
        if (!target) return;
        document.execCommand(target.dataset.cmd, false, null);
        editor.focus();
        sync();
      });
    }

    const colorPicker = document.getElementById('news-color-picker');
    if (colorPicker) {
      colorPicker.addEventListener('input', () => {
        document.execCommand('foreColor', false, colorPicker.value);
        editor.focus();
        sync();
      });
    }

    const toggle = document.getElementById('news-toggle');
    if (toggle) {
      toggle.addEventListener('click', () => {
        const isHidden = form.style.display === 'none' || form.style.display === '';
        form.style.display = isHidden ? 'grid' : 'none';
      });
    }
  }());
</script>
