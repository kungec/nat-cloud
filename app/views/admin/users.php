<?php /* NAT Cloud - 管理后台：用户列表 */ ?>
<?php ob_start(); ?>
<form class="searchbar" method="get" action="/admin/users">
  <input class="input" type="text" name="q" value="<?= e($search) ?>" placeholder="搜索用户名 / 邮箱 / ID">
  <button class="btn btn-ghost" type="submit">搜索</button>
</form>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">没有找到用户</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID</th><th>用户名</th><th>邮箱</th><th>余额</th><th>实名</th><th>状态</th><th>注册</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td class="mono"><?= (int) $u['id'] ?></td>
          <td><a href="/admin/users/<?= (int) $u['id'] ?>"><b><?= e($u['username']) ?></b></a></td>
          <td class="mono"><?= e($u['email']) ?></td>
          <td>¥<?= e(money($u['balance'])) ?></td>
          <td><?php
              $kmap = [0 => '未认证', 1 => '待审核', 2 => '已通过', 3 => '已驳回'];
              echo e($kmap[(int) $u['kyc_status']] ?? '-');
          ?></td>
          <td><span class="tag <?= (int) $u['status'] === 1 ? 'run' : 'closed' ?>"><?= (int) $u['status'] === 1 ? '正常' : '禁用' ?></span></td>
          <td class="mono"><?= e(dt($u['created_at'])) ?></td>
          <td>
            <div class="actions">
              <a class="btn btn-ghost btn-sm" href="/admin/users/<?= (int) $u['id'] ?>">详情</a>
              <button class="btn btn-ghost btn-sm" type="button" data-modal-open="userEdit<?= (int) $u['id'] ?>">编辑</button>
              <form class="inline-form" method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $u['status'] === 1 ? '禁用' : '启用' ?></button>
              </form>
              <form class="inline-form" method="post" action="/admin/users/<?= (int) $u['id'] ?>/resetpw" data-confirm="确认重置该用户密码？"><?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit">重置密码</button>
              </form>
              <form class="inline-form" method="post" action="/admin/users/<?= (int) $u['id'] ?>/delete" data-confirm="确认删除用户 <?= e($u['username']) ?>？其全部数据（实例/工单/实名/流水）将一并删除且无法恢复！"><?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">删除</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php foreach ($rows as $u): ?>
<div class="modal-mask" id="userEdit<?= (int) $u['id'] ?>">
  <div class="modal">
    <h3>编辑用户：<?= e($u['username']) ?></h3>
    <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/edit">
      <?= csrf_field() ?>
      <div class="field">
        <label>用户名</label>
        <input class="input" type="text" name="username" value="<?= e($u['username']) ?>" maxlength="32" required>
      </div>
      <div class="field">
        <label>邮箱</label>
        <input class="input" type="email" name="email" value="<?= e($u['email']) ?>" maxlength="120" required>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php echo admin_render_pager((int) $page, (int) $pages, (int) $total, $search !== '' ? ['q' => $search] : []); ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
