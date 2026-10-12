<?php /* NAT Cloud - 管理后台：管理员账号设置（分页卡片布局） */ ?>
<?php ob_start(); ?>
<form method="post" action="/admin/profile" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="card">
    <div class="tabs" id="profTabs" style="margin-bottom:22px;">
      <button type="button" class="on" data-tab="account">账号信息</button>
      <button type="button" data-tab="password">修改密码</button>
    </div>

    <div class="set-panel on" data-panel="account">
      <div class="field">
        <label>管理员用户名（用于登录管理后台）</label>
        <input class="input" type="text" name="username" value="<?= e($adminName) ?>" minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+" required>
        <div class="tip">3-32 位字母 / 数字 / 下划线，修改后下次登录使用新用户名。</div>
      </div>
    </div>

    <div class="set-panel" data-panel="password">
      <div class="field">
        <label>当前密码（修改密码时必填）</label>
        <input class="input" type="password" name="old_password" autocomplete="current-password">
      </div>
      <div class="field">
        <label>新密码（至少 8 位，留空则不修改密码）</label>
        <input class="input" type="password" name="new_password" minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label>确认新密码</label>
        <input class="input" type="password" name="new_password2" minlength="8" autocomplete="new-password">
      </div>
      <div class="tip">修改成功后所有管理员会话将下线，需用新密码重新登录。</div>
    </div>

    <div class="set-savebar">
      <button class="btn btn-primary btn-lg" type="submit">保存全部设置</button>
      <span class="tip" style="margin:0;">用户名与密码可同时修改，一次保存。</span>
    </div>
  </div>
</form>
<script>
(function () {
  var bar = document.getElementById('profTabs');
  if (!bar) return;
  var panels = document.querySelectorAll('.set-panel');
  function act(key) {
    bar.querySelectorAll('button[data-tab]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-tab') === key);
    });
    panels.forEach(function (p) {
      p.classList.toggle('on', p.getAttribute('data-panel') === key);
    });
    try { localStorage.setItem('nc_prof_tab', key); } catch (e) {}
  }
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-tab]');
    if (b) act(b.getAttribute('data-tab'));
  });
  var saved = null;
  try { saved = localStorage.getItem('nc_prof_tab'); } catch (e) {}
  if (saved && bar.querySelector('button[data-tab="' + saved + '"]')) act(saved);
})();
</script>
<?php $content = ob_get_clean();
$subTitle = '管理员账号与密码';
include __DIR__ . '/_shell.php'; ?>
