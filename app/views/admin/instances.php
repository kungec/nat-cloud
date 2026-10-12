<?php /* NAT Cloud - 管理后台：实例列表 */ ?>
<?php ob_start(); ?>
<form class="searchbar" method="get" action="/admin/instances">
  <input class="input" type="text" name="q" value="<?= e($search) ?>" placeholder="搜索实例名 / 用户名 / IP">
  <button class="btn btn-ghost" type="submit">搜索</button>
</form>
<div class="plist-grid">
  <?php if (empty($rows)): ?>
    <div class="card"><div class="empty">没有找到实例</div></div>
  <?php else: ?>
  <?php foreach ($rows as $i): $on = $i['status'] === 'running'; $smap = ['creating' => '部署中', 'running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费', 'released' => '已释放', 'failed' => '部署失败']; ?>
    <div class="card ncard<?= $i['status'] === 'released' || $i['status'] === 'failed' ? ' pcard-off' : '' ?>" data-id="<?= (int) $i['id'] ?>">
      <div class="pcard-head">
        <span class="drag-handle" title="拖动排序（决定前后台展示顺序）">⠿</span>
        <div class="pcard-title">
          <a href="/admin/instances/<?= (int) $i['id'] ?>/edit" style="color:inherit;text-decoration:none;"><b class="mono"><?= e($i['name']) ?></b></a>
          <span class="pcard-user"><?= e($i['username'] ?? '-') ?></span>
        </div>
        <span class="tag <?= e($i['status']) ?>"><?= $smap[$i['status']] ?? $i['status'] ?></span>
      </div>
      <div class="pcard-price" style="margin:0;">
        <span class="num" style="font-size:15px;font-weight:600;"><?= e(billing_spec_text($i)) ?></span>
      </div>
      <div class="ncard-grid">
        <span class="k">公网 IP</span><span class="mono"><?= e($i['ip']) ?></span>
        <span class="k">NAT 端口</span><span class="mono"><?= e($i['nat_ports']) ?></span>
        <span class="k">计费方式</span><span><?= e(billing_cycle_name(billing_instance_cycle($i))) ?> · 创建于 <?= e(dt($i['created_at'])) ?></span>
      </div>
      <div class="pcard-actions">
        <a class="btn btn-ghost btn-sm" href="/admin/instances/<?= (int) $i['id'] ?>/edit">编辑</a>
        <?php if ((int) $i['node_id'] > 0 && $i['status'] === 'running'): ?>
          <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action" data-confirm="控制台为 ROOT 终端（网页版），15 分钟…实际 6 小时有效。确认启动？"><?= csrf_field() ?><input type="hidden" name="do" value="console"><button class="btn btn-ghost btn-sm" type="submit">VNC</button></form>
        <?php endif; ?>
        <?php if ((int) $i['node_id'] > 0 && $i['status'] !== 'released'): ?>
          <button class="btn btn-ghost btn-sm" type="button" data-modal-open="modalRebuild<?= (int) $i['id'] ?>">重装</button>
        <?php endif; ?>
        <?php if ($i['status'] !== 'running' && $i['status'] !== 'released'): ?>
          <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="do" value="start"><button class="btn btn-ghost btn-sm" type="submit">开机</button></form>
        <?php endif; ?>
        <?php if ($i['status'] === 'running'): ?>
          <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="do" value="stop"><button class="btn btn-ghost btn-sm" type="submit">关机</button></form>
        <?php endif; ?>
        <?php if ($i['status'] !== 'released'): ?>
          <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action" data-confirm="确认强制释放该实例？"><?= csrf_field() ?><input type="hidden" name="do" value="release"><button class="btn btn-danger btn-sm" type="submit">释放</button></form>
        <?php else: ?>
          <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action" data-confirm="清理释放记录？"><?= csrf_field() ?><input type="hidden" name="do" value="clear"><button class="btn btn-ghost btn-sm" type="submit">清理</button></form>
        <?php endif; ?>
        <form class="inline-form" method="post" action="/admin/instances/<?= (int) $i['id'] ?>/delete" data-confirm="确认删除实例 <?= e($i['name']) ?>？节点实例会同步销毁容器，不可恢复。"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">删除</button></form>
      </div>
      <?php if ((int) $i['node_id'] > 0 && $i['status'] !== 'released'):
          $imgList = $nodesImgs[(int) $i['node_id']] ?? [];
          if (!$imgList) $imgList = [$i['os'] ?: 'debian11'];
      ?>
      <div class="modal-mask" id="modalRebuild<?= (int) $i['id'] ?>">
        <div class="modal">
          <h3>重装系统：<?= e($i['name']) ?></h3>
          <p class="desc" style="font-size:13px;">重装将<span style="color:var(--danger);">清空磁盘上全部数据</span>，约 1-2 分钟完成；完成后 ROOT 密码将随机重置，可在本页「重置密码」查看。</p>
          <form method="post" action="/admin/instances/<?= (int) $i['id'] ?>/action">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="rebuild">
            <div class="field">
              <label>选择系统镜像</label>
              <?php foreach ($imgList as $im): ?>
                <label class="checkbox-row"><input type="radio" name="img" value="<?= e($im) ?>" <?= $im === $i['os'] ? 'checked' : '' ?>> <?= e($im) ?></label>
              <?php endforeach; ?>
            </div>
            <div class="modal-actions">
              <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
              <button class="btn btn-danger" type="submit" data-confirm="确认重装系统？磁盘数据将全部清空。">确认重装</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, $search !== '' ? ['q' => $search] : []); ?>
<?php endif; ?>

<script>
/* 实例卡片拖拽排序（HTML5 DnD，把手触发；顺序写 display_sort） */
(function () {
  var grid = document.querySelector(".plist-grid");
  if (!grid) return;
  var dragging = null;
  grid.querySelectorAll(".card[data-id]").forEach(function (card) {
    var handle = card.querySelector(".drag-handle");
    if (!handle) return;
    handle.addEventListener("mousedown", function () { card.draggable = true; });
    handle.addEventListener("mouseup", function () { card.draggable = false; });
    card.addEventListener("dragstart", function (e) {
      dragging = card;
      card.classList.add("dragging");
      e.dataTransfer.effectAllowed = "move";
      try { e.dataTransfer.setData("text/plain", card.getAttribute("data-id")); } catch (err) {}
    });
    card.addEventListener("dragend", function () {
      card.classList.remove("dragging");
      grid.querySelectorAll(".card").forEach(function (c) { c.classList.remove("drag-over"); });
      saveOrder();
    });
    card.addEventListener("dragover", function (e) {
      e.preventDefault();
      if (!dragging || dragging === card) return;
      card.classList.add("drag-over");
      var r = card.getBoundingClientRect();
      var after = (e.clientY - r.top) > r.height / 2;
      grid.insertBefore(dragging, after ? card.nextSibling : card);
    });
    card.addEventListener("dragleave", function () { card.classList.remove("drag-over"); });
  });
  function saveOrder() {
    var csrf = (document.body.innerHTML.match(/_csrf" value="([a-f0-9]+)/) || [0, ""])[1];
    var ids = [...grid.querySelectorAll(".card[data-id]")].map(function (c) { return c.getAttribute("data-id"); });
    var body = ids.map(function (id) { return "ids[]=" + encodeURIComponent(id); }).join("&") + "&_csrf=" + encodeURIComponent(csrf);
    fetch("/admin/instances/reorder", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body }).catch(function () {});
  }
})();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
