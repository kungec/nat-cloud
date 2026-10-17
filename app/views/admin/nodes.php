<?php /* NAT Cloud - 管理后台：节点管理 */ ?>
<?php ob_start(); ?>
<div style="margin-bottom:16px;">
  <button class="btn btn-primary" type="button" data-modal-open="modalNodeCreate">+ 添加节点</button>
</div>
<?php if (empty($rows)): ?>
  <div class="card"><div class="empty">还没有节点。添加节点后，在套餐里绑定即可实现付款全自动开通。</div></div>
<?php else: ?>
<div class="plist-grid">
  <?php foreach ($rows as $n): $on = (int) $n['status'] === 1;
    $mJson = (string) ($n['metrics_json'] ?? '');
    $m = $mJson !== '' ? json_decode($mJson, true) : null;
    $online = $on && $n['last_seen'] !== null && strtotime((string) $n['last_seen']) > time() - 300;
  ?>
    <div class="card ncard<?= $on ? '' : ' pcard-off' ?>" draggable="false" data-id="<?= (int) $n['id'] ?>">
      <div class="pcard-head">
        <span class="drag-handle" title="拖动排序（决定前台展示顺序）">⠿</span>
        <div class="pcard-title"><b><?= e($n['name']) ?></b></div>
        <span class="tag <?= $on ? 'run' : 'closed' ?>"><?= $on ? '启用' : '停用' ?></span>
      </div>
      <div class="ncard-grid">
        <span class="k">公网 IP</span><span class="mono"><?= e($n['pub_ip']) ?></span>
        <span class="k">内网段</span><span class="mono"><?= e($n['net_prefix']) ?>.x</span>
        <span class="k">端口范围</span><span class="mono"><?= (int) $n['port_start'] ?> - <?= (int) $n['port_end'] ?></span>
        <span class="k">实例占用</span><span><?= (int) $n['used'] ?> / <?= (int) $n['max_instances'] ?></span>
      </div>
      <div class="ncard-grid" style="margin-top:6px;">
        <span class="k">心跳</span><span style="font-size:12.5px;">
          <?php if ($n['last_seen'] === null): ?><span style="color:var(--text-dim);">从未上报</span>
          <?php elseif ($online): ?><span style="color:#0a7d33;font-weight:600;">在线</span>（<?= e(dt($n['last_seen'])) ?>）
          <?php else: ?><span style="color:#b3261e;font-weight:600;">超 5 分钟未上报</span>（<?= e(dt($n['last_seen'])) ?>）<?php endif; ?>
        </span>
        <?php if (is_array($m)): ?>
        <span class="k">负载</span><span class="mono"><?= e(number_format((float) ($m['load1'] ?? 0), 2)) ?></span>
        <?php $mt = (int) ($m['mem_total_mb'] ?? 0); $ma = (int) ($m['mem_avail_mb'] ?? 0); ?>
        <span class="k">内存</span><span class="mono"><?= $mt > 0 ? e((string) ($mt - $ma)) . ' / ' . $mt . ' MB' : '—' ?></span>
        <?php $dt = (int) ($m['disk_total_gb'] ?? 0); $du = (int) ($m['disk_used_gb'] ?? 0); ?>
        <span class="k">磁盘</span><span class="mono"><?= $dt > 0 ? $du . ' / ' . $dt . ' GB' : '—' ?></span>
        <span class="k">运行容器</span><span class="mono"><?= (int) ($m['containers_running'] ?? 0) ?></span>
        <?php if (!empty($m['agent_version'])): ?>
        <span class="k">节点程序</span><span class="mono"><?= e((string) $m['agent_version']) ?></span>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ((string) $n['remark'] !== ''): ?><div class="pcard-cycles"><?= e($n['remark']) ?></div><?php endif; ?>
      <div class="pcard-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-install-key="<?= e($n['node_key']) ?>">安装命令</button>
        <button class="btn btn-ghost btn-sm" type="button" data-modal-open="nodeEdit<?= (int) $n['id'] ?>">编辑</button>
        <form class="inline-form" method="post" action="/admin/nodes/<?= (int) $n['id'] ?>/rekey" data-confirm="确认重置通信密钥？旧密钥立即失效，须在节点上重新执行新的安装命令。"><?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">重置密钥</button>
        </form>
        <form class="inline-form" method="post" action="/admin/nodes/<?= (int) $n['id'] ?>/delete" data-confirm="确认删除该节点？仍有实例时会自动改为停用。"><?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit">删除</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total); ?>
<?php endif; ?>

<div class="modal-mask" id="modalNodeCreate">
  <div class="modal">
    <h3>添加节点</h3>
    <p class="desc" style="font-size:13px;">添加后会生成一条<b>一键安装命令</b>（含节点专属密钥），在目标服务器 root 终端执行即可全自动完成虚拟化环境对接。<span style="color:var(--text-dim);">仅支持 KVM 虚拟化或独立服务器，OpenVZ/LXC 等共享内核的容器机不可用。</span></p>
    <form method="post" action="/admin/nodes/create">
      <?= csrf_field() ?>
      <div class="grid grid-2">
        <div class="field"><label>节点名称</label><input class="input" name="name" placeholder="如：东京LXC-01" required></div>
        <div class="field"><label>公网 IP</label><input class="input" name="pub_ip" placeholder="203.0.113.10" required></div>
        <div class="field"><label>内网网段前缀</label><input class="input" name="net_prefix" value="10.0.3"></div>
        <div class="field"><label>端口段起始</label><input class="input" type="number" name="port_start" value="20000"></div>
        <div class="field"><label>端口段结束</label><input class="input" type="number" name="port_end" value="60000"></div>
        <div class="field"><label>最大实例数</label><input class="input" type="number" name="max_instances" value="500"></div>
      </div>
      <div class="field"><label>备注</label><input class="input" name="remark" maxlength="190"></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">添加</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($rows as $n): ?>
