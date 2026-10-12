<?php /* NAT Cloud - 公告详情 */ ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<section class="section" style="padding:56px 0;">
  <div class="container" style="max-width:820px;">
    <a href="/news" style="font-size:13.5px;">← 返回公告列表</a>
    <h1 style="margin-top:14px;font-size:26px;"><?= e($row['title']) ?></h1>
    <div style="color:var(--text-faint);font-size:13px;margin-bottom:20px;">发布于 <?= e(dt($row['created_at'])) ?></div>
    <div class="prose"><?= e($row['content']) ?></div>
  </div>
</section>
<?php include NC_ROOT . '/app/views/_foot.php'; ?>
