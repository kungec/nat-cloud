<?php /* NAT Cloud - USDT(TRC20) 站内支付页（不跳转外部） */ ?>
<?php $backUrl = '/dashboard/wallet'; $backText = '钱包'; ?>
<?php ob_start(); ?>
<div class="card" style="max-width:460px;margin:24px auto 0;">
  <h3>USDT · TRC20 支付</h3>
  <div style="display:flex;justify-content:center;margin:16px 0 4px;">
    <div id="usdtQr" style="background:#fff;padding:10px;border-radius:10px;border:1px solid var(--border);"></div>
  </div>
  <div class="kv" style="margin-top:12px;">
    <span class="k">订单号</span><span class="mono"><?= e($order['order_no']) ?></span>
    <span class="k">应付金额</span><span class="mono" style="color:var(--ok);font-weight:700;"><?= e(rtrim(rtrim(number_format((float) $order['usdt_amount'], 6, '.', ''), '0'), '.')) ?> USDT</span>
    <span class="k">充值金额</span><span>¥<?= e(money($order['amount'])) ?></span>
  </div>
  <div class="field" style="margin-top:12px;">
    <label>收款地址（TRC20，点击复制）</label>
    <div class="pw-box">
      <span class="mono pw-text" id="usdtAddr" data-pw="<?= e($addr) ?>" title="点击复制" style="font-size:12.5px;word-break:break-all;"><?= e($addr) ?></span>
      <span class="pw-tip" id="addrTip">点击复制</span>
    </div>
  </div>
  <div class="task-banner" id="usdtWait" style="display:none;">
    <?= nc_icon('clock', 16) ?><span>已检测到您的转账，正在确认区块链确认数，一般 1-2 分钟内到账…</span>
  </div>
  <div class="task-banner" id="usdtExpire" style="display:none;border-color:rgba(240,97,109,0.4);color:var(--danger);background:rgba(240,97,109,0.06);">
    ⚠ 订单已超时。如已完成转账请联系管理员提供订单号人工处理；也可返回重新下单。
  </div>
  <p class="desc" style="font-size:12.5px;margin-top:12px;color:var(--text-faint);">
    请使用支持 TRC20 的钱包（如 TronLink / OKWeb3）向上述地址转账<b>图示精确金额</b>，
    系统自动监控区块链到账并实时入账；金额不一致将无法自动到账。
  </p>
  <div style="display:flex;gap:10px;margin-top:8px;">
    <a class="btn btn-ghost btn-sm" href="/dashboard/wallet?paid=1">我已完成支付</a>
    <a class="btn btn-ghost btn-sm" href="/dashboard/wallet">返回钱包</a>
  </div>
</div>
<script src="/assets/js/qrcode.min.js?v=<?= is_file(NC_ROOT . '/public/assets/js/qrcode.min.js') ? filemtime(NC_ROOT . '/public/assets/js/qrcode.min.js') : '1' ?>"></script>
<script>
(function () {
  var addr = <?= json_encode($addr) ?>;
  var box = document.getElementById("usdtQr");
  if (box && window.QRCode) {
    new QRCode(box, { text: addr, width: 172, height: 172, colorDark: "#111318", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M });
  }
  /* 地址复制 */
  var addrEl = document.getElementById("usdtAddr");
  var tip = document.getElementById("addrTip");
  if (addrEl) {
    addrEl.addEventListener("click", function () {
      var done = function () {
        if (tip) { tip.textContent = "已复制 ✓"; tip.classList.add("ok"); setTimeout(function () { tip.textContent = "点击复制"; tip.classList.remove("ok"); }, 1600); }
      };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(addrEl.getAttribute("data-pw")).then(done);
      else { var t = document.createElement("textarea"); t.value = addrEl.getAttribute("data-pw"); document.body.appendChild(t); t.select(); try { document.execCommand("copy"); done(); } catch (e) {} document.body.removeChild(t); }
    });
  }
  /* 到账轮询 */
  var orderNo = <?= json_encode($order['order_no']) ?>;
  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute("content") : "";
  var waited = 0, confirmed = false;
  function poll() {
    if (confirmed || waited >= 2300) return;
    waited += 8;
    fetch("/dashboard/wallet/usdt-check", {
      method: "POST", credentials: "include",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "order_no=" + encodeURIComponent(orderNo) + "&_csrf=" + encodeURIComponent(csrf)
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.paid) {
        confirmed = true;
        document.getElementById("usdtWait").style.display = "none";
        location.href = "/dashboard/wallet?paid=1";
      } else if (j.expired) {
        document.getElementById("usdtExpire").style.display = "flex";
      }
    }).catch(function () {});
    setTimeout(poll, 8000);
  }
  if (document.querySelector('meta[name="csrf-token"]')) setTimeout(poll, 3000);
})();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
