<?php /* NAT Cloud - 实名认证（姓名+身份证 → 接口人脸核身 → 自动通过） */
$narrowContent = true;
?><?php ob_start(); ?>
<?php
$u = auth_user();
$kycStatus = null;
$attempts = 0;
if ($kyc) {
    $kycStatus = $kyc['status'];
    $stmt = db()->prepare('SELECT COUNT(*) FROM kyc WHERE uid = ?');
    $stmt->execute([(int) $u['id']]);
    $attempts = (int) $stmt->fetchColumn();
}
$kycPassed = $kycStatus === 'passed' || (int) $u['kyc_status'] === 2;
?>
<div class="card">
    <h3>实名认证</h3>
    <p class="desc" style="font-size:14px;">填写本人真实姓名与身份证号，提交后跳转完成人脸认证即可自动通过。</p>

    <?php if ($kycPassed): ?>
      <div class="tag approved" style="margin-top:12px;display:inline-block;">✓ 已通过实名认证</div>
      <p class="desc" style="margin-top:10px;font-size:13.5px;">认证时间：<?= e(dt($kyc['passed_at'] ?? ($kyc['created_at'] ?? ''))) ?>。</p>
    <?php elseif ($kycStatus === 'pending' && !empty($kyc['certify_id'])): ?>
      <div class="tag pending" style="margin-top:12px;display:inline-block;">待完成人脸认证</div>
      <p class="desc" style="margin-top:10px;font-size:13.5px;">提交时间：<?= e(dt($kyc['created_at'])) ?>。点击下方按钮前往人脸认证页面；<b>完成后本页会自动确认</b>（请保持本页打开）。若浏览器拦截了新窗口，请允许弹出。</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
        <a class="btn btn-light btn-sm" href="<?= e($certUrl ?? '') ?>" target="_blank" rel="noopener" <?= ($certUrl ?? '') === '' ? 'style="pointer-events:none;opacity:.5;"' : '' ?>>前往人脸认证 ↗</a>
        <button class="btn btn-ghost btn-sm" type="button" id="kycCheckNow">我已完成，立即确认</button>
      </div>
      <p class="desc" id="kycPollMsg" style="font-size:12.5px;color:var(--text-faint);margin-top:12px;">正在等待认证结果…</p>
      <script>
      (function () {
        var msg = document.getElementById("kycPollMsg");
        var tries = 0, done = false;
        function check() {
          if (done || ++tries > 600) return;
          fetch("/dashboard/kyc/check", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "_csrf=" + encodeURIComponent((document.body.innerHTML.match(/_csrf" value="([a-f0-9]+)/) || [0, ""])[1])
          }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.state === "passed") {
              done = true;
              msg.innerHTML = '<b style="color:var(--ok);">实名认证通过，正在刷新…</b>';
              setTimeout(function () { location.reload(); }, 1200);
            } else if (d.state === "error" && d.msg) {
              msg.textContent = d.msg;
            }
          }).catch(function () {});
        }
        setInterval(check, 5000);
        check();
        var now = document.getElementById("kycCheckNow");
        if (now) now.addEventListener("click", check);
      })();
      </script>
    <?php elseif ($kycStatus === 'pending'): ?>
      <div class="tag pending" style="margin-top:12px;display:inline-block;">已提交，等待审核</div>
      <p class="desc" style="margin-top:10px;font-size:13.5px;">提交时间：<?= e(dt($kyc['created_at'])) ?>。</p>
    <?php elseif ($kycStatus === 'rejected'): ?>
      <div class="tag rejected" style="margin-top:12px;display:inline-block;">审核未通过</div>
      <p class="desc" style="margin-top:10px;font-size:13.5px;">你可以核对信息后重新提交。</p>
    <?php endif; ?>

    <?php if (!$kycPassed && $kycStatus !== 'pending'): ?>
    <form method="post" action="/dashboard/kyc" style="margin-top:18px;">
      <?= csrf_field() ?>
      <div class="field">
        <label>姓名<i class="req">*</i></label>
        <input class="input" type="text" name="real_name" placeholder="请输入真实姓名" maxlength="30" required>
      </div>
      <div class="field">
        <label>身份证号<i class="req">*</i></label>
        <input class="input" type="text" name="id_card" placeholder="18 位身份证号" maxlength="18" required>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:16px;">
        <button class="btn btn-light btn-sm" type="submit" data-confirm="确认提交并前往人脸认证？信息仅用于实名核验且加密存储。">提交并开始认证</button>
        <span style="color:var(--text-dim);font-size:13.5px;">还可以核验 <?= max(0, 3 - $attempts) ?> 次</span>
      </div>
    </form>
    <?php endif; ?>

    <p class="desc" style="font-size:12.5px;color:var(--text-faint);margin-top:16px;">信息仅用于实名核验，加密存储。认证通过后，你的姓名与身份证号会保留在本站用于合规备案。</p>
  </div>
<?php $content = ob_get_clean();
$subTitle = '购买部分线路前需要完成实名认证。';
include __DIR__ . '/_shell.php'; ?>
