<?php /* NAT Cloud - 钱包（在线充值 + 人工通道） */ ?>
<?php ob_start(); ?>
<?php $u = auth_user(); ?>
<?php
$stmt = db()->prepare("SELECT COALESCE(SUM(hourly_price),0) FROM instances WHERE uid = ? AND status = 'running' AND billing_mode = 'hourly'");
$stmt->execute([(int) $u['id']]);
$hTotal = (float) $stmt->fetchColumn();
$channels = is_array($channels ?? null) ? $channels : [];
?>
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat"><div class="l">余额</div><div class="v">¥<?= e(money($u['balance'])) ?></div></div>
  <div class="stat"><div class="l">每小时消耗</div><div class="v">¥<?= e(money4($hTotal)) ?></div></div>
  <div class="stat"><div class="l">预计可运行</div><div class="v"><?= $hTotal > 0 ? (int) floor($u['balance'] / max(0.0001, $hTotal)) . ' 小时' : '暂无消耗' ?></div></div>
</div>

<?php if (!empty($paid)): ?>
  <div class="card" style="max-width:520px;border-color:rgba(56,224,166,0.35);">
    <div style="display:flex;gap:10px;align-items:center;font-size:14px;">
      <?= nc_icon('warn', 18) ?>支付已提交，到账后余额自动更新（通常几秒内）；如未到账请稍后刷新。
    </div>
  </div>
<?php endif; ?>

<?php if ($channels): ?>
<div class="card" style="max-width:520px;">
  <h3>在线充值</h3>
  <p class="desc" style="font-size:13px;">选择支付方式，付款成功后余额自动到账。单次 10.00 - 10000.00 元。</p>
  <form method="post" action="/dashboard/wallet/topup">
    <?= csrf_field() ?>
    <div class="quick-amounts">
      <button type="button" data-amount="50">¥50</button>
      <button type="button" data-amount="100" class="on">¥100</button>
      <button type="button" data-amount="200">¥200</button>
      <button type="button" data-amount="500">¥500</button>
    </div>
    <div class="field">
      <label>充值金额（元）</label>
      <input class="input" type="number" step="0.01" min="10" max="10000" name="amount" value="100" required>
    </div>
    <div class="field">
      <label>支付方式</label>
      <div class="pay-channels">
        <?php foreach ($channels as $key => $name): ?>
          <label class="pay-channel">
            <input type="radio" name="pay_channel" value="<?= e($key) ?>" <?= $key === array_key_first($channels) ? 'checked' : '' ?>>
            <span><?= e($name) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit">去支付</button>
  </form>
  <p style="margin-top:14px;font-size:13px;"><a href="/dashboard/billing?tab=topups">查看充值记录 →</a></p>
</div>
<?php else: ?>
<div class="card" style="max-width:520px;">
  <h3>在线充值</h3>
  <p class="desc" style="font-size:13px;">在线支付暂未开放，请联系管理员开通。</p>
  <p style="margin-top:14px;font-size:13px;"><a href="/dashboard/billing?tab=topups">查看充值记录 →</a></p>
</div>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
