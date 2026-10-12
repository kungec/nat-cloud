<?php /* NAT Cloud - 管理后台：套餐管理 */
billing_ensure_pricing_columns();
$nodes = db_all('SELECT id, name, pub_ip, images FROM nodes ORDER BY id ASC');
$imgLabels = ['debian11' => 'Debian 11', 'debian12' => 'Debian 12'];
/* 节点上报的实际可用镜像：nodeId => [镜像名...]（未上报时回落 debian11/12） */
$nodeImages = [];
foreach ($nodes as $n) {
    $list = json_decode((string) ($n['images'] ?? ''), true);
    if (!is_array($list)) {
        $list = [];
    }
    $clean = [];
    foreach ($list as $im) {
        if (is_string($im) && preg_match('~^[a-z0-9][a-z0-9-]{0,31}$~', $im)) {
            $clean[] = $im;
        }
    }
    $nodeImages[(string) (int) $n['id']] = array_values(array_unique($clean));
}
function plan_images_of($p): array
{
    $v = json_decode((string) $p['images'], true);
    return is_array($v) ? $v : [];
}
?>
<?php ob_start(); ?>
<div style="margin-bottom:16px;">
  <button class="btn btn-primary" type="button" data-modal-open="modalPlanCreate">+ 新建套餐</button>
</div>
<?php if (empty($rows)): ?>
  <div class="card"><div class="empty">还没有套餐，点击上方「新建套餐」创建。</div></div>
<?php else: ?>
<div class="plist-grid">
  <?php foreach ($rows as $p): $imgs = plan_images_of($p); $on = (int) $p['status'] === 1; ?>
    <div class="card pcard<?= $on ? '' : ' pcard-off' ?>" data-id="<?= (int) $p['id'] ?>">
      <div class="pcard-head">
        <span class="drag-handle" title="拖动排序（决定前台展示顺序）">⠿</span>
        <div class="pcard-title">
          <b><?= e($p['name']) ?></b>
          <?php if ($p['badge']): ?><span class="tag run"><?= e($p['badge']) ?></span><?php endif; ?>
        </div>
        <span class="tag <?= $on ? 'run' : 'closed' ?>"><?= $on ? '上架' : '下架' ?></span>
      </div>
      <div class="pcard-price">
        <span class="num">¥<?= e(money($p['monthly_price'])) ?></span><span class="unit">/月</span>
        <?php if ((float) $p['hourly_price'] > 0): ?><span class="hourly">¥<?= e(money4($p['hourly_price'])) ?> /时</span><?php endif; ?>
      </div>
      <?php
        $cycles = [];
        foreach ([['quarterly_price', '季付'], ['semiannually_price', '半年付'], ['annually_price', '年付']] as [$ck, $cl]) {
            $cv = (float) ($p[$ck] ?? 0);
            if ($cv > 0) {
                $cycles[] = $cl . ' ¥' . e(money($cv));
            }
        }
      ?>
      <?php if ($cycles): ?><div class="pcard-cycles"><?= e(implode(' · ', $cycles)) ?></div><?php endif; ?>
      <div class="pcard-spec">
        <span><?= (int) $p['cpu'] ?>C / <?= e(number_format($p['ram_mb'] / 1024, 1)) ?>G / <?= (int) $p['disk_gb'] ?>G</span>
        <span><?= (int) $p['bandwidth_mbps'] ?>M 带宽</span>
        <span><?= (int) $p['traffic_gb'] > 0 ? (int) $p['traffic_gb'] . 'G 流量' : '流量不限' ?></span>
        <span><?= (int) $p['port_count'] ?> 个 NAT 端口</span>
      </div>
      <div class="pcard-meta">
        <?php if ((int) $p['node_id'] > 0): ?><span class="tag pending">自动开通</span><?php else: ?><span class="tag closed">本机模拟</span><?php endif; ?>
        <span class="pcard-stock"><?= (int) $p['stock'] < 1 ? '<b style="color:var(--danger)">已售罄</b>' : '库存 ' . (int) $p['stock'] ?></span>
      </div>
      <?php if ($imgs): ?>
      <div class="pcard-imgs">
        <span class="pcard-imgs-label">可选系统</span>
        <span class="pcard-imgs-list">
          <?php foreach ($imgs as $im): ?>
            <span class="os-chip"><?= os_icon($im, 13) ?><?= e(os_label($im)) ?></span>
          <?php endforeach; ?>
        </span>
      </div>
      <?php endif; ?>
      <div class="pcard-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-modal-open="planEdit<?= (int) $p['id'] ?>">编辑</button>
        <form class="inline-form" method="post" action="/admin/plans/<?= (int) $p['id'] ?>/delete" data-confirm="确认删除该套餐？存在引用时会自动改为下架。"><?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit">删除</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total); ?>
