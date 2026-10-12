<?php /* NAT Cloud - 账单明细 */ ?>
<?php ob_start(); ?>
<div class="tabs">
  <a class="<?= $tab === 'tx' ? 'on' : '' ?>" href="/dashboard/billing">余额流水</a>
  <a class="<?= $tab === 'topups' ? 'on' : '' ?>" href="/dashboard/billing?tab=topups">充值记录</a>
</div>

<?php if ($tab === 'tx'): ?>
  <div class="filter-row">
    <a class="<?= $type === '' ? 'on' : '' ?>" href="/dashboard/billing">全部</a>
    <a class="<?= $type === 'hourly' ? 'on' : '' ?>" href="/dashboard/billing?type=hourly">扣费</a>
    <a class="<?= $type === 'monthly' ? 'on' : '' ?>" href="/dashboard/billing?type=monthly">包年包月</a>
    <a class="<?= $type === 'topup' ? 'on' : '' ?>" href="/dashboard/billing?type=topup">充值</a>
    <a class="<?= $type === 'adjust' ? 'on' : '' ?>" href="/dashboard/billing?type=adjust">调整</a>
    <a class="<?= $type === 'refund' ? 'on' : '' ?>" href="/dashboard/billing?type=refund">退款</a>
  </div>
  <div class="table-wrap">
    <?php if (empty($rows)): ?>
      <div class="empty">暂无流水</div>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>时间</th><th>类型</th><th>说明</th><th style="text-align:right;">变动</th><th style="text-align:right;">余额</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono" style="white-space:nowrap;"><?= e(dt($r['created_at'])) ?></td>
            <td><?php
                $tmap = ['topup' => '充值', 'hourly' => '按量扣费', 'monthly' => '包年包月', 'adjust' => '调整', 'refund' => '退款', 'traffic' => '流量费', 'storage' => '存储费'];
                echo e($tmap[$r['type']] ?? $r['type']);
            ?></td>
            <td class="wrap"><?= e($r['remark']) ?></td>
            <td style="text-align:right;" class="<?= (float) $r['amount'] >= 0 ? 'amt-in' : 'amt-out' ?>"><?= (float) $r['amount'] >= 0 ? '+' : '' ?><?= e(money($r['amount'])) ?></td>
            <td style="text-align:right;" class="mono"><?= e(money($r['balance_after'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <?php if (empty($rows)): ?>
      <div class="empty">暂无充值记录</div>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>单号</th><th>金额</th><th>状态</th><th>创建时间</th><th>到账时间</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono"><?= e($r['order_no']) ?></td>
            <td>¥<?= e(money($r['amount'])) ?></td>
            <td><span class="tag <?= e($r['status']) ?>"><?php
                $smap = ['pending' => '待确认', 'paid' => '已到账', 'cancelled' => '已取消'];
                echo e($smap[$r['status']] ?? $r['status']);
            ?></span></td>
            <td class="mono"><?= e(dt($r['created_at'])) ?></td>
            <td class="mono"><?= e(dt($r['paid_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
