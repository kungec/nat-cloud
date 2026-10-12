<?php /* NAT Cloud - 首页（原创文案与实现） */ ?>
<?php include __DIR__ . '/_head.php'; ?>

<section class="hero">
  <canvas id="heroFx" class="hero-fx" aria-hidden="true"></canvas>
  <div class="hero-inner">
    <div class="container">
      <div class="hero-kicker"><?= e(strtoupper(setting('site_name', 'NAT Cloud'))) ?></div>
      <h1>轻量 NAT 云主机<br><span class="dim">秒级开通 · 按小时计费</span></h1>
      <p class="hero-sub">NAT 共享 IP 架构，成本更低；下单即开通，不用随时释放。为跨境加速、开发测试与轻量业务而生。</p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="/dashboard/create">立即开通 →</a>
        <a class="btn btn-ghost btn-lg" href="#pricing">查看套餐</a>
      </div>
    </div>
  </div>
  <div class="hero-stats-wrap">
    <div class="container">
      <hr class="hero-sep">
      <div class="hero-stats">
        <?php foreach ($stats as $s): ?>
          <div><div class="v"><?= e($s['value']) ?></div><div class="l"><?= e($s['label']) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="features">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Features</div>
      <h2>该有的都有，多余的没有</h2>
      <p>围绕“快”与“省”构建的轻量云主机服务。</p>
    </div>
    <div class="grid grid-3">
      <div class="card">
        <div class="feature-ico"><?= nc_icon('zap', 20) ?></div>
        <h3>急速开通</h3>
        <p class="desc">下单后自动分配实例与端口段，一分钟内即可连接使用。</p>
      </div>
      <div class="card">
        <div class="feature-ico"><?= nc_icon('clock', 20) ?></div>
        <h3>小时计费</h3>
        <p class="desc">按量实例从余额逐小时扣费，随开随停，只为真正运行的时间付费。</p>
      </div>
      <div class="card">
        <div class="feature-ico"><?= nc_icon('globe', 20) ?></div>
        <h3>优质线路</h3>
        <p class="desc">香港节点接入，大陆方向低延迟、少丢包，适合远程办公与加速场景。</p>
      </div>
      <div class="card">
        <div class="feature-ico"><?= nc_icon('sliders', 20) ?></div>
        <h3>全生命周期管理</h3>
        <p class="desc">开机、关机、重启、续费、释放，控制台内一站式完成。</p>
      </div>
      <div class="card">
        <div class="feature-ico"><?= nc_icon('shield', 20) ?></div>
        <h3>安全可靠</h3>
        <p class="desc">独立端口段互不干扰，关键信息加密存储，全站 HTTPS 访问。</p>
      </div>
      <div class="card">
        <div class="feature-ico"><?= nc_icon('tool', 20) ?></div>
        <h3>便捷工单</h3>
        <p class="desc">账务、实例、网络问题在线提交，管理员尽快跟进处理。</p>
      </div>
    </div>
  </div>
</section>

