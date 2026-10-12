<?php /* NAT Cloud - 管理后台：余额流水 */ ?>
<?php ob_start(); ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
  <form method="post" action="/admin/transactions/clear" data-confirm="确认清空全部余额流水？此操作不可恢复且不影响用户实际余额。">
    <?= csrf_field() ?>
    <button class="btn btn-danger btn-sm" type="submit">🗑 一键清空流水</button>
  </form>
</div>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">暂无流水</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID</th><th>时间</th><th>用户</th><th>类型</th><th>说明</th><th style="text-align:right;">变动</th><th style="text-align:right;">余额</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= (int) $r['id'] ?></td>
          <td class="mono"><?= e(dt($r['created_at'])) ?></td>
          <td><a href="/admin/users/<?= (int) $r['uid'] ?>"><?= e($r['username'] ?? $r['uid']) ?></a></td>
          <td><?= e($r['type']) ?></td>
          <td class="wrap"><?= e($r['remark']) ?></td>
          <td style="text-align:right;" class="<?= (float) $r['amount'] >= 0 ? 'amt-in' : 'amt-out' ?>"><?= (float) $r['amount'] >= 0 ? '+' : '' ?><?= e(money($r['amount'])) ?></td>
          <td style="text-align:right;" class="mono"><?= e(money($r['balance_after'])) ?></td>
          <td>
            <div class="actions">
              <form class="inline-form" method="post" action="/admin/transactions/<?= (int) $r['id'] ?>/delete" data-confirm="删除该条流水记录？"><?= csrf_field() ?>
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
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, isset($search) && $search !== '' ? ['q' => $search] : []); ?>
<?php $content = ob_get_clean();
$subTitle = '流水仅为记录，删除不影响用户实际余额。';
include __DIR__ . '/_shell.php'; ?>