<div class="modal-mask" id="nodeEdit<?= (int) $n['id'] ?>">
  <div class="modal">
    <h3>编辑节点：<?= e($n['name']) ?></h3>
    <p class="desc" style="font-size:12.5px;">节点密钥（NODE_KEY）：<span class="mono"><?= e($n['node_key']) ?></span></p>
    <div class="field">
      <label>一键安装命令（在全新 Ubuntu/Debian 服务器的 root 终端执行）</label>
      <textarea class="input" rows="3" readonly style="font-family:var(--mono);font-size:12px;" onclick="this.select()">bash &lt;(curl -fsSL &quot;<?= e(site_base_url()) ?>/api/api.php?f=node_install&amp;key=<?= e($n['node_key']) ?>&quot;)</textarea>
      <div class="tip">仅支持 KVM 虚拟化或独立服务器（x86_64 独立内核，需能安装 LXC）；OpenVZ / LXC 等共享内核的容器型 VPS 与 ARM 架构不可用。自动完成依赖安装、节点配置、程序部署、分钟级对账任务与 golden 系统模板，执行后约 1 分钟自动对接上线。</div>
    </div>
    <form method="post" action="/admin/nodes/<?= (int) $n['id'] ?>/update">
      <?= csrf_field() ?>
      <div class="grid grid-2">
        <div class="field"><label>节点名称</label><input class="input" name="name" value="<?= e($n['name']) ?>" required></div>
        <div class="field"><label>公网 IP</label><input class="input" name="pub_ip" value="<?= e($n['pub_ip']) ?>"></div>
        <div class="field"><label>内网网段前缀</label><input class="input" name="net_prefix" value="<?= e($n['net_prefix']) ?>"></div>
        <div class="field"><label>状态</label>
          <select class="input" name="status">
            <option value="1" <?= (int) $n['status'] === 1 ? 'selected' : '' ?>>启用</option>
            <option value="0" <?= (int) $n['status'] !== 1 ? 'selected' : '' ?>>停用</option>
          </select>
        </div>
        <div class="field"><label>端口段起始</label><input class="input" type="number" name="port_start" value="<?= (int) $n['port_start'] ?>"></div>
        <div class="field"><label>端口段结束</label><input class="input" type="number" name="port_end" value="<?= (int) $n['port_end'] ?>"></div>
        <div class="field"><label>最大实例数</label><input class="input" type="number" name="max_instances" value="<?= (int) $n['max_instances'] ?>"></div>
        <div class="field"><label>排序（小在前，也可拖动卡片）</label><input class="input" type="number" name="sort" value="<?= (int) ($n['sort'] ?? 0) ?>"></div>
      </div>
      <div class="field"><label>备注</label><input class="input" name="remark" value="<?= e($n['remark']) ?>" maxlength="190"></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<div class="modal-mask" id="modalNodeInstall">
  <div class="modal">
    <h3>节点一键安装命令</h3>
    <p class="desc" style="font-size:13px;">在目标服务器的 root 终端完整执行下面这条命令（需 Ubuntu 20+ / Debian 11+）。<b>仅支持 KVM 虚拟化或独立服务器（x86_64）；OpenVZ / LXC 等共享内核的容器型 VPS 与 ARM 架构不可用</b>（容器机无法嵌套安装 LXC）。命令含本节点专属密钥，请勿泄露。</p>
    <textarea class="input" id="nodeInstallCmd" rows="3" readonly style="font-family:var(--mono);font-size:12.5px;"></textarea>
    <div class="tip">自动完成：依赖安装 → 写入本节点配置 → 部署对账程序 → 注册分钟级任务 → 生成 debian12 系统模板。执行后约 1 分钟自动对接上线。</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" type="button" data-modal-close>关闭</button>
      <button class="btn btn-primary" type="button" id="nodeInstallCopy">复制命令</button>
    </div>
  </div>
</div>
<script>
(function () {
  document.querySelectorAll("[data-install-key]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var key = btn.getAttribute("data-install-key");
      var cmd = 'bash <(curl -fsSL "' + location.origin + '/api/api.php?f=node_install&key=' + key + '")';
      var ta = document.getElementById("nodeInstallCmd");
      if (ta) ta.value = cmd;
      var mask = document.getElementById("modalNodeInstall");
      if (mask) mask.classList.add("open");
    });
  });
  var copy = document.getElementById("nodeInstallCopy");
  if (copy) copy.addEventListener("click", function () {
    var ta = document.getElementById("nodeInstallCmd");
    ta.select();
    try { document.execCommand("copy"); } catch (e) {}
    if (navigator.clipboard) navigator.clipboard.writeText(ta.value).catch(function () {});
    copy.textContent = "已复制";
    setTimeout(function () { copy.textContent = "复制命令"; }, 1500);
  });
})();
</script>

<script>
/* 节点卡片拖拽排序（HTML5 DnD，把手触发） */
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
    var ids = [...grid.querySelectorAll(".card[data-id]")].map(function (c) { return c.getAttribute("data-id"); });
    var body = ids.map(function (id) { return "ids[]=" + encodeURIComponent(id); }).join("&") + "&_csrf=" + (document.body.innerHTML.match(/_csrf" value="([a-f0-9]+)/) || [0, ""])[1];
    fetch("/admin/nodes/reorder", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body }).catch(function () {});
  }
})();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