<?php endif; ?>

<?php
function plan_form_fields(array $nodes, array $imgLabels, array $nodeImages, array $v = []): void
{
    $nodeId = (int) ($v['node_id'] ?? 0);
    $imgs = isset($v['images']) ? (array) (json_decode((string) $v['images'], true) ?: []) : [];
    ?>
    <div class="grid grid-2">
      <div class="field"><label>套餐名称</label><input class="input" name="name" value="<?= e($v['name'] ?? '') ?>" required></div>
      <div class="field"><label>角标（可选，如：热门 / 新品 / 特惠）</label><input class="input" name="badge" value="<?= e($v['badge'] ?? '') ?>" maxlength="20" placeholder="填写后套餐卡右上角显示该标签，留空不显示"></div>
      <div class="field"><label>对接节点（选择后全自动开通）</label>
        <select class="input js-node-sel" name="node_id">
          <option value="0" <?= $nodeId === 0 ? 'selected' : '' ?>>不绑定（本机模拟）</option>
          <?php foreach ($nodes as $n): ?>
            <option value="<?= (int) $n['id'] ?>" <?= $nodeId === (int) $n['id'] ? 'selected' : '' ?>><?= e($n['name']) ?>（<?= e($n['pub_ip']) ?>）</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>地域</label><input class="input" name="region" value="<?= e($v['region'] ?? '东京') ?>"></div>
      <div class="field"><label>节点显示名</label><input class="input" name="node_name" value="<?= e($v['node_name'] ?? '东京 #1') ?>"></div>
      <div class="field"><label>NAT 端口数（每实例）</label><input class="input" type="number" name="port_count" value="<?= (int) ($v['port_count'] ?? 15) ?>" min="1" max="64"></div>
      <div class="field"><label>vCPU（核）</label><input class="input" type="number" name="cpu" value="<?= (int) ($v['cpu'] ?? 1) ?>" min="1"></div>
      <div class="field"><label>内存（MB）</label><input class="input" type="number" name="ram_mb" value="<?= (int) ($v['ram_mb'] ?? 1024) ?>" min="64"></div>
      <div class="field"><label>磁盘（GB）</label><input class="input" type="number" name="disk_gb" value="<?= (int) ($v['disk_gb'] ?? 10) ?>" min="1"></div>
      <div class="field"><label>带宽（Mbps）</label><input class="input" type="number" name="bandwidth_mbps" value="<?= (int) ($v['bandwidth_mbps'] ?? 100) ?>" min="1"></div>
      <div class="field"><label>月价（元）</label><input class="input" type="number" step="0.01" name="monthly_price" value="<?= e($v['monthly_price'] ?? '15.00') ?>"></div>
      <div class="field"><label>时价（元/小时，留空=不支持按量计费）</label><input class="input" type="number" step="0.0001" min="0" name="hourly_price" value="<?= e($v['hourly_price'] > 0 ? money4($v['hourly_price']) : '') ?>" placeholder="留空表示该套餐仅支持包年包月"></div>
      <div class="field"><label>实名认证（覆盖全局设置）</label>
        <select class="input" name="kyc_required">
          <?php $kr = (int) ($v['kyc_required'] ?? 0); ?>
          <option value="0" <?= $kr === 0 ? 'selected' : '' ?>>跟随全局设置（默认）</option>
          <option value="1" <?= $kr === 1 ? 'selected' : '' ?>>需要实名认证</option>
          <option value="2" <?= $kr === 2 ? 'selected' : '' ?>>不需要实名认证</option>
        </select>
      </div>
      <div class="field"><label>季付价格（元，0=按月价×3）</label><input class="input" type="number" step="0.01" min="0" name="quarterly_price" value="<?= e(number_format((float) ($v['quarterly_price'] ?? 0), 2, '.', '')) ?>"></div>
      <div class="field"><label>半年付价格（元，0=按月价×6）</label><input class="input" type="number" step="0.01" min="0" name="semiannually_price" value="<?= e(number_format((float) ($v['semiannually_price'] ?? 0), 2, '.', '')) ?>"></div>
      <div class="field"><label>年付价格（元，0=按月价×12）</label><input class="input" type="number" step="0.01" min="0" name="annually_price" value="<?= e(number_format((float) ($v['annually_price'] ?? 0), 2, '.', '')) ?>"></div>
      <div class="field"><label>月流量（GB，双向计费，0=不限）</label><input class="input" type="number" name="traffic_gb" value="<?= (int) ($v['traffic_gb'] ?? 1000) ?>" min="0"></div>
      <div class="field"><label>库存</label><input class="input" type="number" name="stock" value="<?= (int) ($v['stock'] ?? 50) ?>" min="0"></div>
      <div class="field"><label>NAT 类型说明</label><input class="input" name="nat_type" value="<?= e($v['nat_type'] ?? '共享IP · NAT端口段') ?>"></div>
      <div class="field"><label>排序（小在前）</label><input class="input" type="number" name="sort" value="<?= (int) ($v['sort'] ?? 0) ?>"></div>
    </div>
    <div class="field">
      <label>可选系统（节点支持的镜像）</label>
      <?php $selNodeImgs = $nodeImages[(string) $nodeId] ?? []; ?>
      <?php
        $allImgs = array_values(array_unique(array_merge($selNodeImgs ?: array_keys($imgLabels), ['debian11', 'debian12'])));
        $osIconMap = [];
        $osLabelMap = [];
        foreach ($allImgs as $im) {
            $osIconMap[$im] = os_icon($im, 17);
            $osLabelMap[$im] = os_label($im);
        }
      ?>
      <div class="js-img-field" data-nodes="<?= e(json_encode($nodeImages, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}') ?>" data-os-icons="<?= e(json_encode($osIconMap, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}') ?>" data-os-labels="<?= e(json_encode($osLabelMap, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}') ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (!$selNodeImgs) {
            $selNodeImgs = array_keys($imgLabels);
        }
        foreach ($selNodeImgs as $imgKey): ?>
          <label class="os-check<?= in_array($imgKey, $imgs, true) ? ' on' : '' ?>">
            <input type="checkbox" name="images[]" value="<?= e($imgKey) ?>" <?= in_array($imgKey, $imgs, true) ? 'checked' : '' ?>>
            <span class="os-check-ico"><?= os_icon($imgKey, 17) ?></span>
            <span><?= e(os_label($imgKey)) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="tip">列表来自节点服务器实际存在的镜像（节点每分钟自动上报）；开通时用户只能从这里选择。</div>
    </div>
    <div class="field"><label>描述（可选）</label><textarea class="input" name="description" rows="2"><?= e($v['description'] ?? '') ?></textarea></div>
    <?php
}
?>

