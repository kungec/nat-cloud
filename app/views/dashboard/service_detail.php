<?php /* NAT Cloud - 实例详情 */ ?>
<?php ob_start(); ?>
<?php
$ports = json_decode((string) $inst['forwarded_ports'], true);
$statusMap = ['creating' => '部署中', 'running' => '运行中', 'stopped' => '已停机', 'overdue' => '欠费停机', 'released' => '已释放', 'failed' => '部署失败'];
/* 节点任务：有未完成的状态变更任务时禁用操作按钮（console 是辅助操作，不阻塞页面） */
$taskLabel = ['start' => '开机', 'stop' => '关机', 'restart' => '重启', 'rebuild' => '重装系统', 'delete' => '销毁', 'password' => '重置密码', 'console' => '启动控制台', 'nat' => '更新端口转发'];
$runningTask = null;
if ((int) $inst['node_id'] > 0) {
    $tstmt = db()->prepare("SELECT action, status, created_at FROM node_tasks WHERE node_id = ? AND status IN ('pending','sent') AND action <> 'console' AND payload LIKE ? ORDER BY id DESC LIMIT 1");
    $tstmt->execute([(int) $inst['node_id'], '%"' . $inst['name'] . '"%']);
    $trow = $tstmt->fetch();
    if ($trow) {
        $runningTask = $trow;
    }
}
$busy = (bool) $runningTask;
$dis = $busy ? 'disabled' : '';
/* 节点上报状态中文化 */
$stateMap = ['RUNNING' => '正常', 'STOPPED' => '已关机', 'ABSENT' => '故障', 'FROZEN' => '已冻结', 'STARTING' => '启动中', 'STOPPING' => '停止中', 'FREEZING' => '冻结中', 'THAWED' => '已解冻'];
$stateText = $inst['node_state'] !== '' ? ($stateMap[$inst['node_state']] ?? $inst['node_state']) : '等待节点上报';
$stateColor = $inst['node_state'] === 'RUNNING' ? 'var(--ok)' : ($inst['node_state'] === 'ABSENT' ? 'var(--danger)' : 'var(--warn)');
/* 流量统计 */
$usedGb = round(((int) $inst['traffic_rx'] + (int) $inst['traffic_tx']) / 1073741824, 2);
$quota = (int) $inst['traffic_gb'];
$flowPct = $quota > 0 ? min(100, round($usedGb / $quota * 100, 1)) : 0;
$flowBar = $flowPct >= 95 ? 'var(--danger)' : ($flowPct >= 80 ? 'var(--warn)' : 'var(--text)');
/* SSH 一键命令 */
$sshCmd = 'ssh -p ' . (int) $inst['ssh_port'] . ' root@' . e($inst['ip']);
$released = $inst['status'] === 'released';
?>
<div class="svc-head">
  <div class="svc-head-l">
    <div class="svc-title">
      <h1><?= e($inst['name']) ?></h1>
      <span class="tag <?= e($inst['status']) ?>"><?= e($statusMap[$inst['status']] ?? $inst['status']) ?></span>
    </div>
    <div class="svc-sub">
      <span><?= nc_icon('server', 14) ?><?= e($inst['node_name']) ?> · <?= e($inst['region']) ?></span>
      <span class="svc-sub-sep"></span>
      <span>开通于 <?= e(dt($inst['created_at'])) ?></span>
      <span class="svc-sub-sep"></span>
      <span><?= $inst['billing_mode'] === 'monthly' ? e(billing_cycle_name(billing_instance_cycle($inst))) . ' ¥' . e(money($inst['monthly_price'])) . '/月' : '按量 ¥' . e(money4($inst['hourly_price'])) . '/小时' ?></span>
    </div>
  </div>
  <div class="svc-head-r">
    <?php if ((int) $inst['node_id'] > 0 && $inst['status'] === 'running'): ?>
    <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action" data-confirm="控制台为 ROOT 终端（网页版），15 分钟有效。确认打开？">
      <?= csrf_field() ?><input type="hidden" name="do" value="console"><button class="btn btn-primary" type="submit" <?= $dis ?>><?= nc_icon('terminal', 15) ?>VNC 控制台</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="stat-grid svc-stats">
  <div class="stat">
    <div class="l">配置规格</div>
    <div class="v v-sm"><?= e(billing_spec_text($inst)) ?></div>
    <div class="s"><?= (int) $inst['bandwidth_mbps'] ?> Mbps 带宽</div>
  </div>
  <div class="stat">
    <div class="l">系统镜像</div>
    <div class="v v-sm" style="display:inline-flex;align-items:center;gap:7px;"><?= os_icon((string) $inst['os'], 19) ?><?= e($inst['os']) ?></div>
    <div class="s">可在操作中一键重装</div>
  </div>
  <div class="stat">
    <div class="l">SSH 端口</div>
    <div class="v"><?= (int) $inst['ssh_port'] ?></div>
    <div class="s mono"><?= e($inst['ip']) ?></div>
  </div>
  <div class="stat">
    <div class="l">流量使用</div>
    <div class="v"><?= e($usedGb) ?><span class="v-unit">/ <?= $quota > 0 ? $quota . ' GB' : '不限量' ?></span></div>
    <?php if ($quota > 0): ?>
    <div class="flow-bar"><span style="width:<?= $flowPct ?>%;background:<?= $flowBar ?>;"></span></div>
    <?php else: ?>
    <div class="s">双向计费</div>
    <?php endif; ?>
  </div>
