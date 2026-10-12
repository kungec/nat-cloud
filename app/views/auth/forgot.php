<?php /* NAT Cloud - 找回密码（SMTP 可用：自助重置；未配置：联系管理员提示） */ ?>
<?php $selfService = smtp_ready(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>找回密码 · <?= e(setting('site_name', 'NAT Cloud')) ?></title>
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
        <?php if ($selfService): ?>
        <h1>找回密码</h1>
        <p class="sub">输入注册邮箱，我们会发送重置链接</p>
        <form method="post" action="/auth/forgot">
          <?= csrf_field() ?>
          <div class="field">
            <label>注册邮箱</label>
            <input class="input" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required>
          </div>
          <?php if (captcha_available()): ?>
          <div class="field">
            <label>图形验证码</label>
            <div style="display:flex;gap:10px;align-items:center;">
              <input class="input" style="flex:1;" type="text" name="captcha" maxlength="6" placeholder="不区分大小写" required>
              <img src="/auth/captcha?_=fp" id="captchaImg" alt="验证码" title="点击刷新" style="height:36px;border-radius:8px;cursor:pointer;border:1px solid var(--border);background:#f4f5f7;">
            </div>
          </div>
          <?php endif; ?>
          <button class="btn btn-primary btn-block btn-lg" type="submit">发送重置链接</button>
        </form>
        <div class="auth-foot">
          <a href="/auth/login">返回登录</a>
        </div>
        <?php else: ?>
        <h1>找回密码</h1>
        <p class="sub">暂未开通自助找回通道</p>
        <p class="desc">请使用注册邮箱向管理员提交工单或在网站底部联系方式中申请重置密码，核实身份后会为你生成临时密码。</p>
        <a class="btn btn-primary btn-block" href="/auth/login">返回登录</a>
        <?php endif; ?>
      </div>
      <div class="auth-foot">
        还没有账号？<a href="/auth/register">免费注册</a>
      </div>
    </div>
  </div>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
