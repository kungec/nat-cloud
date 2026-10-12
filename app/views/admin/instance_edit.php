<?php /* NAT Cloud - 管理后台：编辑实例 */ ?>
<?php
$backUrl = '/admin/instances';
$backText = '实例管理';
$smap = ['running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费', 'released' => '已释放'];
?>
<?php ob_start(); ?>
<div class="card" style="max-width:640px;">
  <h3>编辑实例：<?= e($inst['name']) ?></h3>
  <div class="sum-row"><span>所属用户</span><span><?= e($inst['username'] ?? ('#' . (int) $inst['uid'])) ?></span></div>
  <div class="sum-row"><span>计费方式</span><span><?= e(billing_cycle_name(billing_instance_cycle($inst))) ?></span></div>
  <div class="sum-row"><span>IP · 端口</span><span class="mono"><?= e($inst['ip']) ?> · <?= e($inst['nat_ports']) ?></span></div>
  <div class="sum-row" style="border-bottom:0;"><span>对接节点</span><span><?= (int) $inst['node_id'] > 0 ? e($inst['node_name']) . '（自动开通）' : '本机模拟' ?></span></div>

  <form method="post" action="/admin/instances/<?= (int) $inst['id'] ?>/update" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <div class="field">
        <label>状态</label>
        <select class="input" name="status">
          <?php foreach (['running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费'] as $sk => $sv): ?>
            <option value="<?= e($sk) ?>" <?= $inst['status'] === $sk ? 'selected' : '' ?>><?= e($sv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>计费周期</label>
        <select class="input" name="cycle">
          <?php foreach (['monthly' => '月付', 'quarterly' => '季付', 'semiannually' => '半年付', 'annually' => '年付'] as $ck => $cv): ?>
            <option value="<?= e($ck) ?>" <?= billing_instance_cycle($inst) === $ck ? 'selected' : '' ?>><?= e($cv) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>到期时间（按量/不限留空）</label>
        <input class="input" type="datetime-local" name="expire_at" value="<?= e($inst['expire_at'] ? date('Y-m-d\TH:i', strtotime($inst['expire_at'])) : '') ?>">
      </div>
      <div class="field">
        <label>vCPU（核）</label>
        <input class="input" type="number" name="cpu" value="<?= (int) $inst['cpu'] ?>" min="1" max="64">
      </div>
      <div class="field">
        <label>内存（MB）</label>
        <input class="input" type="number" name="ram_mb" value="<?= (int) $inst['ram_mb'] ?>" min="64" max="65536">
      </div>
      <div class="field">
        <label>磁盘（GB）</label>
        <input class="input" type="number" name="disk_gb" value="<?= (int) $inst['disk_gb'] ?>" min="1" max="1024">
      </div>
      <div class="field">
        <label>月流量（GB，0=不限）</label>
        <input class="input" type="number" name="traffic_gb" value="<?= (int) $inst['traffic_gb'] ?>" min="0" max="999999">
      </div>
      <div class="field">
        <label>系统</label>
        <?php $osList = array_values(array_unique(array_merge((array) ($osList ?? []), [(string) $inst['os']]))); ?>
        <?php if (count($osList) > 1 || node_images((int) $inst['node_id'])): ?>
        <?php
            $osIconMap = [];
            foreach ($osList as $o) {
                $osIconMap[$o] = os_icon($o, 16);
            }
        ?>
        <div class="os-select">
          <span class="os-ico" id="osIco"><?= os_icon((string) $inst['os'], 16) ?></span>
          <select class="input" name="os" id="osSel">
            <?php foreach ($osList as $o): ?>
              <option value="<?= e($o) ?>" <?= $o === $inst['os'] ? 'selected' : '' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="tip">选项来自节点上报的可用镜像（golden 模板），节点新增镜像后自动出现。</div>
        <script>
        (function () {
          var map = <?= json_encode($osIconMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
          var sel = document.getElementById("osSel");
          var ico = document.getElementById("osIco");
          if (!sel || !ico) return;
          var sync = function () { ico.innerHTML = map[sel.value] || ico.innerHTML; };
          sel.addEventListener("change", sync);
        })();
        </script>
        <?php else: ?>
        <input class="input" type="text" name="os" value="<?= e($inst['os']) ?>" maxlength="40">
        <?php endif; ?>
      </div>
      <div class="field">
        <label>时价（元/小时）</label>
        <input class="input" type="number" step="0.0001" min="0" name="hourly_price" value="<?= e(number_format((float) $inst['hourly_price'], 4, '.', '')) ?>">
      </div>
    </div>
    <div class="field">
      <label>月价（元）</label>
      <input class="input" type="number" step="0.01" min="0" name="monthly_price" value="<?= e(number_format((float) $inst['monthly_price'], 2, '.', '')) ?>">
    </div>
    <div class="tip">节点实例改配置仅更新记录与计费口径，实际容器规格需重建才生效；改状态会真实开关机。</div>
    <button class="btn btn-primary btn-lg" type="submit" style="margin-top:12px;">保存修改</button>
  </form>
</div>

<div class="card" style="max-width:640px;margin-top:20px;">
  <h3>实例运维</h3>
  <p class="desc" style="font-size:13px;">重置密码立即下发节点生效并更新记录；重装系统清空数据、按当前规格重建（可在编辑表单更换系统镜像后重装）。</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <form class="inline-form" method="post" action="/admin/instances/<?= (int) $inst['id'] ?>/action" data-confirm="确认重置该实例的 ROOT 密码？">
      <?= csrf_field() ?><input type="hidden" name="do" value="resetpw"><button class="btn btn-ghost btn-sm" type="submit" <?= (int) $inst['node_id'] > 0 ? '' : 'disabled' ?>>重置密码</button>
    </form>
    <form class="inline-form" method="post" action="/admin/instances/<?= (int) $inst['id'] ?>/action" data-confirm="确认重装系统（<?= e($inst['os']) ?>）？数据将全部清空。">
      <?= csrf_field() ?><input type="hidden" name="do" value="rebuild"><button class="btn btn-ghost btn-sm" type="submit" <?= (int) $inst['node_id'] > 0 ? '' : 'disabled' ?>>重装系统</button>
    </form>
  </div>
</div>

<div class="card" style="max-width:640px;margin-top:20px;border-color:rgba(240,97,109,0.35);">
  <h3>危险操作</h3>
  <p class="desc" style="font-size:13px;">删除会移除实例记录；节点实例会先下发销毁指令回收容器与端口，不可恢复。</p>
  <form method="post" action="/admin/instances/<?= (int) $inst['id'] ?>/delete" data-confirm="确认删除实例 <?= e($inst['name']) ?>？该操作不可恢复。">
    <?= csrf_field() ?>
    <button class="btn btn-danger btn-lg" type="submit">删除实例</button>
  </form>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
