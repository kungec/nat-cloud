<?php /* NAT Cloud - 验证邮件已发送提示页 */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '验证邮件') ?> · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
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
      <div class="card" style="text-align:center;">
        <h1>验证邮件已发送</h1>
        <p class="sub">我们已向 <b><?= e($email ?? '') ?></b> 发送了一封验证邮件</p>
        <p style="font-size:14px;color:var(--text-dim);line-height:1.8;margin-top:14px;">
          请登录邮箱点击邮件中的验证链接完成注册（48 小时内有效）。<br>
          若未收到，请检查垃圾邮件，或返回登录页使用「重发验证邮件」功能。
        </p>
        <a class="btn btn-primary btn-block btn-lg" style="margin-top:16px;" href="/auth/resend">没收到？重新发送</a>
      </div>
      <div class="auth-foot">
        <a href="/">返回首页</a>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
