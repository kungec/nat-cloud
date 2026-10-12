<?php /* NAT Cloud - 管理后台：用户详情 */ ?>
<?php ob_start(); ?>
<div class="grid grid-2">
  <div class="card">
    <h3>用户信息</h3>
    <div class="kv" style="margin-top:12px;">
      <span class="k">ID</span><span class="mono"><?= (int) $u['id'] ?></span>
      <span class="k">用户名</span><span><?= e($u['username']) ?></span>
      <span class="k">邮箱</span><span class="mono"><?= e($u['email']) ?></span>
      <span class="k">余额</span><span>¥<?= e(money($u['balance'])) ?></span>
      <span class="k">实名状态</span><span><?php
          $kmap = [0 => '未认证', 1 => '待审核', 2 => '已通过', 3 => '已驳回'];
          echo e($kmap[(int) $u['kyc_status']] ?? '-');
      ?></span>
      <span class="k">状态</span><span><?= (int) $u['status'] === 1 ? '正常' : '禁用' ?></span>
      <span class="k">注册时间</span><span><?= e(dt($u['created_at'])) ?></span>
      <span class="k">最近登录</span><span><?= e(dt($u['last_login_at'])) ?>（<?= e($u['last_ip']) ?>）</span>
    </div>
  </div>
  <div class="card">
    <h3>余额调整</h3>
    <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/balance" style="margin-top:12px;">
      <?= csrf_field() ?>
      <div class="field">
        <label>金额（正数增加 / 负数扣减）</label>
        <input class="input" type="number" step="0.01" name="amount" placeholder="如 50 或 -20" required>
      </div>
      <div class="field">
        <label>备注</label>
        <input class="input" type="text" name="remark" maxlength="100" placeholder="调整原因">
      </div>
      <button class="btn btn-primary" type="submit" data-confirm="确认调整该用户余额？">执行调整</button>
    </form>
  </div>
</div>

<h3 style="font-size:15px;margin:22px 0 10px;">名下实例</h3>
<div class="table-wrap">
  <?php if (empty($insts)): ?>
    <div class="empty">暂无实例</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>实例</th><th>状态</th><th>配置</th><th>计费</th><th>创建时间</th></tr></thead>
      <tbody>
      <?php foreach ($insts as $i): ?>
        <tr>
          <td class="mono"><?= e($i['name']) ?></td>
          <td><span class="tag <?= e($i['status']) ?>"><?= e($i['status']) ?></span></td>
          <td class="mono"><?= (int) $i['cpu'] ?>C / <?= e(number_format($i['ram_mb'] / 1024, 1)) ?>G / <?= (int) $i['disk_gb'] ?>G</td>
          <td><?= $i['billing_mode'] === 'monthly' ? '包月 ¥' . e(money($i['monthly_price'])) : '按量 ¥' . e(money4($i['hourly_price'])) . '/h' ?></td>
          <td class="mono"><?= e(dt($i['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<h3 style="font-size:15px;margin:22px 0 10px;">最近流水</h3>
<div class="table-wrap">
  <?php if (empty($tx)): ?>
    <div class="empty">暂无流水</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>时间</th><th>类型</th><th>说明</th><th style="text-align:right;">变动</th><th style="text-align:right;">余额</th></tr></thead>
      <tbody>
      <?php foreach ($tx as $t): ?>
        <tr>
          <td class="mono"><?= e(dt($t['created_at'])) ?></td>
          <td><?= e($t['type']) ?></td>
          <td><?= e($t['remark']) ?></td>
          <td style="text-align:right;" class="<?= (float) $t['amount'] >= 0 ? 'amt-in' : 'amt-out' ?>"><?= (float) $t['amount'] >= 0 ? '+' : '' ?><?= e(money($t['amount'])) ?></td>
          <td style="text-align:right;" class="mono"><?= e(money($t['balance_after'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
