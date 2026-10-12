<?php /* NAT Cloud - 管理员登录页 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理员登录 · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
<link rel="stylesheet" href="/assets/css/main.css?v=<?= filemtime(NC_ROOT . '/public/assets/css/main.css') ?>">
</head>
<body>
<div class="auth-wrap">
  <nav class="topnav">
    <div class="container topnav-inner">
      <a class="brand" href="/"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo"></span><?= e(setting('site_name', 'NAT Cloud')) ?></a>
      <div class="topnav-actions" style="margin-left:auto;">
        <span class="admin-badge" style="font-size:12px;">ADMIN</span>
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
        <h1>管理后台</h1>
        <p class="sub">仅限授权管理员访问</p>
        <form method="post" action="/admin/login">
          <?= csrf_field() ?>
          <div class="field">
            <label>账号</label>
            <input class="input" type="text" name="account" value="<?= e($old['account'] ?? '') ?>" autocomplete="username" required>
          </div>
          <div class="field">
            <label>密码</label>
            <input class="input" type="password" name="password" autocomplete="current-password" required>
          </div>
          <div class="field">
            <label>图形验证码</label>
            <div style="display:flex;gap:10px;align-items:center;">
              <input class="input" style="flex:1;" type="text" name="captcha" maxlength="6" placeholder="不区分大小写" required>
              <img src="/auth/captcha?_=admin" id="captchaImg" alt="验证码" title="点击刷新" style="height:36px;border-radius:8px;cursor:pointer;border:1px solid var(--border);background:#f4f5f7;">
            </div>
          </div>
          <button class="btn btn-primary btn-block btn-lg" type="submit">登录</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
