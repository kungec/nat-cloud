<?php /* NAT Cloud - 管理后台：公告管理 */ ?>
<?php ob_start(); ?>
<div style="margin-bottom:16px;">
  <button class="btn btn-primary" type="button" data-modal-open="modalNews">+ 发布公告</button>
</div>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">暂无公告</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID</th><th>标题</th><th>状态</th><th>发布时间</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $n): ?>
        <tr>
          <td class="mono"><?= (int) $n['id'] ?></td>
          <td><a href="/news/<?= (int) $n['id'] ?>" target="_blank"><?= e($n['title']) ?></a></td>
          <td><span class="tag <?= (int) $n['status'] === 1 ? 'run' : 'closed' ?>"><?= (int) $n['status'] === 1 ? '已发布' : '已隐藏' ?></span></td>
          <td class="mono"><?= e(dt($n['created_at'])) ?></td>
          <td>
            <div class="actions">
              <button class="btn btn-ghost btn-sm" type="button" data-modal-open="newsEdit<?= (int) $n['id'] ?>">编辑</button>
              <form class="inline-form" method="post" action="/admin/news/<?= (int) $n['id'] ?>/toggle"><?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $n['status'] === 1 ? '隐藏' : '发布' ?></button>
              </form>
              <form class="inline-form" method="post" action="/admin/news/<?= (int) $n['id'] ?>/delete" data-confirm="确认删除该公告？"><?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">删除</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total); ?>

<div class="modal-mask" id="modalNews">
  <div class="modal">
    <h3>发布公告</h3>
    <form method="post" action="/admin/news/create">
      <?= csrf_field() ?>
      <div class="field">
        <label>标题</label>
        <input class="input" type="text" name="title" maxlength="120" required>
      </div>
      <div class="field">
        <label>内容</label>
        <textarea class="input" name="content" rows="8" required></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">发布</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($rows as $n): ?>
<div class="modal-mask" id="newsEdit<?= (int) $n['id'] ?>">
  <div class="modal">
    <h3>编辑公告</h3>
    <form method="post" action="/admin/news/<?= (int) $n['id'] ?>/edit">
      <?= csrf_field() ?>
      <div class="field">
        <label>标题</label>
        <input class="input" type="text" name="title" value="<?= e($n['title']) ?>" maxlength="120" required>
      </div>
      <div class="field">
        <label>内容</label>
        <textarea class="input" name="content" rows="8" required><?= e($n['content']) ?></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