<section class="section demo-split-sec">
  <div class="container demo-split">
    <div class="demo-copy">
      <h2>从下单到就绪，只要几秒</h2>
      <p class="demo-sub">在控制台选好套餐、付完款，香港节点的实例就已经在启动。运行中按小时计费，点一下停机，就只剩磁盘的存储费。</p>
      <ul class="demo-checks">
        <li><span class="ck">✓</span><span class="tx"><b>专线直连</b> — 香港节点接入，避开公网拥塞，晚高峰依然低延迟、低丢包。</span></li>
        <li><span class="ck">✓</span><span class="tx"><b>急速开通</b> — 付款完成即自动开通，几秒就绪，无需等待人工审核。</span></li>
        <li><span class="ck">✓</span><span class="tx"><b>小时计费</b> — 按实际使用的小时数计费，随时释放随时停止扣费，账单清清楚楚。</span></li>
      </ul>
    </div>
    <div class="inst-demo" id="instDemo">
      <div class="id-head">
        <span class="k">实例</span><b class="mono">xc-8k3f</b>
        <span class="tag run" id="idState">● 运行中</span>
      </div>
      <div class="id-body">
        <div class="id-steps">
          <div class="id-step"><span class="ck">✓</span>提交订单</div>
          <div class="id-step"><span class="ck">✓</span>支付确认</div>
          <div class="id-step"><span class="ck">✓</span>分配资源</div>
          <div class="id-step"><span class="ck">✓</span>启动系统</div>
          <div class="id-step"><span class="ck">✓</span>就绪 <em class="id-timer" id="idTimer">用时 0.0 秒</em></div>
        </div>
        <div class="id-info">
          <div><span>套餐</span><b>标准 · 1 vCPU / 1 GB</b></div>
          <div><span>地域</span><b>香港 · 香港</b></div>
          <div><span>公网 IP</span><b class="mono"><?= '203.0.113.' . mt_rand(2, 254) ?></b></div>
          <div><span>时价</span><b>¥0.03 / h</b></div>
          <div><span>运行时长</span><b class="mono" id="idUptime">00:00</b></div>
          <div><span>累计费用</span><b class="mono" id="idCost">¥0.0000</b></div>
        </div>
      </div>
      <div class="id-actions">
        <button type="button" class="btn btn-ghost btn-sm" id="idPower">⏻ 停机</button>
        <button type="button" class="btn btn-ghost btn-sm" id="idReplay">⟳ 重新演示</button>
      </div>
      <div class="id-note" id="idNote">试着点一下「停机」：CPU、内存、带宽立刻停止计费。</div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Demo</div>
      <h2>开通一个实例有多快？</h2>
      <p>从提交订单到实例就绪，全自动完成。</p>
    </div>
    <div class="demo-wrap">
      <div class="demo-window" id="provTerm">
        <div class="demo-titlebar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="term-replay" id="termReplay">⟳ 重新演示</span></div>
        <div class="demo-body">
          <div class="demo-row dr"><span class="k">提交订单</span><span>nat-a1b2c3d4 · Debian 12</span></div>
          <div class="demo-row dr"><span class="k">分配资源</span><span class="ok">1 vCPU · 1 GB · 10 GB NVMe</span></div>
          <div class="demo-row dr"><span class="k">分配端口段</span><span class="ok">20000-20014（共享 IP）</span></div>
          <div class="demo-row dr"><span class="k">启动系统</span><span class="ok">done</span></div>
          <div class="demo-row dr"><span class="k">状态</span><span class="ok ok-strong">RUNNING ✓ 就绪</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Images</div>
      <h2>常用系统镜像</h2>
      <p>主流 Linux 发行版、Windows 与应用镜像开箱即用。</p>
    </div>
    <div class="chip-grid" style="justify-content:center;">
      <span class="chip"><b>Ubuntu</b> 24.04 / 22.04</span>
      <span class="chip"><b>Debian</b> 12</span>
      <span class="chip"><b>AlmaLinux</b> 9</span>
      <span class="chip"><b>Rocky Linux</b> 9</span>
      <span class="chip"><b>Windows Server</b> 2022</span>
      <span class="chip"><b>Docker</b></span>
      <span class="chip"><b>宝塔面板</b></span>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Network</div>
      <h2>线路与节点</h2>
      <p>优质入境线路持续扩充中。</p>
    </div>
    <div class="chip-grid" style="justify-content:center;">
      <?php
      $homeNodes = [];
      try {
          foreach (db_all('SELECT name, status FROM nodes ORDER BY sort ASC, id ASC') as $hn) {
              $short = trim((string) preg_replace('~\s*LXC.*$~', '', (string) $hn['name']));
              $homeNodes[] = ['name' => $short !== '' ? $short : $hn['name'], 'on' => (int) $hn['status'] === 1];
          }
      } catch (Throwable $e) {
          $homeNodes = [['name' => '香港', 'on' => true], ['name' => '日本', 'on' => false], ['name' => '美国', 'on' => false]];
      }
      if (!$homeNodes) {
          $homeNodes = [['name' => '香港', 'on' => true], ['name' => '日本', 'on' => false], ['name' => '美国', 'on' => false]];
      }
      foreach ($homeNodes as $hn): ?>
      <span class="chip"><b><?= e($hn['name']) ?> NAT</b> <span class="st <?= $hn['on'] ? 'run' : 'soon' ?>"><?= $hn['on'] ? '运行中' : '即将上线' ?></span></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="pricing" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Pricing</div>
      <h2>节点与套餐</h2>
      <p>包年包月按月预付更省心，按量计费按小时从余额扣。</p>
    </div>
    <div class="grid grid-3">
      <?php if (empty($plans)): ?>
        <div class="empty" style="grid-column:1/-1;">套餐即将上架，敬请期待。</div>
      <?php endif; ?>
      <?php foreach ($plans as $p): $soldOut = (int) $p['stock_left'] <= 0; ?>
        <div class="card plan-card<?= $soldOut ? ' soldout' : '' ?>">
          <?php if ($soldOut): ?><span class="plan-badge" style="background:var(--danger);color:#fff;">已售罄</span>
          <?php elseif (!empty($p['badge'])): ?><span class="plan-badge"><?= e($p['badge']) ?></span><?php endif; ?>
          <div class="plan-name"><?= e($p['name']) ?></div>
          <div class="plan-node"><?= e($p['node_name']) ?> · <?= e($p['region']) ?></div>
          <div class="plan-price">
            <span class="num">¥<?= e(money($p['monthly_price'])) ?></span><span class="per"> /月</span>
            <?php if ((float) $p['hourly_price'] > 0): ?><div class="hourly">按量 ¥<?= e(money4($p['hourly_price'])) ?>/小时</div><?php endif; ?>
          </div>
          <ul class="plan-specs">
            <li><span>vCPU</span><span><?= (int) $p['cpu'] ?> 核</span></li>
            <li><span>内存</span><span><?= (int) $p['ram_mb'] >= 1024 ? (round($p['ram_mb'] / 1024 * 10) / 10) . ' GB' : (int) $p['ram_mb'] . ' MB' ?></span></li>
            <li><span>NVMe 磁盘</span><span><?= (int) $p['disk_gb'] ?> GB</span></li>
            <li><span>带宽</span><span><?= (int) $p['bandwidth_mbps'] ?> Mbps</span></li>
            <li><span>月流量</span><span><?= (int) $p['traffic_gb'] ?> GB</span></li>
            <li><span>NAT 类型</span><span><?= e($p['nat_type']) ?></span></li>
            <li><span>剩余库存</span><span><?= $soldOut ? '已售罄' : (int) $p['stock_left'] . ' 台' ?></span></li>
          </ul>
          <?php if ($soldOut): ?>
            <button class="btn btn-primary" disabled>已售罄 · 暂停购买</button>
          <?php else: ?>
            <a class="btn btn-primary" href="/dashboard/create">立即开通</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="faq" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div class="kicker">FAQ</div>
      <h2>常见问题</h2>
    </div>
    <div class="faq">
      <details>
        <summary>什么是 NAT 云主机？和独立 IP 的 VPS 有什么区别？</summary>
        <div class="a">NAT 主机多个用户共享同一个公网 IP，每位用户分配一段专属端口（TCP/UDP 通用）和一个独立 SSH 端口，价格远低于独立 IP 主机。适合代理转发、开发测试、轻量建站等场景；如需 80/443 建站或独立 IP，请选择独立 IP 产品。</div>
      </details>
      <details>
        <summary>按量计费怎么扣费？停机还收费吗？</summary>
        <div class="a">按量实例按整小时从余额扣费，手动关机后停止扣费。余额不足会自动转为欠费停机，欠费超过 72 小时实例将被释放、数据清空且无法找回。</div>
      </details>
      <details>
        <summary>包年包月到期后会怎样？</summary>
        <div class="a">到期后实例自动关机进入欠费状态，72 小时内续费即可恢复；超过 72 小时未续费将被释放。</div>
      </details>
      <details>
        <summary>怎么充值？支持哪些支付方式？</summary>
        <div class="a">登录控制台后在「钱包」提交充值订单，管理员确认后余额实时到账。如需其他充值方式，请提交工单联系管理员。</div>
      </details>
    </div>
  </div>
</section>

<section class="container">
  <div class="cta">
    <h2>准备好开始了吗？</h2>
    <p>注册即可开通你的第一台 NAT 云主机。</p>
    <a class="btn btn-primary btn-lg" href="/auth/register">免费注册</a>
  </div>
</section>

<?php include __DIR__ . '/_foot.php'; ?>
