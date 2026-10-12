<?php /* NAT Cloud - 我的服务列表（卡片 + 翻页） */ ?>
<?php ob_start(); ?>
<div class="main-head" style="margin-bottom:14px;">
  <div class="sub">包年包月按月预付，按量计费按小时从余额扣。</div>
  <a class="btn btn-primary btn-sm" href="/dashboard/create">创建实例</a>
</div>
<div class="tabs">
  <a class="<?= $tab === '' ? 'on' : '' ?>" href="/dashboard/services">全部</a>
  <a class="<?= $tab === 'running' ? 'on' : '' ?>" href="/dashboard/services?tab=running">运行中</a>
  <a class="<?= $tab === 'stopped' ? 'on' : '' ?>" href="/dashboard/services?tab=stopped">已停机</a>
  <a class="<?= $tab === 'overdue' ? 'on' : '' ?>" href="/dashboard/services?tab=overdue">欠费停机</a>
  <a class="<?= $tab === 'released' ? 'on' : '' ?>" href="/dashboard/services?tab=released">已释放</a>
</div>
<?php if (empty($rows)): ?>
  <div class="empty">还没有服务。<br><a class="btn btn-primary btn-sm" style="margin-top:10px;" href="/dashboard/create">浏览套餐</a></div>
<?php else: ?>
<form method="post" action="/dashboard/services/bulk-renew" id="bulkForm" data-confirm="确认对已勾选的实例按所选周期批量续费？将从余额一次性扣费。">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="bulk-bar">
    <label class="bulk-all"><input type="checkbox" id="bulkAll"><span>全选</span></label>
    <span class="bulk-count" id="bulkCount">已选 0 台</span>
    <span class="bulk-flex"></span>
    <select class="input svc-cycle-sm" name="cycle">
      <option value="monthly">月付</option>
      <option value="quarterly">季付</option>
      <option value="semiannually">半年付</option>
      <option value="annually">年付</option>
    </select>
    <button class="btn btn-primary btn-sm" type="submit" id="bulkBtn" disabled>批量续费</button>
  </div>
  <div class="plist-grid svc-list">
  <?php foreach ($rows as $i): ?>
  <div class="card pcard<?= $i['status'] === 'released' ? ' pcard-off' : '' ?>">
    <div class="pcard-head">
      <?php if ($i['billing_mode'] === 'monthly' && $i['status'] !== 'released'): ?>
      <label class="pcard-check" title="勾选批量续费"><input type="checkbox" name="ids[]" value="<?= (int) $i['id'] ?>"></label>
      <?php endif; ?>
      <a class="pcard-title svc-card-title mono" href="/dashboard/services/<?= (int) $i['id'] ?>"><?= e($i['name']) ?></a>
      <span class="tag <?= $i['status'] === 'creating' ? 'pending' : e($i['status']) ?>"><?php
        $smap = ['creating' => '部署中', 'running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费停机', 'released' => '已释放', 'failed' => '部署失败'];
        echo e($smap[$i['status']] ?? $i['status']);
      ?></span>
    </div>
    <div class="pcard-price"><span class="num"><?= e(billing_spec_text($i)) ?></span></div>
    <div class="ncard-grid">
      <span class="k">节点</span><span><?= e($i['node_name']) ?> · <?= e($i['region']) ?></span>
      <span class="k">系统镜像</span><span style="display:inline-flex;align-items:center;gap:6px;"><?= os_icon((string) $i['os'], 15) ?><span class="mono"><?= e($i['os']) ?></span></span>
      <span class="k">公网 IP</span><span class="mono"><?= e($i['ip']) ?></span>
      <span class="k">端口段</span><span class="mono"><?= e($i['nat_ports']) ?></span>
    </div>
    <div class="scard-foot">
      <span class="scard-bill"><?= e(billing_cycle_name(billing_instance_cycle($i))) ?> · <?= $i['billing_mode'] === 'monthly' ? ($i['expire_at'] && $i['expire_at'] !== '0000-00-00 00:00:00' ? '到期 ' . e(dt($i['expire_at'])) : '未激活') : '随余额扣费' ?></span>
      <a class="btn btn-ghost btn-sm" href="/dashboard/services/<?= (int) $i['id'] ?>">管理</a>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</form>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, $tab !== '' ? ['tab' => $tab] : []); ?>
<?php endif; ?>
<script>
(function () {
  var form = document.getElementById("bulkForm");
  if (!form) return;
  var boxes = function () { return [...form.querySelectorAll('input[name="ids[]"]')]; };
  var countEl = document.getElementById("bulkCount");
  var btn = document.getElementById("bulkBtn");
  var all = document.getElementById("bulkAll");
  function sync() {
    var list = boxes();
    var n = list.filter(function (b) { return b.checked; }).length;
    if (countEl) countEl.textContent = "已选 " + n + " 台";
    if (btn) btn.disabled = n === 0;
    if (all) all.checked = list.length > 0 && n === list.length;
  }
  boxes().forEach(function (b) { b.addEventListener("change", sync); });
  if (all) {
    all.addEventListener("change", function () {
      boxes().forEach(function (b) { b.checked = all.checked; });
      sync();
    });
  }
  sync();
})();
</script>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