</div>

<div class="svc-grid">
  <div class="card">
    <h3>配置信息</h3>
    <div class="kv" style="margin-top:12px;">
      <span class="k">实例名</span><span class="mono"><?= e($inst['name']) ?></span>
      <span class="k">节点</span><span><?= e($inst['node_name']) ?> · <?= e($inst['region']) ?></span>
      <span class="k">带宽 / 流量</span><span><?= (int) $inst['bandwidth_mbps'] ?> Mbps / <?= $quota > 0 ? $quota . ' GB 每月' : '不限量（双向计费）' ?></span>
      <span class="k">计费方式</span><span><?= $inst['billing_mode'] === 'monthly' ? e(billing_cycle_name(billing_instance_cycle($inst))) . '（¥' . e(money($inst['monthly_price'])) . '/月）' : '按量计费（¥' . e(money4($inst['hourly_price'])) . '/小时）' ?></span>
      <span class="k">到期时间</span><span><?= $inst['billing_mode'] === 'monthly' ? e(dt($inst['expire_at'])) : '随余额扣费' ?></span>
      <span class="k">节点上报</span><span style="color:<?= $stateColor ?>;font-weight:600;"><?= e($stateText) ?></span>
    </div>
  </div>
  <div class="card">
    <h3>连接信息</h3>
    <div class="kv" style="margin-top:12px;">
      <span class="k">公网 IP</span><span><span class="chip-copy" data-copy="<?= e($inst['ip']) ?>"><span class="mono"><?= e($inst['ip']) ?></span><?= nc_icon('copy', 13) ?></span></span>
      <span class="k">SSH 命令</span><span><span class="chip-copy" data-copy="<?= $sshCmd ?>"><span class="mono"><?= $sshCmd ?></span><?= nc_icon('copy', 13) ?></span></span>
      <span class="k">ROOT 密码</span><span class="pw-box">
        <span class="mono pw-text" id="rootPw" data-pw="<?= e($inst['root_pw'] !== '' ? $inst['root_pw'] : '') ?>" title="点击复制">●●●●●●●●</span>
        <button type="button" class="pw-eye" id="pwEye" title="显示 / 隐藏密码">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <span class="pw-tip" id="pwTip">点击密码复制</span>
      </span>
      <?php if ((int) $inst['node_id'] > 0):
          $bizFrom = (int) $inst['ssh_port'] + 1;
          $bizTo = (int) $inst['ssh_port'];
          if (preg_match('~^(\d+)\s*-\s*(\d+)$~', (string) $inst['nat_ports'], $mm)) {
              $bizTo = (int) $mm[2];
          }
      ?>
      <span class="k">SSH 端口</span><span class="mono"><?= (int) $inst['ssh_port'] ?> <span class="port-hint">登录专用</span></span>
      <span class="k">业务端口</span><span class="mono"><?= $bizFrom <= $bizTo ? $bizFrom . ' - ' . $bizTo : '暂无' ?> <span class="port-hint">自由转发 · TCP/UDP</span></span>
      <?php elseif ($ports && isset($ports['start'])): ?>
      <span class="k">SSH 端口</span><span class="mono"><?= (int) $ports['start'] ?> <span class="port-hint">登录专用</span></span>
      <span class="k">业务端口</span><span class="mono"><?= (int) ($ports['start'] + 1) ?> - <?= (int) ($ports['start'] + $ports['count'] - 1) ?> <span class="port-hint">自由转发 · TCP/UDP</span></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $vnc = function_exists('vnc_active') && !$released ? vnc_active($inst) : null; ?>
