<?php /* NAT Cloud - 管理后台：实名审核 */ ?>
<?php ob_start(); ?>
<div class="table-wrap">
  <?php
  $rowsOut = $rows;
  ?>
  <?php if (empty($rowsOut)): ?>
    <div class="empty">暂无实名申请</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID</th><th>用户</th><th>姓名</th><th>证件号</th><th>手机号</th><th>状态</th><th>接口</th><th>提交时间</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rowsOut as $k): ?>
        <?php
        $realName = secret_decrypt((string) $k['real_name']);
        $idCard = secret_decrypt((string) $k['id_card']);
        $phone = secret_decrypt((string) $k['phone']);
        $smap = ['pending' => '待完成人脸', 'passed' => '已通过', 'approved' => '已通过', 'rejected' => '已驳回', 'failed' => '未通过'];
        $pmap = ['jidian' => '极点', 'alipay' => '支付宝', 'wx' => '微信', 'zhijian' => '智简魔方', '' => '人工'];
        ?>
        <tr>
          <td class="mono"><?= (int) $k['id'] ?></td>
          <td><a href="/admin/users/<?= (int) $k['uid'] ?>"><?= e($k['username'] ?? $k['uid']) ?></a></td>
          <td><?= e($realName) ?></td>
          <td class="mono"><?= e($idCard) ?></td>
          <td class="mono"><?= e($phone) ?></td>
          <td><span class="tag <?= $k['status'] === 'passed' || $k['status'] === 'approved' ? 'run' : e($k['status']) ?>"><?=
              e($smap[$k['status']] ?? $k['status']);
          ?></span></td>
          <td class="mono"><?= e($pmap[(string) ($k['provider'] ?? '')] ?? '人工') ?></td>
          <td class="mono"><?= e(dt($k['created_at'])) ?></td>
          <td>
            <?php if ($k['status'] === 'pending'): ?>
            <div class="actions">
              <button class="btn btn-primary btn-sm" type="button" data-modal-open="kycEdit<?= (int) $k['id'] ?>">编辑</button>
              <form class="inline-form" method="post" action="/admin/kyc/<?= (int) $k['id'] ?>/approve"><?= csrf_field() ?>
                <button class="btn btn-primary btn-sm" type="submit">通过</button>
              </form>
              <form class="inline-form" method="post" action="/admin/kyc/<?= (int) $k['id'] ?>/reject"><?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">驳回</button>
              </form>
              <form class="inline-form" method="post" action="/admin/kyc/<?= (int) $k['id'] ?>/delete" data-confirm="删除该实名记录？用户实名状态将重置为未认证。"><?= csrf_field() ?>
                <button class="btn btn-danger btn-sm" type="submit">删除</button>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<p class="desc" style="margin-top:12px;font-size:12.5px;color:var(--text-faint);">数据以加密形式存储，管理后台按合规要求展示完整实名信息；请妥善保管后台账号。</p>

<?php foreach ($rows as $k): ?>
<?php if ($k['status'] !== 'pending') continue; ?>
<div class="modal-mask" id="kycEdit<?= (int) $k['id'] ?>">
  <div class="modal">
    <h3>编辑实名信息（<?= e($k['username'] ?? $k['uid']) ?>）</h3>
    <form method="post" action="/admin/kyc/<?= (int) $k['id'] ?>/edit">
      <?= csrf_field() ?>
      <div class="field"><label>姓名</label><input class="input" type="text" name="real_name" maxlength="30" required></div>
      <div class="field"><label>身份证号</label><input class="input" type="text" name="id_card" maxlength="18" required></div>
      <div class="field"><label>手机号</label><input class="input" type="text" name="phone" maxlength="11" required></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
