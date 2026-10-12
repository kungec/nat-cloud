<?php /* NAT Cloud - 管理后台布局壳（对齐前台顶栏风格） */ ?>
<?php $a = auth_admin(); ?>
<?php $noTopnav = true; ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<nav class="topnav">
  <div class="container topnav-inner">
    <a class="brand" href="/admin"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo" style="width:26px;height:26px;object-fit:contain;display:block;margin:0;"></span>管理后台 <span class="admin-badge">ADMIN</span></a>
    <div class="topnav-actions">
      <div class="user-menu">
        <button type="button" class="user-menu-btn" data-dropdown="adminMenuPanel">
          <img class="avatar-img" src="/assets/img/avatar.png?v=<?= filemtime(NC_ROOT . '/public/assets/img/avatar.png') ?>" alt="">
          <span class="um-name"><?= e($a['username']) ?></span>
          <span class="um-caret"><?= nc_icon('chevron', 12) ?></span>
        </button>
        <div class="user-menu-panel" id="adminMenuPanel" hidden style="min-width:150px;">
          <a class="um-item" href="/admin/profile"><span class="ico"><?= nc_icon('user') ?></span>账号设置</a>
          <form method="post" action="/admin/logout">
            <?= csrf_field() ?>
            <button class="um-item um-logout" type="submit"><span class="ico"><?= nc_icon('logout', 16) ?></span>退出后台</button>
          </form>
        </div>
      </div>
      <button type="button" class="nav-hamburger" id="admHamburger" aria-label="菜单"><?= nc_icon('menu', 18) ?></button>
    </div>
  </div>
  <div class="mobile-nav-panel" id="admMobilePanel">
    <?php $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
    <a class="<?= $cur === '/admin' ? 'on' : '' ?>" href="/admin">仪表盘</a>
    <a class="<?= strpos($cur, '/admin/users') === 0 ? 'on' : '' ?>" href="/admin/users">用户</a>
    <a class="<?= strpos($cur, '/admin/instances') === 0 ? 'on' : '' ?>" href="/admin/instances">实例</a>
    <a class="<?= strpos($cur, '/admin/plans') === 0 ? 'on' : '' ?>" href="/admin/plans">套餐</a>
    <a class="<?= strpos($cur, '/admin/nodes') === 0 ? 'on' : '' ?>" href="/admin/nodes">节点管理</a>
    <a class="<?= strpos($cur, '/admin/finance') === 0 ? 'on' : '' ?>" href="/admin/finance">财务</a>
    <a class="<?= $cur === '/admin/transactions' ? ' on' : '' ?>" href="/admin/transactions">流水</a>
    <a class="<?= strpos($cur, '/admin/tickets') === 0 ? ' on' : '' ?>" href="/admin/tickets">工单</a>
    <a class="<?= strpos($cur, '/admin/news') === 0 ? ' on' : '' ?>" href="/admin/news">公告</a>
    <a class="<?= strpos($cur, '/admin/kyc') === 0 ? ' on' : '' ?>" href="/admin/kyc">实名审核</a>
    <a class="<?= strpos($cur, '/admin/settings') === 0 ? ' on' : '' ?>" href="/admin/settings">系统设置</a>
  </div>
</nav>
<div class="shell">
  <aside class="sidebar sb-admin">
    <nav class="side-nav">
      <a class="<?= $cur === '/admin' ? ' on' : '' ?>" href="/admin"><span class="ico"><?= nc_icon('grid') ?></span>仪表盘</a>
      <a class="<?= strpos($cur, '/admin/users') === 0 ? ' on' : '' ?>" href="/admin/users"><span class="ico"><?= nc_icon('user') ?></span>用户</a>
      <a class="<?= strpos($cur, '/admin/instances') === 0 ? ' on' : '' ?>" href="/admin/instances"><span class="ico"><?= nc_icon('layers') ?></span>实例</a>
      <a class="<?= strpos($cur, '/admin/plans') === 0 ? ' on' : '' ?>" href="/admin/plans"><span class="ico"><?= nc_icon('tag') ?></span>套餐</a>
      <a class="<?= strpos($cur, '/admin/nodes') === 0 ? ' on' : '' ?>" href="/admin/nodes"><span class="ico"><?= nc_icon('headset') ?></span>节点管理</a>
      <a class="<?= strpos($cur, '/admin/finance') === 0 ? ' on' : '' ?>" href="/admin/finance"><span class="ico"><?= nc_icon('wallet') ?></span>财务</a>
      <a class="<?= $cur === '/admin/transactions' ? ' on' : '' ?>" href="/admin/transactions"><span class="ico"><?= nc_icon('receipt') ?></span>流水</a>
      <a class="<?= strpos($cur, '/admin/tickets') === 0 ? ' on' : '' ?>" href="/admin/tickets"><span class="ico"><?= nc_icon('headset') ?></span>工单</a>
      <a class="<?= strpos($cur, '/admin/news') === 0 ? ' on' : '' ?>" href="/admin/news"><span class="ico"><?= nc_icon('megaphone') ?></span>公告</a>
      <a class="<?= strpos($cur, '/admin/kyc') === 0 ? ' on' : '' ?>" href="/admin/kyc"><span class="ico"><?= nc_icon('shield') ?></span>实名审核</a>
      <a class="<?= strpos($cur, '/admin/settings') === 0 ? ' on' : '' ?>" href="/admin/settings"><span class="ico"><?= nc_icon('gear') ?></span>系统设置</a>
    </nav>
  </aside>
  <main class="main">
    <div class="flashes">
      <?php foreach (flash_take() as $f): ?>
        <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="main-head">
      <div>
        <?php if (!empty($backUrl)): ?><a class="back-link" href="<?= e($backUrl) ?>">← <?= e($backText ?? '返回') ?></a><?php endif; ?>
        <h1><?= e($title ?? '') ?></h1>
        <?php if (!empty($subTitle)): ?><div class="sub"><?= e($subTitle) ?></div><?php endif; ?>
      </div>
    </div>
    <?= $content ?>
  </main>
</div>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
