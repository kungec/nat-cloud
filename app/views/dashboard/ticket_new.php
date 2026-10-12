<?php /* NAT Cloud - 提交工单（对齐演示站：Markdown 编辑器 + 图片上传） */
$backUrl = '/dashboard/tickets'; $backText = '全部工单';
$insts = db_all('SELECT id, name FROM instances WHERE uid = ? AND status != ? ORDER BY id DESC', [(int) auth_user()['id'], 'released']);
?><?php ob_start(); ?>
<div class="card" style="max-width:900px;">
  <form method="post" action="/dashboard/tickets/new" id="ticketForm">
    <?= csrf_field() ?>
    <div class="field">
      <label>标题<i class="req">*</i></label>
      <input class="input" type="text" name="title" value="<?= e($old['title'] ?? '') ?>" maxlength="100" placeholder="一句话概括问题" required>
    </div>
    <div class="grid grid-3" style="margin-bottom:16px;">
      <div class="field" style="margin:0;">
        <label>分类</label>
        <select class="input" name="category">
          <option value="instance">技术故障</option>
          <option value="network">网络问题</option>
          <option value="billing">账务问题</option>
          <option value="other">其他</option>
        </select>
      </div>
      <div class="field" style="margin:0;">
        <label>优先级</label>
        <select class="input" name="priority">
          <option value="low">低</option>
          <option value="normal" selected>普通</option>
          <option value="high">高</option>
          <option value="urgent">紧急</option>
        </select>
      </div>
      <div class="field" style="margin:0;">
        <label>关联实例（可选）</label>
        <select class="input" name="instance_id">
          <option value="0">不关联</option>
          <?php foreach ($insts as $i): ?>
            <option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label>内容<i class="req">*</i></label>
      <div class="md-editor">
        <div class="md-tabs">
          <button type="button" class="on" data-mdtab="edit">编辑</button>
          <button type="button" data-mdtab="preview">预览</button>
          <span class="md-note">支持 Markdown</span>
        </div>
        <textarea class="input md-ta" name="content" id="mdSource" rows="9" placeholder="请描述问题、出现时间、已尝试的操作。报错和日志可以用 ``` 包成代码块…"><?= e($old['content'] ?? '') ?></textarea>
        <div class="md-preview" id="mdPreview" style="display:none;"></div>
      </div>
    </div>
    <div class="field">
      <label>添加图片（可选）</label>
      <div class="uploader" id="uploader">
        <input type="file" id="imgInput" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden>
        <div class="up-box" id="upBox"><?= nc_icon('tray', 18) ?>添加图片</div>
        <div class="up-thumbs" id="upThumbs"></div>
      </div>
      <input type="hidden" name="images" id="imagesInput" value="[]">
      <div class="tip" style="margin-top:8px;">最多 5 张，每张不超过 5 MB，也可以直接粘贴或拖进来</div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:18px;">
      <a class="btn btn-ghost" href="/dashboard/tickets">取消</a>
      <button class="btn btn-light" type="submit">提交</button>
    </div>
  </form>
</div>
<?php $content = ob_get_clean();
$subTitle = '描述得越具体，处理得越快。';
include __DIR__ . '/_shell.php'; ?>
