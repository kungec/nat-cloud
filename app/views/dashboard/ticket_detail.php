<?php /* NAT Cloud - 工单详情对话 */ ?>
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
    分类：<?= e($cmap[$ticket['category']] ?? '其他') ?>
    · 优先级：<span class="prio <?= e($ticket['priority'] ?? 'normal') ?>"><?php
        $pmap = ['low' => '低', 'normal' => '普通', 'high' => '高', 'urgent' => '紧急'];
        echo e($pmap[$ticket['priority'] ?? 'normal'] ?? '普通');
    ?></span>
    <?php if (!empty($ticket['instance_id'])): ?>
      · 关联实例：<a href="/dashboard/services/<?= (int) $ticket['instance_id'] ?>" class="mono"><?= e(db_val('SELECT name FROM instances WHERE id = ?', [(int) $ticket['instance_id']]) ?? $ticket['instance_id']) ?></a>
    <?php endif; ?>
    · 提交于 <?= e(dt($ticket['created_at'])) ?>
  </div>
</div>

<?php $tkImgs = json_decode((string) ($ticket['images'] ?? '[]'), true); ?>
<?php if (is_array($tkImgs) && $tkImgs): ?>
<div class="tk-imgs" style="margin-bottom:16px;">
  <?php foreach ($tkImgs as $im): ?>
    <a href="/ticket-file?tid=<?= (int) $ticket['id'] ?>&f=<?= e($im) ?>" target="_blank"><img src="/ticket-file?tid=<?= (int) $ticket['id'] ?>&f=<?= e($im) ?>" alt=""></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="chat" style="margin-bottom:18px;">
  <?php if (empty($replies)): ?>
    <div class="empty">暂无回复</div>
  <?php endif; ?>
  <?php foreach ($replies as $r): ?>
    <div class="bubble <?= (int) $r['is_admin'] === 1 ? 'admin' : 'user' ?>">
      <div class="meta"><?= (int) $r['is_admin'] === 1 ? '客服' : '我' ?> · <?= e(dt($r['created_at'])) ?></div>
      <?= e($r['content']) ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($ticket['status'] !== 'closed'): ?>
<div class="card" style="max-width:680px;">
  <form method="post" action="/dashboard/tickets/<?= (int) $ticket['id'] ?>/reply">
    <?= csrf_field() ?>
    <div class="field">
      <textarea class="input" name="content" rows="4" maxlength="3000" placeholder="回复内容"></textarea>
    </div>
    <div style="display:flex;gap:10px;">
      <button class="btn btn-primary" type="submit">发送回复</button>
    </div>
  </form>
  <form method="post" action="/dashboard/tickets/<?= (int) $ticket['id'] ?>/close" style="margin-top:12px;" data-confirm="确认关闭该工单？">
    <?= csrf_field() ?>
    <button class="btn btn-ghost btn-sm" type="submit">关闭工单</button>
  </form>
</div>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
