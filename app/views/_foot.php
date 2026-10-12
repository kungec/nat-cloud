<footer class="site">
  <div class="container">
    <div class="foot-grid">
      <div>
        <a class="brand" href="/"><span class="brand-logo"><img src="/assets/img/brand.png" alt="logo" style="width:26px;height:26px;object-fit:contain;display:block;margin:0;"></span><?= e(setting('site_name', 'NAT Cloud')) ?></a>
        <p style="margin-top:12px;">轻量 NAT 云主机 · 秒级开通，按小时计费。<br>为开发者和跨境业务提供低延迟的接入方案。</p>
      </div>
      <div>
        <h4>产品</h4>
        <a href="/#pricing">套餐</a>
        <a href="/dashboard">控制台</a>
      </div>
      <div>
        <h4>支持</h4>
        <a href="/#faq">常见问题</a>
        <a href="/news">公告动态</a>
        <a href="/dashboard/tickets/new">提交工单</a>
      </div>
    </div>
    <div>© <?= date('Y') ?> <?= e(setting('site_name', 'NAT Cloud')) ?>. 保留所有权利。</div>
  </div>
</footer>
<script src="/assets/js/main.js?v=<?= is_file(NC_ROOT . '/public/assets/js/main.js') ? filemtime(NC_ROOT . '/public/assets/js/main.js') : '1' ?>"></script>
</body>
</html>
