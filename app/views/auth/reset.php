<?php /* NAT Cloud - 重置密码（凭邮件链接中的 token） */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>设置新密码 · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
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
        <h1>设置新密码</h1>
        <p class="sub">为账号 <b><?= e($resetEmail ?? '') ?></b> 设置新密码</p>
        <form method="post" action="/auth/reset">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($resetToken ?? '') ?>">
          <div class="field">
            <label>新密码（至少 8 位）</label>
            <input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label>确认新密码</label>
            <input class="input" type="password" name="password2" minlength="8" autocomplete="new-password" required>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit">重置密码</button>
        </form>
        <div class="auth-foot">
          <a href="/auth/login">返回登录</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
