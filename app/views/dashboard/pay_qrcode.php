<?php /* NAT Cloud - 扫码支付页（支付宝当面付 / 微信官方 Native 共用） */ ?>
<?php ob_start(); ?>
<?php $isAlipay = strpos((string) $payName, '支付宝') !== false; ?>
<div class="qr-wrap">
  <div class="card qr-card">
    <div class="qr-brand<?= $isAlipay ? ' qr-alipay' : ' qr-wx' ?>">
      <span class="qr-brand-dot"></span><?= e($payName) ?>
    </div>
    <div class="qr-amount">¥<?= e(number_format((float) $amount, 2, '.', '')) ?></div>
    <div class="qr-hint">请使用<?= $isAlipay ? '支付宝' : '微信' ?>「扫一扫」扫描下方二维码完成付款</div>
    <div class="qr-box" id="qrBox" data-qr="<?= e($qr) ?>"></div>
    <div class="qr-meta">订单号 <span class="mono"><?= e($orderNo) ?></span></div>
    <div class="qr-status" id="qrStatus">等待支付中<span class="qr-dots"></span>（二维码 15 分钟内有效）</div>
    <a class="btn btn-ghost btn-sm" href="/dashboard/wallet">返回钱包</a>
  </div>
</div>
<script src="/assets/js/qrcode.min.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/qrcode.min.js') ?>"></script>
<script>
(function () {
  var box = document.getElementById("qrBox");
  var data = box.getAttribute("data-qr");
  if (box && data) {
    new QRCode(box, { text: data, width: 220, height: 220, colorDark: "#111318", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M });
  }
  var statusEl = document.getElementById("qrStatus");
  var tries = 0, done = false;
  var timer = setInterval(function () {
    if (done) { clearInterval(timer); return; }
    if (++tries > 300) {
      done = true;
      statusEl.innerHTML = '<b style="color:var(--warn);">二维码已超时失效，请返回钱包重新发起充值。</b>';
      clearInterval(timer);
      return;
    }
    fetch("/dashboard/wallet/order-status?no=<?= e($orderNo) ?>").then(function (r) { return r.json(); }).then(function (d) {
      if (d.paid) {
        done = true;
        statusEl.innerHTML = '<b style="color:var(--ok);">支付成功，正在跳转…</b>';
        setTimeout(function () { location.href = "/dashboard/wallet?paid=1"; }, 1200);
      } else if (d.gone) {
        done = true;
        statusEl.innerHTML = '<b style="color:var(--danger);">订单已失效，请重新发起充值。</b>';
      }
    }).catch(function () {});
  }, 3000);
})();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
