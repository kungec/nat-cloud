<?php /* NAT Cloud - 用户中心布局壳 */ ?>
<?php $u = auth_user(); ?>
<?php $navMinimal = true; ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<div class="shell">
  <aside class="sidebar sb-main">
    <nav class="side-nav">
      <?php
      $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
      function nav_on(string $prefix, string $cur, string $exact = ''): string
      {
          if ($exact !== '' && $cur === $exact) return ' on';
          return ($prefix !== '' && strpos($cur, $prefix) === 0) ? ' on' : '';
      }
      ?>
      <a class="<?= nav_on('', $cur, '/dashboard') ?>" href="/dashboard"><span class="ico"><?= nc_icon('grid') ?></span>总览</a>
      <a class="<?= nav_on('/dashboard/services', $cur) ?>" href="/dashboard/services"><span class="ico"><?= nc_icon('layers') ?></span>我的服务</a>
      <a class="<?= nav_on('/dashboard/wallet', $cur) ?>" href="/dashboard/wallet"><span class="ico"><?= nc_icon('wallet') ?></span>钱包</a>
      <a class="<?= nav_on('/dashboard/billing', $cur) ?>" href="/dashboard/billing"><span class="ico"><?= nc_icon('receipt') ?></span>账单</a>
      <a class="<?= nav_on('/dashboard/tickets', $cur) ?>" href="/dashboard/tickets"><span class="ico"><?= nc_icon('headset') ?></span>工单</a>
      <a class="<?= nav_on('/dashboard/create', $cur) ?>" href="/dashboard/create"><span class="ico"><?= nc_icon('plus') ?></span>创建实例</a>
      <a class="<?= nav_on('/dashboard/kyc', $cur) ?>" href="/dashboard/kyc"><span class="ico"><?= nc_icon('shield') ?></span>实名认证</a>
      <a class="<?= nav_on('/dashboard/invite', $cur) ?>" href="/dashboard/invite"><span class="ico"><?= nc_icon('user') ?></span>邀请返利</a>
      <a class="<?= nav_on('/dashboard/settings', $cur) ?>" href="/dashboard/settings"><span class="ico"><?= nc_icon('gear') ?></span>设置</a>
    </nav>
    <div class="side-foot"></div>
  </aside>
  <main class="main">
    <div class="flashes">
      <?php foreach (flash_take() as $f): ?>
        <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="<?= !empty($narrowContent) ? 'narrow-col' : (isset($contentWrapClass) && $contentWrapClass !== '' ? e($contentWrapClass) : 'wide-col') ?>">
      <div class="main-head">
        <div>
          <?php if (!empty($backUrl)): ?><a class="back-link" href="<?= e($backUrl) ?>">← <?= e($backText ?? '返回') ?></a><?php endif; ?>
          <h1><?= e($title ?? '') ?></h1>
          <?php if (!empty($subTitle)): ?><div class="sub"><?= e($subTitle) ?></div><?php endif; ?>
        </div>
        <div class="topnav-actions">
          <?php if (!empty($headBtn)): ?><a class="btn <?= e(isset($headBtn[2]) ? $headBtn[2] : 'btn-ghost') ?>" href="<?= e($headBtn[0]) ?>"><?= e($headBtn[1]) ?></a><?php endif; ?>
        </div>
      </div>
      <?= $content ?>
    </div>
  </main>
</div>
<nav class="bottom-tabs">
  <?php $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
  <?php
  $btItems = [
    ['/dashboard', 'grid', '总览', 'exact'],
    ['/dashboard/services', 'layers', '我的服务', ''],
    ['/dashboard/wallet', 'wallet', '钱包', ''],
    ['/dashboard/billing', 'receipt', '账单', ''],
    ['/dashboard/tickets', 'headset', '工单', ''],
    ['/dashboard/invite', 'users', '邀请', ''],
  ];
  foreach ($btItems as $it):
    $on = ($it[3] === 'exact') ? ($cur === $it[0]) : (strpos($cur, $it[0]) === 0);
  ?>
    <a class="<?= $on ? 'on' : '' ?>" href="<?= $it[0] ?>"><span class="ico"><?= nc_icon($it[1], 20) ?></span><?= $it[2] ?></a>
  <?php endforeach; ?>
</nav>
<script src="/assets/js/main.js?v=<?= filemtime(NC_ROOT . '/public/assets/js/main.js') ?>"></script>
</body>
</html>
