<?php /* NAT Cloud - 管理后台：工单列表 */ ?>
<?php ob_start(); ?>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">暂无工单</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>#</th><th>用户</th><th>标题</th><th>分类</th><th>状态</th><th>更新</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td class="mono"><?= (int) $t['id'] ?></td>
          <td><?= e($t['username'] ?? '-') ?></td>
          <td class="wrap"><a href="/admin/tickets/<?= (int) $t['id'] ?>"><b><?= e($t['title']) ?></b></a></td>
          <td><?php
              $cmap = ['billing' => '账务', 'instance' => '实例', 'network' => '网络', 'other' => '其他'];
              echo e($cmap[$t['category']] ?? '其他');
          ?></td>
          <td><span class="tag <?= e($t['status']) ?>"><?php
              $smap = ['open' => '待处理', 'replied' => '已回复', 'closed' => '已关闭'];
              echo e($smap[$t['status']] ?? $t['status']);
          ?></span></td>
          <td class="mono"><?= e(dt($t['updated_at'])) ?></td>
          <td><div class="actions">
            <a class="btn btn-ghost btn-sm" href="/admin/tickets/<?= (int) $t['id'] ?>">处理</a>
            <form class="inline-form" method="post" action="/admin/tickets/<?= (int) $t['id'] ?>/delete" data-confirm="删除后工单与全部回复将无法恢复，确认删除？"><?= csrf_field() ?>
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php if (!empty($rows)): ?>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, []); ?>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
