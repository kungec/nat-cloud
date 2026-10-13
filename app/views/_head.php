<?php /* NAT Cloud - 页面头部（前台/认证页通用） */
$siteAccent = (string) setting('site_accent', 'default');
?>
<!DOCTYPE html>
<html lang="zh-CN"<?php if (preg_match('~^(ocean|forest|violet|sunset|rose)$~', $siteAccent)) echo ' data-accent="' . e($siteAccent) . '"'; ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? 'NAT Cloud') ?></title>
<?php
$assetV = function (string $rel): string {
    $full = NC_ROOT . '/public' . $rel;
    return $rel . '?v=' . (is_file($full) ? filemtime($full) : '1');
};
?>
<link rel="icon" type="image/x-icon" href="<?= $assetV('/assets/img/favicon.ico') ?>">
<link rel="stylesheet" href="<?= $assetV('/assets/css/main.css') ?>">
</head>
<body>
<div id="ncProgress"></div>
<div class="modal-mask" id="ncConfirm">
  <div class="modal confirm-modal">
    <div class="cfm-ico"><?= nc_icon('warn', 24) ?></div>
    <h3 id="cfmMsg">确认执行该操作？</h3>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" id="cfmCancel">取消</button>
      <button class="btn btn-danger" type="button" id="cfmOk">确认</button>
    </div>
  </div>
</div>
<?php if (empty($noTopnav)): ?>
<nav class="topnav">
  <div class="container topnav-inner">
    <a class="brand" href="/"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo" style="width:26px;height:26px;object-fit:contain;display:block;margin:0;"></span><span class="brand-name"><?= e(setting('site_name', 'NAT Cloud')) ?></span></a>
    <div class="topnav-links" <?php if (!empty($navMinimal)) echo 'style="display:none;"'; ?>>
      <a href="/#features">特性</a>
      <a href="/#pricing">套餐</a>
      <a href="/#faq">常见问题</a>
      <a href="/news">公告</a>
      <a href="/status">状态</a>
    </div>
    <div class="topnav-actions">
      <div class="user-menu" style="margin-right:4px;">
        <button type="button" class="lang-chip" data-dropdown="langPanel"><?= nc_icon('lang', 17) ?><span class="lang-text"><?= lang() === 'en' ? 'English' : '简体中文' ?></span><?= nc_icon('chevron', 12) ?></button>
        <div class="user-menu-panel" id="langPanel" hidden style="min-width:150px;">
          <button class="um-item <?= lang() === 'zh' ? 'on' : '' ?>" type="button" data-setlang="zh"><span class="ico"></span>简体中文</button>
          <button class="um-item <?= lang() === 'en' ? 'on' : '' ?>" type="button" data-setlang="en"><span class="ico"></span>English</button>
        </div>
      </div>
      <button type="button" class="icon-btn" id="themeToggle" title="<?= e(lang() === 'en' ? 'Toggle appearance' : '切换外观') ?>"><?= nc_icon('moon', 18) ?></button>
      <?php $u = auth_user(); ?>
      <?php if ($u): ?>
        <div class="user-menu">
          <button type="button" class="user-menu-btn" data-dropdown="userMenuPanel">
            <img class="avatar-img" src="/assets/img/avatar.png?v=<?= filemtime(NC_ROOT . '/public/assets/img/avatar.png') ?>" alt="">
            <span class="um-name"><?= e($u['username']) ?></span>
            <span class="um-caret"><?= nc_icon('chevron', 14) ?></span>
          </button>
          <div class="user-menu-panel" id="userMenuPanel" hidden>
            <div class="um-head">
              <img class="avatar-img" src="/assets/img/avatar.png?v=<?= filemtime(NC_ROOT . '/public/assets/img/avatar.png') ?>" alt="">
              <div class="um-id">
                <b><?= e($u['username']) ?></b>
                <span><?= e($u['email']) ?></span>
              </div>
            </div>
            <a class="um-item" href="/dashboard"><span class="ico"><?= nc_icon('grid', 16) ?></span>控制台</a>
            <a class="um-item" href="/dashboard/settings"><span class="ico"><?= nc_icon('gear', 16) ?></span>设置</a>
            <form method="post" action="/auth/logout">
              <?= csrf_field() ?>
              <button class="um-item um-logout" type="submit"><span class="ico"><?= nc_icon('logout', 16) ?></span>退出登录</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="/auth/login">登录</a>
        <a class="btn btn-primary btn-sm" href="/auth/register">免费注册</a>
      <?php endif; ?>
      <button type="button" class="nav-hamburger" id="navHamburger" aria-label="菜单"><?= nc_icon('menu', 18) ?></button>
    </div>
  </div>
  <div class="mobile-nav-panel" id="mobileNavPanel">
    <a href="/#features">特性</a>
    <a href="/#pricing">套餐</a>
    <a href="/#faq">常见问题</a>
    <a href="/news">公告</a>
    <a href="/status">状态</a>
    <?php if (empty($u)): ?>
    <div class="mobile-auth-row">
      <a class="btn btn-ghost btn-sm" style="flex:1;" href="/auth/login">登录</a>
      <a class="btn btn-primary btn-sm" style="flex:1;" href="/auth/register">免费注册</a>
    </div>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>
<div class="flashes">
  <?php foreach (flash_take() as $f): ?>
    <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
</div>
