<?php /* NAT Cloud - 重发验证邮件 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '重发验证邮件') ?> · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
<link rel="stylesheet" href="/assets/css/main.css?v=<?= filemtime(NC_ROOT . '/public/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <nav class="topnav">
    <div class="container topnav-inner">
      <a class="brand" href="/"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo"></span><?= e(setting('site_name', 'NAT Cloud')) ?></a>
      <div class="topnav-actions" style="margin-left:auto;">
        <a class="btn btn-ghost btn-sm" href="/auth/login">返回登录</a>
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
        <h1>重发验证邮件</h1>
        <p class="sub">输入注册邮箱，系统将重新发送验证链接（每小时最多 3 次）</p>
        <form method="post" action="/auth/resend">
          <?= csrf_field() ?>
          <div class="field">
            <label>注册邮箱</label>
            <input class="input" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit">重新发送</button>
        </form>
      </div>
      <div class="auth-foot">
        已验证？<a href="/auth/login">直接登录</a>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
