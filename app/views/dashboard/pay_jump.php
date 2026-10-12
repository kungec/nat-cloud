<?php /* NAT Cloud - 码支付收银台跳转页（表单自动提交） */ ?>
<?php ob_start(); ?>
<div class="card" style="max-width:520px;margin:48px auto;">
  <h3>正在跳转到支付页面…</h3>
  <div class="sum-row"><span>订单号</span><span><?= e((string) ($payment['out_trade_no'] ?? '')) ?></span></div>
  <div class="sum-row"><span>支付金额</span><span>¥<?= e((string) ($payment['money'] ?? '')) ?></span></div>
  <div class="sum-row" style="border-bottom:0;"><span>支付方式</span><span><?= e((string) ($payment['type'] ?? '')) ?></span></div>
  <p class="desc" style="font-size:13px;margin-top:12px;">支付完成后余额自动到账；若浏览器未自动跳转，请点击下方按钮继续。</p>
  <form id="payJumpForm" method="post" action="<?= e($endpoint) ?>">
    <?php foreach ($payment as $pk => $pv): ?>
      <input type="hidden" name="<?= e((string) $pk) ?>" value="<?= e((string) $pv) ?>">
    <?php endforeach; ?>
    <button class="btn btn-primary btn-block btn-lg" type="submit">继续支付</button>
  </form>
  <p style="margin-top:14px;font-size:13px;"><a href="/dashboard/wallet">返回钱包 →</a></p>
</div>
<script>
window.addEventListener("DOMContentLoaded", function () {
  var f = document.getElementById("payJumpForm");
  if (f) setTimeout(function () { f.submit(); }, 400);
});
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
