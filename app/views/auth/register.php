<?php /* NAT Cloud - 注册页 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '免费注册') ?> · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
<link rel="stylesheet" href="/assets/css/main.css?v=<?= filemtime(NC_ROOT . '/public/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <nav class="topnav">
    <div class="container topnav-inner">
      <a class="brand" href="/"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo"></span><?= e(setting('site_name', 'NAT Cloud')) ?></a>
      <div class="topnav-actions" style="margin-left:auto;">
        <a class="btn btn-ghost btn-sm" href="/">返回首页</a>
      </div>
    </div>
  </nav>
  <div class="flashes">
    <?php foreach (flash_take() as $f): ?>
      <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="auth-main">
    <div class="auth-card">
      <div class="card">
        <h1>创建账号</h1>
        <p class="sub">注册即可开通你的第一台 NAT 主机<?php $regRef = $refCode ?? ($_SESSION['reg_ref'] ?? ''); if ($regRef !== ''): ?> · 邀请码 <b><?= e($regRef) ?></b> 已应用<?php endif; ?></p>
        <form method="post" action="/auth/register">
          <?= csrf_field() ?>
          <input type="hidden" name="ref" value="<?= e($refCode ?? ($_SESSION['reg_ref'] ?? '')) ?>">
          <div class="field">
            <label>用户名</label>
            <input class="input" type="text" name="username" value="<?= e($old['username'] ?? '') ?>" placeholder="字母开头，3-32 位" autocomplete="username" required>
          </div>
          <div class="field">
            <label>邮箱</label>
            <input class="input" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="you@example.com" autocomplete="email" required>
          </div>
          <div class="field">
            <label>密码</label>
            <input class="input" type="password" name="password" placeholder="至少 8 位" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label>确认密码</label>
            <input class="input" type="password" name="password2" autocomplete="new-password" required>
          </div>
          <?php if (captcha_available()): ?>
          <div class="field">
            <label>图形验证码</label>
            <div style="display:flex;gap:10px;align-items:center;">
              <input class="input" style="flex:1;" type="text" name="captcha" maxlength="6" placeholder="不区分大小写" required>
              <img src="/auth/captcha?_=reg" id="captchaImg" alt="验证码" title="点击刷新" style="height:36px;border-radius:8px;cursor:pointer;border:1px solid var(--border);background:#f4f5f7;">
            </div>
          </div>
          <?php endif; ?>
          <button class="btn btn-primary btn-block btn-lg" type="submit">免费注册</button>
        </form>
      </div>
      <div class="auth-foot">
        已有账号？<a href="/auth/login">直接登录</a>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
