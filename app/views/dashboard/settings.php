<?php /* NAT Cloud - 设置（对齐演示站布局） */
$contentWrapClass = 'settings-col';
?><?php ob_start(); ?>
<?php $u = auth_user(); ?>
<div class="grid-settings">
  <div class="panel-card">
    <div class="panel-head" style="margin-bottom:16px;"><b>账号信息</b></div>
    <div class="kv" style="margin-top:4px;">
      <span class="k">用户名</span>
      <span>
        <?= e($u['username']) ?>
        <button class="pencil-btn" type="button" data-modal-open="modalUsername" title="修改用户名"><?= nc_icon('pencil', 15) ?></button>
      </span>
      <span class="k">邮箱</span><span><?= e($u['email']) ?></span>
    </div>

    <div style="margin-top:26px;">
      <div style="font-size:14.5px;font-weight:600;margin-bottom:10px;"><?= e(lang() === 'en' ? 'Language' : '界面语言') ?></div>
      <div class="seg">
        <button type="button" data-setlang="zh" class="<?= lang() === 'zh' ? 'on' : '' ?>">简体中文</button>
        <button type="button" data-setlang="en" class="<?= lang() === 'en' ? 'on' : '' ?>">English</button>
      </div>
    </div>

    <div style="margin-top:22px;">
      <div style="font-size:14.5px;font-weight:600;margin-bottom:10px;">外观</div>
      <div class="seg">
        <button type="button" data-theme-set="auto">跟随系统</button>
        <button type="button" data-theme-set="light">浅色</button>
        <button type="button" data-theme-set="dark">深色</button>
      </div>
    </div>

    <hr style="margin:24px 0 18px;">
    <div style="font-size:14.5px;font-weight:600;margin-bottom:12px;">Telegram 通知</div>
    <?php $tgReady = function_exists('tg_ready') && tg_ready(); ?>
    <?php $tgBound = isset($u['tg_chat_id']) && $u['tg_chat_id'] !== null && $u['tg_chat_id'] !== ''; ?>
    <?php if (!$tgReady && !$tgBound): ?>
      <div style="font-size:13.5px;color:var(--text-dim);">管理员尚未配置 Telegram 机器人。</div>
    <?php elseif ($tgBound): ?>
      <div style="font-size:13.5px;color:var(--text-dim);">✅ 已绑定。实例开通 / 续费 / 到期提醒 / 工单回复将推送到你的 Telegram。</div>
      <form method="post" action="/dashboard/settings/tg-unbind" style="margin-top:10px;">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">解除绑定</button>
      </form>
    <?php else: ?>
      <?php $tgCode = tg_bind_code((int) $u['id']); ?>
      <div style="font-size:13.5px;color:var(--text-dim);line-height:1.9;">未绑定。绑定后可接收实例开通 / 续费 / 到期提醒 / 工单回复通知：<br>
        1. 在 Telegram 打开站点机器人；<br>
        2. 发送：<b>/start <?= e($tgCode) ?></b></div>
      <form method="post" action="/dashboard/settings/tg-bind" style="margin-top:10px;">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">重新生成绑定码</button>
      </form>
    <?php endif; ?>

    <hr style="margin:24px 0 18px;">
    <div style="font-size:14.5px;font-weight:600;margin-bottom:12px;">开放 API</div>
    <?php $apiToken = trim((string) ($apiToken ?? '')); ?>
    <?php if ($apiToken !== ''): ?>
      <div style="font-size:13.5px;color:var(--text-dim);line-height:1.9;">Bearer Token（等同于账户操作凭据，请妥善保管）：<br>
        <input class="input" readonly value="<?= e($apiToken) ?>" style="font-family:var(--mono);font-size:12px;margin-top:6px;" onclick="this.select()"></div>
      <div style="font-size:13px;color:var(--text-dim);margin-top:8px;line-height:1.9;">
        接口基地址：<code><?= e(codepay_site_base()) ?>/open/v1</code>，请求头携带 <code>Authorization: Bearer &lt;token&gt;</code>：<br>
        · GET /me · GET /instances · GET /instances/{name} · POST /instances/{name}/power {"action":"start|stop|reboot"}
      </div>
      <form method="post" action="/dashboard/settings/api-token" style="margin-top:10px;" data-confirm="确认重置 Token？旧 Token 将立即失效。">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">重置 Token</button>
      </form>
    <?php else: ?>
      <div style="font-size:13.5px;color:var(--text-dim);">生成 Token 后可通过 HTTP 接口管理你的实例（查询 / 开关机 / 续费）。</div>
      <form method="post" action="/dashboard/settings/api-token" style="margin-top:10px;">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">生成 API Token</button>
      </form>
    <?php endif; ?>

    <hr style="margin:24px 0 18px;">
    <div style="font-size:14.5px;font-weight:600;margin-bottom:12px;">修改密码</div>
    <form method="post" action="/dashboard/settings/password">
      <?= csrf_field() ?>
      <div class="field"><label>当前密码</label><input class="input" type="password" name="old_password" autocomplete="current-password" required></div>
      <div class="field"><label>新密码</label><input class="input" type="password" name="new_password" autocomplete="new-password" required></div>
      <div class="field"><label>确认新密码</label><input class="input" type="password" name="new_password2" autocomplete="new-password" required></div>
      <button class="btn btn-light btn-sm" type="submit">更新密码</button>
    </form>
  </div>

  <div class="panel-card">
    <div class="panel-head" style="margin-bottom:12px;"><b>登录会话</b>
      <form method="post" action="/dashboard/settings/logout_all" data-confirm="确认退出所有设备？">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit"><?= nc_icon('logout', 14) ?> 退出所有设备</button>
      </form>
    </div>
    <p class="desc" style="font-size:13px;">这些设备目前已登录你的账号。</p>
    <?php foreach ($sessions as $s): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--border-soft);">
        <span class="eb-ico" style="width:38px;height:38px;border-radius:10px;flex:none;"><?= nc_icon('user', 16) ?></span>
        <div style="flex:1;min-width:0;">
          <div style="font-size:14px;"><?= e($s['ua']) ?><?= $s['is_current'] ? ' <span class="tag run">当前设备</span>' : '' ?></div>
          <div style="color:var(--text-faint);font-size:12.5px;" class="mono">IP <?= e($s['ip']) ?> · 登录 <?= e(dt($s['created_at'])) ?> · 过期 <?= e(dt($s['expire_at'])) ?></div>
        </div>
        <?php if (!$s['is_current']): ?>
        <form class="inline-form" method="post" action="/dashboard/settings/sessions/<?= (int) $s['id'] ?>/kill">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm" type="submit">下线</button>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel-card" style="margin-top:20px;">
  <div class="panel-head" style="margin-bottom:12px;"><b>SSH 公钥</b>
    <button class="btn btn-ghost btn-sm" type="button" data-modal-open="modalSsh">＋ 添加公钥</button>
  </div>
  <p class="desc" style="font-size:13px;">保存常用的公钥，创建实例、重装系统时可直接使用，最多 20 把；删掉这里的记录不影响已经装进实例的公钥。</p>
  <?php if (empty($keys)): ?>
    <div class="empty" style="padding:22px;">还没有保存公钥。</div>
  <?php else: ?>
    <?php foreach ($keys as $k): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px dashed var(--border-soft);">
        <div style="flex:1;min-width:0;">
          <div style="font-size:14px;"><?= e($k['name']) ?></div>
          <div class="mono" style="color:var(--text-faint);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(mb_substr($k['public_key'], 0, 60)) ?>…</div>
        </div>
        <form class="inline-form" method="post" action="/dashboard/settings/sshkeys/<?= (int) $k['id'] ?>/del" data-confirm="删除该公钥？">
          <?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit">删除</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="modal-mask" id="modalUsername">
  <div class="modal">
    <h3>修改用户名</h3>
    <form method="post" action="/dashboard/settings/username">
      <?= csrf_field() ?>
      <div class="field">
        <label>新用户名</label>
        <input class="input" type="text" name="username" value="<?= e($u['username']) ?>" maxlength="32" required>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-light" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-mask" id="modalSsh">
  <div class="modal">
    <h3>添加 SSH 公钥</h3>
    <form method="post" action="/dashboard/settings/sshkeys/add">
      <?= csrf_field() ?>
      <div class="field">
        <label>名称</label>
        <input class="input" type="text" name="key_name" placeholder="例如：我的笔记本" maxlength="40" required>
      </div>
      <div class="field">
        <label>公钥内容</label>
        <textarea class="input" name="public_key" rows="4" placeholder="ssh-ed25519 AAAA..." required></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-light" type="submit">保存公钥</button>
      </div>
    </form>
  </div>
</div>
<?php $content = ob_get_clean();
$subTitle = '账号与安全。';
include __DIR__ . '/_shell.php'; ?>
