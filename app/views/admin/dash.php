<?php /* NAT Cloud - 管理后台仪表盘 */ ?>
<?php ob_start(); ?>
<div class="stat-grid">
  <div class="stat"><div class="l">用户总数</div><div class="v"><?= (int) $usersTotal ?></div><div class="s">今日新增 <?= (int) $usersToday ?></div></div>
  <div class="stat"><div class="l">运行中实例</div><div class="v"><?= (int) $instRun ?></div><div class="s">未释放共 <?= (int) $instTotal ?></div></div>
  <div class="stat"><div class="l">用户余额合计</div><div class="v">¥<?= e(money($balanceSum)) ?></div><div class="s">负债口径</div></div>
  <div class="stat"><div class="l">今日收入</div><div class="v">¥<?= e(money($incomeToday)) ?></div><div class="s">已支付订单</div></div>
</div>
<div class="stat-grid stat-grid-3">
  <div class="stat"><div class="l">待确认充值</div><div class="v"><?= (int) $pendingTopups ?></div><div class="s"><a href="/admin/finance">去处理 →</a></div></div>
  <div class="stat"><div class="l">未关闭工单</div><div class="v"><?= (int) $openTickets ?></div><div class="s"><a href="/admin/tickets">去处理 →</a></div></div>
  <div class="stat"><div class="l">待审实名</div><div class="v"><?= (int) $pendingKyc ?></div><div class="s"><a href="/admin/kyc">去处理 →</a></div></div>
</div>

<div class="grid grid-2">
  <div>
    <h3 style="font-size:15px;margin-bottom:10px;">最新注册用户</h3>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>余额</th><th>注册时间</th></tr></thead>
        <tbody id="dashUsersBody"><?= $recentUsers['html'] ?></tbody>
      </table>
    </div>
    <div class="pager pager-sm" id="dashUsersPager"><?= $recentUsers['pager'] ?></div>
  </div>
  <div>
    <h3 style="font-size:15px;margin-bottom:10px;">最新订单</h3>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>单号</th><th>用户</th><th>内容</th><th>金额</th><th>状态</th></tr></thead>
        <tbody id="dashOrdersBody"><?= $recentOrders['html'] ?></tbody>
      </table>
    </div>
    <div class="pager pager-sm" id="dashOrdersPager"><?= $recentOrders['pager'] ?></div>
  </div>
</div>
<script>
(function () {
  function load(block, page) {
    fetch('/admin/dash/' + block.toLowerCase() + '?page=' + page, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        document.getElementById('dash' + block + 'Body').innerHTML = d.html;
        document.getElementById('dash' + block + 'Pager').innerHTML = d.pager;
      });
  }
  ['Users', 'Orders'].forEach(function (block) {
    var pager = document.getElementById('dash' + block + 'Pager');
    if (pager) pager.addEventListener('click', function (e) {
      var b = e.target.closest('[data-page]');
      if (b) load(block, b.getAttribute('data-page'));
    });
  });
})();
</script>
<?php $content = ob_get_clean();
$subTitle = '站点运营数据一览';
include __DIR__ . '/_shell.php'; ?>
