<?php /* NAT Cloud - 邀请返利 */ ?>
<?php include NC_ROOT . '/app/views/_head.php'; ?>
<section class="section" style="padding:40px 0;">
  <div class="container" style="max-width:860px;">
    <div class="stat-grid stat-grid-3" style="margin-bottom:26px;">
      <div class="card" style="padding:20px;">
        <div style="font-size:14px;color:var(--text-dim);">已邀请用户</div>
        <div style="font-size:28px;font-weight:600;margin-top:8px;font-variant-numeric:tabular-nums;"><?= (int) $stats['invited'] ?></div>
      </div>
      <div class="card" style="padding:20px;">
        <div style="font-size:14px;color:var(--text-dim);">累计返佣</div>
        <div style="font-size:28px;font-weight:600;margin-top:8px;font-variant-numeric:tabular-nums;">¥<?= e(money($stats['commission'])) ?></div>
      </div>
      <div class="card" style="padding:20px;">
        <div style="font-size:14px;color:var(--text-dim);">返佣比例</div>
        <div style="font-size:28px;font-weight:600;margin-top:8px;font-variant-numeric:tabular-nums;"><?= (int) $percent ?>%</div>
      </div>
    </div>

    <div class="card" style="padding:24px;margin-bottom:22px;">
      <div style="font-size:15px;font-weight:600;margin-bottom:10px;">你的专属邀请链接</div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input class="input chip-copy" type="text" readonly value="<?= e($link) ?>" onclick="this.select()" style="font-family:var(--mono);font-size:12.5px;">
        <button class="btn btn-primary btn-sm chip-copy" type="button" data-copy="<?= e($link) ?>">复制链接</button>
      </div>
      <p class="desc" style="font-size:13px;color:var(--text-dim);margin:12px 0 0;">好友通过此链接注册并充值后，你将按充值金额的 <b><?= (int) $percent ?>%</b> 获得返佣，直接进入账户余额，可提现用途不限。</p>
    </div>

    <div class="table-wrap" style="margin-bottom:22px;">
      <div style="font-size:15px;font-weight:600;padding:14px 14px 6px;">已邀请用户</div>
      <?php if (empty($invited)): ?>
        <div class="empty">还没有邀请记录，快去分享你的链接吧。</div>
      <?php else: ?>
        <table class="tbl">
          <thead><tr><th>用户</th><th>注册时间</th><th>状态</th></tr></thead>
          <tbody>
          <?php foreach ($invited as $iv): ?>
            <tr>
              <td class="mono"><?= e($iv['username']) ?></td>
              <td class="mono" style="font-size:12.5px;"><?= e(dt($iv['created_at'])) ?></td>
              <td><span class="tag <?= (int) $iv['status'] === 1 ? 'run' : 'closed' ?>"><?= (int) $iv['status'] === 1 ? '正常' : '禁用' ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <div style="font-size:15px;font-weight:600;padding:14px 14px 6px;">返佣明细</div>
      <?php if (empty($commissions)): ?>
        <div class="empty">暂无返佣记录</div>
      <?php else: ?>
        <table class="tbl">
          <thead><tr><th>金额</th><th>余额</th><th>说明</th><th>时间</th></tr></thead>
          <tbody>
          <?php foreach ($commissions as $c): ?>
            <tr>
              <td class="mono" style="color:#0a7d33;">+<?= e(money((float) $c['amount'])) ?></td>
              <td class="mono"><?= e(money((float) $c['balance_after'])) ?></td>
              <td style="font-size:13px;"><?= e($c['remark']) ?></td>
              <td class="mono" style="font-size:12.5px;"><?= e(dt($c['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php include NC_ROOT . '/app/views/_foot.php'; ?>
