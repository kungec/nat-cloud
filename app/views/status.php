<?php /* NAT Cloud - 服务状态页 */ ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<section class="section" style="padding:56px 0;">
  <div class="container" style="max-width:820px;">
    <div class="section-head" style="margin-bottom:26px;">
      <div class="kicker">Status</div>
      <h2>服务状态</h2>
      <p class="sub">节点每分钟向系统上报心跳；若节点显示离线，我们已在处理，请留意公告。</p>
    </div>
    <div class="table-wrap">
      <?php if (empty($nodes)): ?>
        <div class="empty">暂未接入节点</div>
      <?php else: ?>
        <table class="tbl">
          <thead><tr><th>节点</th><th>状态</th><th>最后心跳</th></tr></thead>
          <tbody>
          <?php foreach ($nodes as $n): ?>
            <tr>
              <td><?= e($n['name']) ?></td>
              <td>
                <?php if (!$n['enabled']): ?>
                  <span class="tag closed">维护下线</span>
                <?php elseif ($n['online']): ?>
                  <span class="tag run">运行正常</span>
                <?php else: ?>
                  <span class="tag closed">离线</span>
                <?php endif; ?>
              </td>
              <td class="mono" style="font-size:12.5px;color:var(--text-dim);"><?= e($n['last_seen'] !== '' ? dt($n['last_seen']) : '从未上报') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="section-head" style="margin:34px 0 20px;">
      <div class="kicker">News</div>
      <h2>最新公告</h2>
    </div>
    <div class="table-wrap">
      <?php if (empty($news)): ?>
        <div class="empty">暂无公告</div>
      <?php endif; ?>
      <?php foreach ($news as $r): ?>
        <a class="news-item" href="/news/<?= (int) $r['id'] ?>">
          <div class="t"><?= e($r['title']) ?></div>
          <div class="d"><?= e(dt($r['created_at'])) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php include NC_ROOT . '/app/views/_foot.php'; ?>
