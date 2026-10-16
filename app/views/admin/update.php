<?php /* NAT Cloud - 管理后台：系统更新（GitHub 一键同步） */ ?>
<?php ob_start(); ?>
<div style="max-width:860px;">

  <div class="card" style="margin-bottom:18px;">
    <div style="font-size:15px;font-weight:600;margin-bottom:14px;">当前安装</div>
    <?php if ($cur['sha'] !== ''): ?>
      <div class="kv" style="grid-template-columns:auto 1fr;">
        <span>版本</span><span class="mono"><?= e($cur['short']) ?></span>
        <span>上游提交时间</span><span><?= e($cur['time']) ?></span>
        <span>本地更新时间</span><span><?= e($cur['updated_at']) ?></span>
      </div>
    <?php else: ?>
      <div class="tip" style="margin:0;">发行版 / 手动安装（尚无版本记录）。执行一次「立即更新」后，系统会记录当前版本并开始跟踪 GitHub 更新。</div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
      <div style="font-size:15px;font-weight:600;">GitHub 最新版本</div>
      <?php if ($remote !== null): ?>
        <?php if ($hasUpdate): ?>
          <span class="tag" style="background:rgba(240,180,0,.14);color:#8a6d00;">有可用更新</span>
        <?php else: ?>
          <span class="tag" style="background:rgba(16,163,74,.12);color:#0a7d33;">已是最新</span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if ($checkErr !== ''): ?>
      <div class="tip" style="color:#b3261e;">检查失败：<?= e($checkErr) ?></div>
    <?php elseif ($remote !== null): ?>
      <div class="kv" style="grid-template-columns:auto 1fr;">
        <span>最新提交</span><span class="mono"><?= e($remote['short']) ?></span>
        <span>提交说明</span><span><?= e($remote['message']) ?></span>
        <span>提交时间</span><span><?= e($remote['time']) ?><?= !empty($remote['cached']) ? '（缓存，可点下方刷新）' : '' ?></span>
      </div>
      <?php if (count($commits) > 0): ?>
        <div style="font-size:13px;color:var(--text-dim);margin:16px 0 8px;">最近更新记录</div>
        <div style="border:1px solid var(--border-soft);border-radius:10px;overflow:hidden;">
          <?php foreach ($commits as $c): ?>
            <div style="display:flex;gap:10px;padding:9px 14px;font-size:13px;border-bottom:1px solid var(--border-soft);align-items:baseline;">
              <span class="mono" style="color:var(--text-faint);flex:none;"><?= e($c['short']) ?></span>
              <span style="flex:1;min-width:0;word-break:break-all;"><?= e($c['message']) ?></span>
              <span style="color:var(--text-faint);flex:none;font-size:12px;"><?= e($c['time']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
      <form method="post" action="/admin/system/update/check" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit">检查更新</button>
      </form>
      <form method="post" action="/admin/system/update/apply" data-confirm="立即更新会把 GitHub 主分支的最新程序换入站点（.env、install.lock 与 storage 数据不受影响），旧文件自动备份到 storage/backup-时间戳/。确认更新？">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit" <?= ($remote === null) ? 'disabled' : '' ?>>立即更新</button>
      </form>
    </div>
    <div class="tip" style="margin-top:12px;">
      更新内容为程序文件（app / cron / install / public / web.config 等），数据库新增字段会在首次访问时自动补齐；更新瞬间站点会有亚秒级切换窗口。更新源：github.com/<?= e(NC_UPDATE_REPO) ?>（主分支）。
    </div>
  </div>

  <div class="card">
    <div style="font-size:15px;font-weight:600;margin-bottom:6px;">下载加速前缀（可选）</div>
    <div class="tip" style="margin:0 0 12px;">服务器访问 GitHub 缓慢或超时时，可填写加速代理前缀（形如 https://gh-proxy.com），留空则直连官方源。</div>
    <form method="post" action="/admin/system/update/proxy" style="display:flex;gap:10px;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input class="input" type="text" name="proxy" value="<?= e($proxy) ?>" placeholder="https://gh-proxy.com" style="max-width:360px;">
      <button class="btn btn-ghost" type="submit">保存</button>
    </form>
  </div>

</div>
<?php $content = ob_get_clean();
$subTitle = '系统更新（GitHub 一键同步）';
include __DIR__ . '/_shell.php'; ?>