<div class="modal-mask" id="modalPlanCreate">
  <div class="modal">
    <h3>新建套餐</h3>
    <form method="post" action="/admin/plans/create">
      <?= csrf_field() ?>
      <?php plan_form_fields($nodes, $imgLabels, $nodeImages); ?>
      <div class="field"><label>状态</label>
        <select class="input" name="status">
          <option value="1" selected>上架</option>
          <option value="0">下架</option>
        </select>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">创建</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($rows as $p): ?>
<div class="modal-mask" id="planEdit<?= (int) $p['id'] ?>">
  <div class="modal">
    <h3>编辑套餐：<?= e($p['name']) ?></h3>
    <form method="post" action="/admin/plans/<?= (int) $p['id'] ?>/update">
      <?= csrf_field() ?>
      <?php plan_form_fields($nodes, $imgLabels, $nodeImages, $p); ?>
      <div class="field"><label>状态</label>
        <select class="input" name="status">
          <option value="1" <?= (int) $p['status'] === 1 ? 'selected' : '' ?>>上架</option>
          <option value="0" <?= (int) $p['status'] !== 1 ? 'selected' : '' ?>>下架</option>
        </select>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script>
/* 套餐卡片拖拽排序（HTML5 DnD，把手触发） */
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
    var csrf = (document.body.innerHTML.match(/_csrf" value="([a-f0-9]+)/) || [0, ""])[1];
    var body = ids.map(function (id) { return "ids[]=" + encodeURIComponent(id); }).join("&") + "&_csrf=" + csrf;
    fetch("/admin/plans/reorder", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body }).catch(function () {});
  }
})();
</script>

<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
