<?php /* NAT Cloud - 自定义 NAT 端口转发管理 */ ?>
<?php
$backUrl = '/dashboard/services/' . (int) $inst['id'];
$backText = '实例 ' . $inst['name'];
[$pb, $pe] = billing_node_ports_of($inst);
$rulesByPort = [];
foreach ($rules as $r) {
    $rulesByPort[(int) $r['port']] = $r;
}
$protoName = ['tcp' => 'TCP', 'udp' => 'UDP', 'both' => 'TCP+UDP'];
?>
<?php ob_start(); ?>
<div class="card" style="max-width:720px;">
  <h3>端口段说明</h3>
  <p class="desc" style="font-size:13px;">你的公网端口段 <code><?= $pb ?>-<?= $pe ?></code>（共 <?= $pe - $pb + 1 ?> 个，来自套餐）。其中
    <code><?= $pb ?></code> 固定用于 SSH 登录，不可修改；其余端口默认转发到容器内<b>相同端口</b>，
    你可以在下面把任意端口改写到容器内的其他端口（如 <?= $pb + 1 ?> → 80）。</p>
  <p class="desc" style="font-size:13px;">规则修改约 1 分钟内在节点生效（节点每分钟同步一次指令）。</p>
</div>

<div class="card" style="max-width:720px;margin-top:16px;">
  <h3>添加 / 更新转发规则</h3>
  <form method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/nat/save" style="margin-top:10px;">
    <?= csrf_field() ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
      <div class="field" style="margin:0;">
        <label>公网端口（<?= $pb + 1 ?> - <?= $pe ?>）</label>
        <input class="input" style="width:130px;" type="number" name="port" min="<?= $pb + 1 ?>" max="<?= $pe ?>" value="<?= $pb + 1 ?>" required>
      </div>
      <div class="field" style="margin:0;">
        <label>转发到容器内端口（1-65535）</label>
        <input class="input" style="width:130px;" type="number" name="to" min="1" max="65535" value="80" required>
      </div>
      <div class="field" style="margin:0;">
        <label>协议</label>
        <select class="input" style="width:130px;" name="proto">
          <option value="both">TCP+UDP</option>
          <option value="tcp">仅 TCP</option>
          <option value="udp">仅 UDP</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">保存规则</button>
    </div>
  </form>
  <p class="desc" style="font-size:12.5px;margin-top:10px;color:var(--text-faint);">
    同一公网端口只能有一条规则（重复提交会更新已有规则）；SSH 端口 <?= $pb ?> 已被系统占用，无法改作转发。
  </p>
</div>

<div class="table-wrap" style="max-width:720px;margin-top:16px;">
  <table class="tbl">
    <thead><tr><th>公网端口</th><th>转发到容器端口</th><th>协议</th><th>来源</th><th style="text-align:right;">操作</th></tr></thead>
    <tbody>
      <tr>
        <td class="mono"><?= $pb ?></td>
        <td class="mono">22（SSH）</td>
        <td>TCP</td>
        <td>系统固定</td>
        <td></td>
      </tr>
      <?php foreach ($rules as $r): ?>
        <tr>
          <td class="mono"><?= (int) $r['port'] ?></td>
          <td class="mono"><?= (int) $r['to_port'] ?></td>
          <td><?= e($protoName[$r['proto']] ?? $r['proto']) ?></td>
          <td>自定义</td>
          <td style="text-align:right;">
            <form class="inline-form" method="post" action="/dashboard/services/<?= (int) $inst['id'] ?>/nat/del" data-confirm="删除端口 <?= (int) $r['port'] ?> 的转发规则并恢复默认？">
              <?= csrf_field() ?><input type="hidden" name="port" value="<?= (int) $r['port'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($rules) === 0): ?>
        <tr><td colspan="5" class="empty">暂无自定义规则：所有业务端口默认转发到容器内相同端口（TCP+UDP）。</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
