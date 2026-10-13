<?php /* NAT Cloud - 通用 HTML 静态页（条款/隐私） */ ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<section class="section" style="padding:56px 0;">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="margin-bottom:26px;">
      <div class="kicker">Legal</div>
      <h2><?= e($pageTitle) ?></h2>
    </div>
    <div class="card" style="padding:26px;">
      <?php if (trim((string) $html) === ''): ?>
        <div class="empty">内容尚未配置，请稍后再来。</div>
      <?php else: ?>
        <div style="font-size:14px;line-height:1.9;color:var(--text-dim);"><?= $html ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include NC_ROOT . '/app/views/_foot.php'; ?>
