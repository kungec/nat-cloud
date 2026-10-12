<?php /* NAT Cloud - 管理后台：工单处理 */ ?>
<?php ob_start(); ?>
<?php
$cmap = ['billing' => '账务', 'instance' => '实例', 'network' => '网络', 'other' => '其他'];
$smap = ['open' => '待处理', 'replied' => '已回复', 'closed' => '已关闭'];
?>
<div class="card" style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
    <h3 style="margin:0;"><?= e($ticket['title']) ?></h3>
    <span class="tag <?= e($ticket['status']) ?>"><?= e($smap[$ticket['status']] ?? $ticket['status']) ?></span>
  </div>
  <div style="color:var(--text-faint);font-size:12.5px;margin-top:6px;">
    用户 <a href="/admin/users/<?= (int) $ticket['uid'] ?>"><?= e($ticket['username'] ?? $ticket['uid']) ?></a>（<?= e($ticket['email'] ?? '-') ?>）
    · 分类：<?= e($cmap[$ticket['category']] ?? '其他') ?> · 更新于 <?= e(dt($ticket['updated_at'])) ?>
  </div>
</div>

<div class="chat" style="margin-bottom:18px;">
  <?php foreach ($replies as $r): ?>
    <div class="bubble <?= (int) $r['is_admin'] === 1 ? 'admin' : 'user' ?>">
      <div class="meta"><?= (int) $r['is_admin'] === 1 ? '客服（我）' : e($r['username'] ?? '用户') ?> · <?= e(dt($r['created_at'])) ?></div>
      <?= e($r['content']) ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="card" style="max-width:680px;">
  <form method="post" action="/admin/tickets/<?= (int) $ticket['id'] ?>/reply">
    <?= csrf_field() ?>
    <div class="field">
      <textarea class="input" name="content" rows="4" maxlength="3000" placeholder="回复内容（发送后工单转为已回复）"></textarea>
    </div>
    <div style="display:flex;gap:10px;">
      <button class="btn btn-primary" type="submit">发送回复</button>
      <button class="btn btn-ghost" type="submit" name="close" value="1" data-confirm="回复并关闭该工单？">回复并关闭</button>
    </div>
  </form>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
