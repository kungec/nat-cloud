<?php /* NAT Cloud - 创建实例（对齐演示站：左配置 + 右 sticky 费用栏） */
$backUrl = '/dashboard/services'; $backText = '我的服务';
$u = auth_user();
$base = (float) setting('price_base', '23.00');
$cpuP = (float) setting('price_cpu', '10.00');
$ramP = (float) setting('price_ram', '12.00');
$diskP = (float) setting('price_disk', '0.60');
$stmt = db()->prepare("SELECT COUNT(*) FROM instances WHERE uid = ? AND billing_mode = 'hourly' AND status != 'released'");
$stmt->execute([(int) $u['id']]);
$hourlyUsed = (int) $stmt->fetchColumn();
$imgLabels = ['debian11' => 'Debian 11', 'debian12' => 'Debian 12'];
$hasPlans = !empty($nodePlans);
/* 自定义配置（本机模拟）只有在存在上架的本机套餐时才开放 */
$hasCustom = (bool) db_val('SELECT COUNT(*) FROM plans WHERE status = 1 AND node_id = 0');
$showTabs = $hasPlans && $hasCustom;
function plan_ram_txt($mb) { return (int) $mb >= 1024 ? (round($mb / 1024 * 10) / 10) . 'G' : (int) $mb . 'M'; }
?>
<?php ob_start(); ?>
<div class="sub" style="margin:-10px 0 20px;">先选计费方式，再选节点和配置。包年包月按月预付更省心，按量计费按小时从余额扣。</div>