<?php if ($vnc): ?>
<div class="card vnc-card" style="margin-top:18px;">
  <div class="vnc-head">
    <h3><?= nc_icon('terminal', 17) ?> VNC 控制台</h3>
    <span class="vnc-expire">有效期至 <?= e(dt($vnc['expire_at'])) ?></span>
  </div>
  <p class="desc" style="font-size:13px;margin-top:6px;">
    点击下方按钮打开登录页，账号密码用右侧复制按钮复制后粘贴（16 位密码不建议手抄）。
    若登录框反复弹出或提示错误，说明浏览器缓存了旧密码——请关闭旧的 30005 标签页后重新打开，或改用无痕窗口。
  </p>
  <div class="vnc-row">
    <span class="chip-copy" data-copy="<?= e($vnc['url']) ?>"><span class="mono"><?= e($vnc['url']) ?></span><?= nc_icon('copy', 13) ?></span>
    <span class="chip-copy" data-copy="nc"><?= nc_icon('user', 13) ?> 账号 nc <?= nc_icon('copy', 13) ?></span>
    <span class="chip-copy" data-copy="<?= e($vnc['token']) ?>"><span class="mono"><?= e($vnc['token']) ?></span><?= nc_icon('copy', 13) ?></span>
    <a class="btn btn-primary btn-sm" href="<?= e($vnc['url']) ?>" target="_blank" rel="noopener">打开控制台 ↗</a>
  </div>
</div>
<?php endif; ?>

