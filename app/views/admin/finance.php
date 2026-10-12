<?php /* NAT Cloud - 管理后台：财务（充值确认 / 订单） */ ?>
<?php ob_start(); ?>
<div class="tabs">
  <a class="<?= $tab === 'topups' ? 'on' : '' ?>" href="/admin/finance">充值订单</a>
  <a class="<?= $tab === 'orders' ? 'on' : '' ?>" href="/admin/finance?tab=orders">消费订单</a>
</div>
<?php if ($tab === 'topups'): ?>
  <form method="post" action="/admin/finance/clear" style="display:flex;justify-content:flex-end;margin-bottom:14px;" data-confirm="确认清空全部充值单记录？此操作不可恢复（不影响已到账余额）。">
    <?= csrf_field() ?>
    <input type="hidden" name="clear_type" value="topups">
    <button class="btn btn-danger btn-sm" type="submit">🗑 一键清空充值单</button>
  </form>
  <div class="table-wrap">
    <?php if (empty($topups)): ?>
      <div class="empty">暂无充值单</div>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>单号</th><th>用户</th><th>金额</th><th>状态</th><th>创建</th><th>到账</th><th style="text-align:right;">操作</th></tr></thead>
        <tbody>
        <?php foreach ($topups as $o): ?>
          <tr>
            <td class="mono"><?= e($o['order_no']) ?></td>
            <td><a href="/admin/users/<?= (int) $o['uid'] ?>"><?= e($o['username'] ?? '-') ?></a></td>
            <td>¥<?= e(money($o['amount'])) ?></td>
            <td><span class="tag <?= e($o['status']) ?>"><?php
                $smap = ['pending' => '待确认', 'paid' => '已到账', 'cancelled' => '已取消'];
                echo e($smap[$o['status']] ?? $o['status']);
            ?></span></td>
            <td class="mono"><?= e(dt($o['created_at'])) ?></td>
            <td class="mono"><?= e(dt($o['paid_at'])) ?></td>
            <td>
              <div class="actions">
                <?php if ($o['status'] === 'pending'): ?>
                <form class="inline-form" method="post" action="/admin/topups/<?= (int) $o['id'] ?>/confirm"><?= csrf_field() ?>
                  <button class="btn btn-primary btn-sm" type="submit" data-confirm="确认该笔充值已收款并到账？">确认到账</button>
                </form>
                <?php endif; ?>
                <form class="inline-form" method="post" action="/admin/orders/<?= (int) $o['id'] ?>/delete" data-confirm="删除该充值单记录？"><?= csrf_field() ?>
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
  <?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, ['tab' => 'topups']); ?>
<?php else: ?>
  <form method="post" action="/admin/finance/clear" style="display:flex;justify-content:flex-end;margin-bottom:14px;" data-confirm="确认清空全部消费订单记录？此操作不可恢复。">
    <?= csrf_field() ?>
    <input type="hidden" name="clear_type" value="orders">
    <button class="btn btn-danger btn-sm" type="submit">🗑 一键清空订单</button>
  </form>
  <div class="table-wrap">
    <?php if (empty($orders)): ?>
      <div class="empty">暂无订单</div>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>单号</th><th>用户</th><th>类型</th><th>内容</th><th>金额</th><th>状态</th><th>时间</th><th style="text-align:right;">操作</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="mono"><?= e($o['order_no']) ?></td>
            <td><?= e($o['username'] ?? '-') ?></td>
            <td><?php
                $tmap = ['new' => '新购（包月）', 'new_hourly' => '新购（按量）', 'renew' => '续费'];
                echo e($tmap[$o['type']] ?? $o['type']);
            ?></td>
            <td><?= e($o['title']) ?></td>
            <td>¥<?= e(money($o['amount'])) ?></td>
            <td><span class="tag <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
            <td class="mono"><?= e(dt($o['created_at'])) ?></td>
            <td>
              <div class="actions">
                <form class="inline-form" method="post" action="/admin/orders/<?= (int) $o['id'] ?>/delete" data-confirm="删除该订单记录？"><?= csrf_field() ?>
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
  <?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, ['tab' => 'orders']); ?>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