<form method="post" action="/dashboard/create" id="createForm"
      data-base="<?= e($base) ?>" data-cpu-p="<?= e($cpuP) ?>" data-ram-p="<?= e($ramP) ?>"
      data-disk-p="<?= e($diskP) ?>" data-balance="<?= e($u['balance']) ?>"
      data-oses="<?= e(json_encode($oses)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="mode" value="monthly" id="modeInput">
  <input type="hidden" name="plan_id" value="" id="planIdInput">
  <input type="hidden" name="cpu" value="1" id="cpuInput">
  <input type="hidden" name="ram_gb" value="1" id="ramInput">
  <input type="hidden" name="disk_gb" value="10" id="diskInput">

  <div class="mode-cards" style="margin-bottom:20px;">
    <div class="mode-card on" data-mode="monthly">
      <span class="rec">推荐</span>
      <div class="t"><span class="mode-ico"><?= nc_icon('receipt', 18) ?></span>包年包月</div>
      <div class="d">按月预付，到期自动续费；停机不额外收费，适合长期运行。</div>
    </div>
    <div class="mode-card" data-mode="hourly">
      <div class="t"><span class="mode-ico"><?= nc_icon('clock') ?></span>按量计费</div>
      <div class="d">每小时从余额扣费，随开随停，停机不扣费；适合临时测试。<br>已用 <?= (int) $hourlyUsed ?> / 5 台</div>
    </div>
  </div>
  <div class="warn-box" id="hourlyUnsupportedTip" style="display:none;margin:-8px 0 16px;">当前套餐未开放按量计费，已为您切换为包年包月。</div>

  <div class="create-grid">
    <div>
      <?php if ($showTabs): ?>
      <div class="tabs" style="margin-bottom:16px;">
        <button type="button" class="on" data-prod="plan">套餐实例 · 自动开通</button>
        <button type="button" data-prod="custom">自定义配置</button>
      </div>
      <?php endif; ?>
      <?php if ($hasPlans): ?>
      <div id="planSection">
        <div class="card" style="margin-bottom:16px;">
          <h3>选择套餐</h3>
          <div class="plan-pick" style="margin-top:12px;">
            <?php $firstAvail = true; foreach ($nodePlans as $i => $p): $soldOut = (int) $p['stock_left'] <= 0; ?>
              <div class="opt<?= $soldOut ? ' soldout' : ($firstAvail ? ' on' : '') ?>" data-soldout="<?= $soldOut ? '1' : '0' ?>" data-plan='<?= e(json_encode([
                  'id' => (int) $p['id'], 'name' => $p['name'], 'node_name' => $p['node_name'],
                  'cpu' => (int) $p['cpu'], 'ram_gb' => (int) round($p['ram_mb'] / 1024 * 10) / 10, 'disk_gb' => (int) $p['disk_gb'],
                  'monthly' => (float) $p['monthly_price'], 'hourly' => (float) $p['hourly_price'],
                  'port_count' => (int) $p['port_count'], 'traffic_gb' => (int) $p['traffic_gb'],
                  'images' => $p['images'],
                  'ram_mb' => (int) $p['ram_mb'], 'bandwidth_mbps' => (int) $p['bandwidth_mbps'],
                  'desc' => (string) $p['description'],
                  'cycle_prices' => [
                      'monthly' => (float) $p['monthly_price'],
                      'quarterly' => billing_cycle_price($p, 'quarterly'),
                      'semiannually' => billing_cycle_price($p, 'semiannually'),
                      'annually' => billing_cycle_price($p, 'annually'),
                  ],
              ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>'>
                <div class="n"><?= e($p['name']) ?><?= $i === 0 ? ' <span class="tag run" style="font-size:11px;">热门</span>' : '' ?><?= $soldOut ? ' <span class="tag closed" style="font-size:11px;">已售罄</span>' : ' <span class="tag" style="font-size:11px;">余 ' . (int) $p['stock_left'] . ' 台</span>' ?></div>
                <div><?= e($p['node_name']) ?> · <?= (int) $p['cpu'] ?>C / <?= plan_ram_txt($p['ram_mb']) ?> / <?= (int) $p['disk_gb'] ?>G · <?= (int) $p['bandwidth_mbps'] ?>M 带宽</div>
                <div>¥<?= e(money($p['monthly_price'])) ?>/月<?= (float) $p['hourly_price'] > 0 ? ' · ¥' . e(money4($p['hourly_price'])) . '/时' : ' <span class="tag closed" style="font-size:11px;">仅包月</span>' ?></div>
                <?php if (trim((string) $p['description']) !== ''): ?><div class="plan-desc"><?= e($p['description']) ?></div><?php endif; ?>
              </div>
            <?php if (!$soldOut) { $firstAvail = false; } endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!$hasPlans && !$hasCustom): ?>
      <div class="card" style="margin-bottom:16px;">
        <div class="empty">暂无可购买的套餐，敬请期待。</div>
      </div>
      <?php endif; ?>
      <?php if ($hasCustom): ?>
      <div id="customSection" style="display:<?= $hasPlans ? 'none' : '' ?>;">

      <div class="card" style="margin-bottom:16px;">
        <h3>节点</h3>
        <div class="net-chip"><?= e(setting('node_name', '东京 #1')) ?> Performance</div>
        <div class="desc" style="font-size:13px;color:var(--text-faint);margin-top:8px;">EPYC 高性能机型 · NAT 共享 IP 架构</div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="slider-row">
          <div class="slider-top">
            <label>vCPU</label>
            <span class="stepper"><button type="button" data-step="cpu" data-d="-1">−</button><input type="text" id="cpuShow" value="1" readonly><button type="button" data-step="cpu" data-d="1">＋</button></span>
          </div>
          <input type="range" class="slider-range" id="cpuRange" min="1" max="16" step="1" value="1">
          <div class="slider-minmax"><span>1</span><span>16</span></div>
        </div>
        <div class="slider-row">
          <div class="slider-top">
            <label>内存</label>
            <span class="stepper"><button type="button" data-step="ram" data-d="-1">−</button><input type="text" id="ramShow" value="1" readonly><button type="button" data-step="ram" data-d="1">＋</button></span>
          </div>
          <input type="range" class="slider-range" id="ramRange" min="1" max="16" step="1" value="1">
          <div class="slider-minmax"><span>1</span><span>16</span></div>
        </div>
        <div class="slider-row" style="margin-bottom:6px;">
          <div class="slider-top">
            <label>磁盘</label>
            <span class="stepper"><button type="button" data-step="disk" data-d="-1">−</button><input type="text" id="diskShow" value="10" readonly><button type="button" data-step="disk" data-d="1">＋</button></span>
          </div>
          <input type="range" class="slider-range" id="diskRange" min="10" max="100" step="5" value="10">
          <div class="slider-minmax"><span>10</span><span>100</span></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <h3>网卡类型<span class="req">*</span></h3>
        <p class="desc" style="font-size:13px;">主网卡（net0）的线路，开通后不能更换。</p>
        <div class="net-card" style="margin-top:12px;">
          <div class="n">Skyline 网络</div>
          <div class="d">net0</div>
        </div>
        <div class="info-note"><?= nc_icon('info', 16) ?>NTT + IIJ + Softbank 多线聚合，国际方向尽力而为优化。</div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <h3>月流量<span class="req">*</span></h3>
        <p class="desc" style="font-size:13px;">1000 GB 不限上下行合计，用完限速、下个计费周期自动恢复。流量越多，机器带宽能拉得越足。</p>
      </div>

      </div><!-- /customSection -->
      <?php endif; ?>

      <div class="card" style="margin-bottom:16px;">
        <h3>系统镜像<span class="req">*</span></h3>
        <div class="os-dd" id="osGrid" style="margin-top:10px;max-width:340px;"></div>
        <input type="hidden" name="os" id="osSelect" value="">
      </div>
    </div>

    <div class="summary-card">
      <div class="card" id="sumPlanBox" <?= $hasPlans ? '' : 'style="display:none;"' ?>>
        <h3 style="font-size:17px;">费用</h3>
        <div class="sum-row"><span>套餐</span><span id="sumPlanName">-</span></div>
        <div id="sumPlanDesc" class="plan-desc" style="display:none;"></div>
        <div class="sum-row"><span>配置</span><span id="sumPlanSpec">-</span></div>
        <div class="sum-row"><span>系统镜像</span><span id="sumPlanImage">-</span></div>
        <div class="sum-row"><span>NAT 端口</span><span id="sumPlanPorts">-</span></div>
        <div class="sum-row"><span>带宽</span><span id="sumPlanBandwidth">-</span></div>
        <div class="sum-row"><span>月流量</span><span id="sumPlanTraffic">-</span></div>
        <div class="sum-row"><span>月价</span><span id="sumPlanMonthly">-</span></div>
        <div class="field" id="planCycleRow" style="margin-top:6px;">
          <label>购买时长</label>
          <select class="input" name="cycle" id="planCycle">
            <option value="monthly">月付</option>
            <option value="quarterly">季付</option>
            <option value="semiannually">半年付</option>
            <option value="annually">年付</option>
          </select>
          <div id="planCycleHourly" style="display:none;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-dim);font-size:13.5px;">按小时计费 · 随开随停，停机不扣费</div>
        </div>
        <div class="field" style="margin-top:6px;">
          <label>优惠码（可选，仅包年包月可用）</label>
          <input class="input" type="text" name="coupon" maxlength="32" placeholder="有优惠码请在此输入" autocomplete="off">
        </div>
        <div class="sum-row" style="border-bottom:0;"><span>当前余额</span><span>¥<?= e(money($u['balance'])) ?></span></div>
        <div class="pay-box">
          <span class="l">应付<b id="payPlanMonths">1 个月</b></span>
          <span class="num" id="payPlanAmount">¥0.00</span>
        </div>
        <div id="planInsufficient" style="display:none;">
          <div class="warn-box" id="planInsufficientMsg">余额不足以支付所选时长，请先充值。</div>
          <a class="btn btn-ghost btn-block" href="/dashboard/wallet" style="margin-top:12px;">充值</a>
        </div>
        <button class="btn btn-light btn-lg btn-block" type="submit" id="planSubmit" style="margin-top:16px;">提交订单</button>
        <div class="tip" style="color:var(--text-faint);font-size:12.5px;margin-top:10px;">付款成功后系统自动部署，约 1-2 分钟就绪。</div>
      </div>

      <div class="card" id="sumCustomBox" <?= $hasPlans ? 'style="display:none;"' : '' ?>>
        <h3 style="font-size:17px;">费用确认</h3>
        <div class="sum-row"><span>计费方式</span><span id="sumMode">包年包月</span></div>
        <div class="sum-row"><span>配置</span><span id="sumSpec">1 vCPU · 1 GB · 10 GB</span></div>
        <div class="sum-row"><span>NAT 端口</span><span>共享 IP · 独立端口段</span></div>
        <div class="field" style="margin-top:10px;" id="monthsRow">
          <label>购买时长（月，包年包月）</label>
          <input class="input" type="number" name="months" id="monthsInput" min="1" max="36" value="1">
        </div>
        <div class="sum-total"><span style="color:var(--text-dim);font-size:14px;">应付金额</span><span class="num" id="sumTotal">¥0.00</span></div>
        <button class="btn btn-light btn-lg btn-block" type="submit" id="customSubmit" style="margin-top:16px;">提交订单</button>
        <div class="tip" style="color:var(--text-faint);font-size:12.5px;margin-top:10px;">提交即表示同意从账户余额扣费；按量实例要求余额足够运行 24 小时。</div>
      </div>
    </div>
  </div>
</form>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