<div class="ops-band">
  <?php if ($busy && $runningTask): ?>
  <div class="task-banner">
    <?= nc_icon('clock', 16) ?>
    <span>任务执行中：<b><?= e($taskLabel[$runningTask['action']] ?? $runningTask['action']) ?></b>（<?= e(dt($runningTask['created_at'])) ?> 下发）。节点每分钟同步一次，完成后本页自动恢复，请勿重复操作。</span>
  </div>
  <script>
  (function () {
    var tries = 0;
    var timer = setInterval(function () {
      if (++tries > 75) { clearInterval(timer); return; }
      fetch("/dashboard/services/<?= (int) $inst['id'] ?>/busy").then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.busy === false) { clearInterval(timer); location.reload(); }
      }).catch(function () {});
    }, 8000);
  })();
  </script>
  <?php endif; ?>
  <div class="ops-bar<?= $busy ? ' busy' : '' ?>">
    <div class="ops-group">
      <span class="ops-name">电源</span>
      <div class="ops-btns">
        <?php if ($inst['status'] === 'stopped'): ?>
          <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="do" value="start"><button class="btn btn-ghost btn-sm" type="submit" <?= $dis ?>>开机</button></form>
        <?php endif; ?>
        <?php if ($inst['status'] === 'running'): ?>
          <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="do" value="stop"><button class="btn btn-ghost btn-sm" type="submit" <?= $dis ?>>关机</button></form>
          <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="do" value="reboot"><button class="btn btn-ghost btn-sm" type="submit" <?= $dis ?>>重启</button></form>
        <?php endif; ?>
        <?php if ($inst['status'] !== 'running' && $inst['status'] !== 'stopped' && !$released): ?>
          <span class="ops-none">当前状态不可操作</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ((int) $inst['node_id'] > 0 && !$released): ?>
    <span class="ops-sep"></span>
    <div class="ops-group">
      <span class="ops-name">系统</span>
      <div class="ops-btns">
        <button class="btn btn-ghost btn-sm" type="button" data-modal-open="modalRebuild" <?= $dis ?>>重装系统</button>
        <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action" data-confirm="确认重置 ROOT 密码？重置后旧密码立即失效。">
          <?= csrf_field() ?><input type="hidden" name="do" value="resetpw"><button class="btn btn-ghost btn-sm" type="submit" <?= $dis ?>>重置密码</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!$released): ?>
    <span class="ops-sep"></span>
    <div class="ops-group">
      <span class="ops-name">网络</span>
      <div class="ops-btns">
        <a class="btn btn-ghost btn-sm" href="/dashboard/services/<?= (int) $inst['id'] ?>/nat">NAT 端口转发</a>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($inst['billing_mode'] === 'monthly' && !$released): ?>
    <span class="ops-sep"></span>
    <div class="ops-group">
      <span class="ops-name">续费</span>
      <div class="ops-btns">
        <form class="inline-form svc-renew" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action" data-confirm="确认按所选周期续费并从余额扣费？">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="renew">
          <select class="input svc-cycle-sm" name="cycle" <?= $dis ?>>
            <option value="monthly">月付</option>
            <option value="quarterly">季付</option>
            <option value="semiannually">半年付</option>
            <option value="annually">年付</option>
          </select>
          <button class="btn btn-primary btn-sm" type="submit" <?= $dis ?>>续费</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!$released): ?>
    <div class="ops-group ops-danger">
      <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action" data-confirm="释放后数据将被清空且无法找回，确认释放？">
        <?= csrf_field() ?><input type="hidden" name="do" value="release"><button class="btn btn-danger btn-sm" type="submit" <?= $dis ?>>释放实例</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($inst['status'] === 'overdue'): ?>
    <p class="desc" style="margin-top:12px;">实例处于欠费停机状态，<?= (int) $inst['node_id'] > 0 ? '7 天内' : '72 小时内' ?>续费将自动恢复运行，超过期限数据将被删除。</p>
  <?php endif; ?>
</div>

<?php if (!empty($orders)): ?>
<div class="card" style="margin-top:18px;">
  <h3>关联订单</h3>
  <div class="table-wrap" style="margin-top:8px;">
    <table class="tbl">
      <thead><tr><th>订单号</th><th>内容</th><th>金额</th><th>时间</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="mono"><?= e($o['order_no']) ?></td>
          <td><?= e($o['title']) ?></td>
          <td>¥<?= e(money($o['amount'])) ?></td>
          <td class="mono"><?= e(dt($o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ((int) $inst['node_id'] > 0 && !$released): ?>
<div class="modal-mask" id="modalRebuild">
  <div class="modal" style="max-width:460px;">
    <h3>重装系统</h3>
    <p class="desc" style="font-size:13px;">
      实例 <b><?= e($inst['name']) ?></b> 当前系统：<code><?= e($inst['os']) ?></code>。
      重装将<span style="color:var(--danger);">清空磁盘上全部数据</span>，约 1-2 分钟完成；
      完成后将生成新的 ROOT 密码并在本页显示。
    </p>
    <form method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/action">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="rebuild">
      <div class="field">
        <label>选择系统镜像</label>
        <div class="rebuild-imgs">
          <?php $rebuildList = (array) ($planImages ?? []); ?>
          <?php foreach ($rebuildList as $im): ?>
            <label class="rebuild-img<?= $im === $inst['os'] ? ' on' : '' ?>">
              <input type="radio" name="img" value="<?= e($im) ?>" <?= $im === $inst['os'] ? 'checked' : '' ?>>
              <span><?= e($im) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!in_array($inst['os'], $rebuildList, true)): ?>
            <label class="rebuild-img on">
              <input type="radio" name="img" value="<?= e($inst['os']) ?>" checked>
              <span><?= e($inst['os']) ?></span>
            </label>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-actions" style="margin-top:14px;">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-danger" type="submit">确认重装</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
