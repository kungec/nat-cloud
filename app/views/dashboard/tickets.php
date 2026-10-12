<?php /* NAT Cloud - 工单列表 */ ?>
<?php ob_start(); ?>
<div class="main-head" style="margin-bottom:14px;">
  <div class="sub">遇到问题？提交工单，我们会尽快回复。</div>
  <a class="btn btn-primary btn-sm" href="/dashboard/tickets/new">提交工单</a>
</div>
<div class="tabs">
  <a class="<?= $tab === '' ? 'on' : '' ?>" href="/dashboard/tickets">全部</a>
  <a class="<?= $tab === 'open' ? 'on' : '' ?>" href="/dashboard/tickets?tab=open">待处理</a>
  <a class="<?= $tab === 'replied' ? 'on' : '' ?>" href="/dashboard/tickets?tab=replied">已回复</a>
  <a class="<?= $tab === 'closed' ? 'on' : '' ?>" href="/dashboard/tickets?tab=closed">已关闭</a>
</div>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">还没有工单。<br>账单、实例故障或账户问题都可以在这里提交。</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>#</th><th>标题</th><th>分类</th><th>状态</th><th>更新时间</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td class="mono"><?= (int) $t['id'] ?></td>
          <td class="wrap"><a href="/dashboard/tickets/<?= (int) $t['id'] ?>"><b><?= e($t['title']) ?></b></a></td>
          <td><?php
              $cmap = ['billing' => '账务', 'instance' => '实例', 'network' => '网络', 'other' => '其他'];
              echo e($cmap[$t['category']] ?? '其他');
          ?></td>
          <td><span class="tag <?= e($t['status']) ?>"><?php
              $smap = ['open' => '待处理', 'replied' => '已回复', 'closed' => '已关闭'];
              echo e($smap[$t['status']] ?? $t['status']);
          ?></span></td>
          <td class="mono"><?= e(dt($t['updated_at'])) ?></td>
          <td><div class="actions"><a class="btn btn-ghost btn-sm" href="/dashboard/tickets/<?= (int) $t['id'] ?>">查看</a></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php if (!empty($rows)): ?>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, $tab !== '' ? ['tab' => $tab] : []); ?>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
