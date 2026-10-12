<?php /* NAT Cloud - 用户中心：总览（对齐演示站布局） */
$trayIcon = nc_icon('tray', 24);
?><?php ob_start(); ?>
<?php $u = auth_user(); ?>
<div class="stat-grid">
  <div class="stat"><div class="l">余额</div><div class="v">¥<?= e(money($u['balance'])) ?></div></div>
  <div class="stat"><div class="l">每小时消耗</div><div class="v">¥<?= e(money($hourlyTotal)) ?></div></div>
  <div class="stat"><div class="l">运行中的实例</div><div class="v"><?= (int) $running ?></div></div>
  <div class="stat"><div class="l">预计可运行</div><div class="v"><?= $runnable === null ? '暂无消耗' : (int) $runnable . ' 小时' ?></div></div>
</div>

<div class="grid grid-2">
  <div class="panel-card">
    <div class="panel-head"><b>最近的服务</b><a href="/dashboard/services">查看全部 →</a></div>
    <?php if (empty($recent)): ?>
      <div class="empty-box">
        <div class="eb-ico"><?= $trayIcon ?></div>
        <b>还没有服务</b>
        <p>从套餐列表选一个开始吧。</p>
        <a class="btn btn-light btn-sm" href="/dashboard/create">浏览套餐</a>
      </div>
    <?php else: ?>
      <div class="svc-rows">
        <?php foreach ($recent as $i): ?>
        <a class="svc-row" href="/dashboard/services/<?= (int) $i['id'] ?>">
          <span class="svc-row-ico"><?= os_icon((string) $i['os'], 20) ?></span>
          <span class="svc-row-main">
            <b><?= e($i['name']) ?></b>
            <small><?= e(os_label((string) $i['os'])) ?> · <?= e(billing_cycle_name(billing_instance_cycle($i))) ?><?= (int) $i['node_id'] > 0 ? ' · ' . e($i['node_name']) : '' ?></small>
          </span>
          <span class="tag <?= e($i['status']) ?>"><?php
              $map = ['running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费停机', 'released' => '已释放'];
              echo e($map[$i['status']] ?? $i['status']);
          ?></span>
          <span class="svc-row-exp"><?= $i['billing_mode'] === 'monthly' ? ($i['expire_at'] && $i['expire_at'] !== '0000-00-00 00:00:00' ? '到期 ' . e(dt($i['expire_at'])) : '未激活') : '¥' . e(money4($i['hourly_price'])) . '/小时' ?></span>
          <span class="svc-row-arrow"><?= nc_icon('chevron', 14) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <div class="panel-head"><b>最近流水</b><a href="/dashboard/billing">查看全部 →</a></div>
    <?php if (empty($tx)): ?>
      <div class="empty-box">
        <div class="eb-ico"><?= $trayIcon ?></div>
        <b>暂无流水</b>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>时间</th><th>说明</th><th style="text-align:right;">金额</th></tr></thead>
          <tbody>
          <?php foreach ($tx as $t): ?>
            <tr>
              <td class="mono" style="white-space:nowrap;"><?= e(dt($t['created_at'])) ?></td>
              <td><?= e($t['remark']) ?></td>
              <td style="text-align:right;" class="<?= (float) $t['amount'] >= 0 ? 'amt-in' : 'amt-out' ?>"><?= (float) $t['amount'] >= 0 ? '+' : '' ?><?= e(money($t['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php $content = ob_get_clean();
$title = '你好，' . (string) ($u['username'] ?? '');
$subTitle = '这是你的云资源概览。';
$headBtn = ['/dashboard/wallet', '充值', 'btn-light btn-sm'];
include __DIR__ . '/_shell.php'; ?>
