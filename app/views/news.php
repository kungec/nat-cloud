<?php /* NAT Cloud - 公告列表 */ ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<section class="section" style="padding:56px 0;">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="margin-bottom:26px;">
      <div class="kicker">News</div>
      <h2>公告动态</h2>
    </div>
    <div class="table-wrap">
      <?php if (empty($rows)): ?>
        <div class="empty">暂无公告</div>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <a class="news-item" href="/news/<?= (int) $r['id'] ?>">
          <div class="t"><?= e($r['title']) ?></div>
          <div class="d"><?= e(dt($r['created_at'])) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php include NC_ROOT . '/app/views/_foot.php'; ?>
