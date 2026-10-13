<?php /* NAT Cloud - 优惠码管理 */ ?>
<?php ob_start(); ?>
<div style="margin-bottom:16px;">
  <button class="btn btn-primary" type="button" data-modal-open="modalCouponCreate">+ 创建优惠码</button>
</div>
<div class="table-wrap">
  <?php if (empty($rows)): ?>
    <div class="empty">暂无优惠码</div>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>优惠码</th><th>类型</th><th>面值</th><th>门槛</th><th>已用 / 配额</th><th>有效期至</th><th>状态</th><th style="text-align:right;">操作</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td class="mono"><?= e($c['code']) ?></td>
          <td><?= $c['type'] === 'percent' ? '折扣' : '立减' ?></td>
          <td><?= $c['type'] === 'percent' ? (float) $c['value'] . '%' : '¥' . money((float) $c['value']) ?></td>
          <td><?= (float) $c['min_amount'] > 0 ? '满 ¥' . money((float) $c['min_amount']) : '无' ?></td>
          <td class="mono"><?= (int) $c['used'] ?> / <?= (int) $c['quota'] ?></td>
          <td class="mono" style="font-size:12.5px;"><?= $c['expires_at'] ? e(dt($c['expires_at'])) : '长期' ?></td>
          <td><span class="tag <?= (int) $c['status'] === 1 ? 'run' : 'closed' ?>"><?= (int) $c['status'] === 1 ? '启用' : '停用' ?></span></td>
          <td>
            <div class="actions">
              <form class="inline-form" method="post" action="/admin/coupons/<?= (int) $c['id'] ?>/toggle"><?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $c['status'] === 1 ? '停用' : '启用' ?></button>
              </form>
              <form class="inline-form" method="post" action="/admin/coupons/<?= (int) $c['id'] ?>/delete" data-confirm="确认删除该优惠码？核销记录将一并删除。"><?= csrf_field() ?>
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

<div class="modal-mask" id="modalCouponCreate">
  <div class="modal">
    <h3>创建优惠码</h3>
    <form method="post" action="/admin/coupons/create">
      <?= csrf_field() ?>
      <div class="grid grid-2">
        <div class="field"><label>优惠码（大写字母 / 数字，3-32 位）</label><input class="input" type="text" name="code" placeholder="如 WELCOME10" maxlength="32" required></div>
        <div class="field"><label>类型</label>
          <select class="input" name="type">
            <option value="percent">折扣（按百分比）</option>
            <option value="fixed">立减（固定金额）</option>
          </select>
        </div>
        <div class="field"><label>面值（百分比填 1-99；立减填金额）</label><input class="input" type="number" step="0.01" name="value" min="0.01" required></div>
        <div class="field"><label>使用门槛金额（0 = 无门槛）</label><input class="input" type="number" step="0.01" name="min_amount" value="0" min="0"></div>
        <div class="field"><label>总可用次数</label><input class="input" type="number" name="quota" value="1" min="1" required></div>
        <div class="field"><label>有效期至（可选）</label><input class="input" type="datetime-local" name="expires_at"></div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" data-modal-close>取消</button>
        <button class="btn btn-primary" type="submit">创建</button>
      </div>
    </form>
  </div>
</div>
<?php $content = ob_get_clean();
include __DIR__ . '/_shell.php'; ?>
