<?php /* NAT Cloud - 管理后台：系统设置（分页卡片式） */ ?>
<?php ob_start(); ?>
<form method="post" action="/admin/settings/save" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="card">
    <div class="tabs" id="setTabs" style="margin-bottom:22px;">
      <button type="button" class="on" data-tab="base">基础设置</button>
      <button type="button" data-tab="mail">邮件发信（SMTP）</button>
      <button type="button" data-tab="pay">码支付</button>
      <button type="button" data-tab="alipay">支付宝官方</button>
      <button type="button" data-tab="wx">微信官方</button>
      <button type="button" data-tab="kyc">实名认证</button>
      <button type="button" data-tab="usdt">USDT 支付</button>
    </div>

    <div class="set-panel on" data-panel="base">
      <div class="field">
        <label>站点名称</label>
        <input class="input" type="text" name="site_name" value="<?= e(setting('site_name', 'NAT Cloud')) ?>" maxlength="40">
      </div>
      <div class="field">
        <label>站点对外地址（支付回调用，如 https://www.example.com）</label>
        <input class="input" type="text" name="site_url" value="<?= e(setting('site_url', '')) ?>" maxlength="190" placeholder="https://www.example.com">
        <div class="tip">码支付 / 支付宝官方 / 微信官方的异步通知地址都由它拼出，请确保公网可访问。</div>
      </div>
      <div class="field">
        <label>每实例 NAT 端口数（节点开通实例时分配）</label>
        <input class="input" type="number" name="ports_per_instance" value="<?= e(setting('ports_per_instance', '15')) ?>" min="3" max="64">
        <div class="tip">节点端口段范围内、每台实例分配的连续端口数量。</div>
      </div>
      <div class="field">
        <label>开放注册</label>
        <select class="input" name="register_enabled">
          <option value="1" <?= setting('register_enabled', '1') === '1' ? 'selected' : '' ?>>开启</option>
          <option value="0" <?= setting('register_enabled', '1') !== '1' ? 'selected' : '' ?>>关闭</option>
        </select>
      </div>
      <div class="field">
        <label>联系方式（展示在工单/充值说明中）</label>
        <input class="input" type="text" name="contact" value="<?= e(setting('contact', '')) ?>" maxlength="100">
      </div>
      <div class="field">
        <label>全站公告横幅（留空则不显示）</label>
        <input class="input" type="text" name="announcement" value="<?= e(setting('announcement', '')) ?>" maxlength="200">
      </div>
      <div class="field">
        <label>注册邮箱验证</label>
        <select class="input" name="reg_verify">
          <option value="1" <?= setting('reg_verify', '1') === '1' ? 'selected' : '' ?>>开启（注册后必须点击验证邮件才能登录）</option>
          <option value="0" <?= setting('reg_verify', '1') !== '1' ? 'selected' : '' ?>>关闭（注册后直接可用，需先配置 SMTP）</option>
        </select>
      </div>
      <div class="field">
        <label>到期余额自动续费</label>
        <select class="input" name="auto_renew_enabled">
          <option value="1" <?= setting('auto_renew_enabled', '1') === '1' ? 'selected' : '' ?>>开启（到期时余额充足自动扣费续期，不足则照常停机）</option>
          <option value="0" <?= setting('auto_renew_enabled', '1') !== '1' ? 'selected' : '' ?>>关闭（到期一律停机，等待用户手动续费）</option>
        </select>
      </div>
      <div class="field">
        <label>服务条款内容（HTML，展示于 /terms，留空则提示未配置）</label>
        <textarea class="input" name="tos_html" rows="5" style="font-family:var(--mono);font-size:12.5px;"><?= e(setting('tos_html', '')) ?></textarea>
      </div>
      <div class="field">
        <label>隐私政策内容（HTML，展示于 /privacy，留空则提示未配置）</label>
        <textarea class="input" name="privacy_html" rows="5" style="font-family:var(--mono);font-size:12.5px;"><?= e(setting('privacy_html', '')) ?></textarea>
      </div>
    </div>

    <div class="set-panel" data-panel="mail">
      <p class="desc" style="font-size:13px;margin-top:0;">用于发送注册验证邮件。请填写邮箱服务商的 SMTP 信息（密码一般为授权码）。例如 QQ 邮箱：ssl / 465，用户名为 QQ 号邮箱；163 邮箱：tls / 587。</p>
      <div class="grid grid-2">
        <div class="field">
          <label>SMTP 服务器</label>
          <input class="input" type="text" name="smtp_host" value="<?= e(setting('smtp_host', '')) ?>" placeholder="smtp.qq.com" maxlength="120">
        </div>
        <div class="field">
          <label>端口</label>
          <input class="input" type="number" name="smtp_port" value="<?= e(setting('smtp_port', '465')) ?>" placeholder="465">
        </div>
        <div class="field">
          <label>加密方式</label>
          <select class="input" name="smtp_encrypt">
            <?php foreach (['ssl' => 'SSL（465 端口）', 'tls' => 'TLS/STARTTLS（587 端口）', 'none' => '无加密（25 端口）'] as $ek => $ev): ?>
              <option value="<?= e($ek) ?>" <?= setting('smtp_encrypt', 'ssl') === $ek ? 'selected' : '' ?>><?= e($ev) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>SMTP 账号（通常为邮箱）</label>
          <input class="input" type="text" name="smtp_user" value="<?= e(setting('smtp_user', '')) ?>" maxlength="120">
        </div>
        <div class="field">
          <label>SMTP 密码 / 授权码（已配置则留空保持不变）</label>
          <input class="input" type="password" name="smtp_pass" maxlength="200" placeholder="<?= setting('smtp_pass', '') !== '' ? '已配置，留空保持不变' : '未配置' ?>">
        </div>
        <div class="field">
          <label>发件人邮箱</label>
          <input class="input" type="text" name="smtp_from" value="<?= e(setting('smtp_from', '')) ?>" maxlength="120">
        </div>
        <div class="field">
          <label>发件人名称</label>
          <input class="input" type="text" name="smtp_fromname" value="<?= e(setting('smtp_fromname', '')) ?>" maxlength="60">
        </div>
      </div>
      <div class="field">
        <label>发送测试邮件到</label>
        <div style="display:flex;gap:10px;align-items:center;">
          <input class="input" style="flex:1;" type="email" name="test_email" placeholder="you@example.com">
          <button class="btn btn-ghost" type="submit" name="do" value="mailtest">保存并发送测试</button>
        </div>
        <div class="tip">会先保存上面的全部设置，再向该邮箱发送一封测试邮件。</div>
      </div>
    </div>

    <div class="set-panel" data-panel="pay">
      <p class="desc" style="font-size:13px;margin-top:0;">第三方聚合支付（易支付协议），收款经网关中转。异步通知地址：<code><?= e(codepay_site_base() !== '' ? codepay_site_base() : 'https://你的域名') ?>/pay/notify</code>。站点对外地址在「基础设置」页签。</p>
      <div class="field">
        <label>码支付网关地址</label>
        <input class="input" type="text" name="codepay_url" value="<?= e(setting('codepay_url', '')) ?>" maxlength="190" placeholder="https://pay.example.com">
      </div>
      <div class="grid grid-2">
        <div class="field">
          <label>商户 ID（pid）</label>
          <input class="input" type="text" name="codepay_id" value="<?= e(setting('codepay_id', '')) ?>" maxlength="32">
        </div>
        <div class="field">
          <label>商户密钥（KEY）</label>
          <input class="input" type="text" name="codepay_key" value="<?= e(setting('codepay_key', '')) ?>" maxlength="64">
        </div>
      </div>
      <div class="field">
        <label>开放支付方式（勾选后前台才显示）</label>
        <div class="pay-channels">
          <label class="pay-channel"><input type="checkbox" name="codepay_alipay" value="1" <?= setting('codepay_alipay', '0') === '1' ? 'checked' : '' ?>><span>支付宝</span></label>
          <label class="pay-channel"><input type="checkbox" name="codepay_wxpay" value="1" <?= setting('codepay_wxpay', '0') === '1' ? 'checked' : '' ?>><span>微信支付</span></label>
          <label class="pay-channel"><input type="checkbox" name="codepay_qqpay" value="1" <?= setting('codepay_qqpay', '0') === '1' ? 'checked' : '' ?>><span>QQ钱包</span></label>
        </div>
      </div>
    </div>

    <div class="set-panel" data-panel="alipay">
      <div class="pay-head">
        <div class="pay-head-txt">
          <h3>支付宝官方（当面付 · 扫码）</h3>
          <p class="desc" style="font-size:12.5px;margin:0;">需要开通「当面付」产品的开放平台应用；签名方式为 RSA2（SHA256withRSA）· 公钥模式。启用后前台出现「支付宝（官方·扫码）」，资金直接进你的支付宝账户，无第三方抽成。</p>
          <p class="desc" style="font-size:12.5px;margin:6px 0 0;">异步通知地址：<code><?= e(codepay_site_base() !== '' ? codepay_site_base() : 'https://你的域名') ?>/pay/alipay/notify</code></p>
        </div>
        <label class="pay-toggle<?= setting('alipay_enable', '0') === '1' ? ' on' : '' ?>">
          <input type="checkbox" name="alipay_enable" value="1" <?= setting('alipay_enable', '0') === '1' ? 'checked' : '' ?>><span>启用</span>
        </label>
      </div>
      <div class="field" style="margin-top:16px;">
        <label>APPID</label>
        <input class="input" type="text" name="alipay_appid" value="<?= e(setting('alipay_appid', '')) ?>" maxlength="32" placeholder="2021xxxxxxxxxx">
      </div>
      <div class="field">
        <label>应用私钥（开放平台「应用私钥」，PKCS8，首尾行可省略）</label>
        <textarea class="input" name="alipay_private_key" rows="3" placeholder="<?= setting('alipay_private_key', '') !== '' ? '已保存（加密存储）——留空保持不变，粘贴新值则覆盖' : 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASC…（或完整 PEM，含 -----BEGIN PRIVATE KEY-----）' ?>"></textarea>
      </div>
      <div class="field">
        <label>支付宝公钥（开放平台「接口加签方式」里的支付宝公钥，不是应用公钥）</label>
        <textarea class="input" name="alipay_public_key" rows="3" placeholder="MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A…（或完整 PEM，含 -----BEGIN PUBLIC KEY-----）"><?= e(setting('alipay_public_key', '')) ?></textarea>
      </div>
    </div>

    <div class="set-panel" data-panel="wx">
      <div class="pay-head">
        <div class="pay-head-txt">
          <h3>微信官方支付（Native · 扫码）</h3>
          <p class="desc" style="font-size:12.5px;margin:0;">需要微信支付商户号并开通「Native 支付」产品；验签采用微信支付公钥模式（商户平台「API 安全」页可下载公钥并查看证书序列号）。启用后前台出现「微信支付（官方·扫码）」，资金直接进你的商户号。</p>
          <p class="desc" style="font-size:12.5px;margin:6px 0 0;">异步通知地址：<code><?= e(codepay_site_base() !== '' ? codepay_site_base() : 'https://你的域名') ?>/pay/wx/notify</code></p>
        </div>
        <label class="pay-toggle<?= setting('wx_enable', '0') === '1' ? ' on' : '' ?>">
          <input type="checkbox" name="wx_enable" value="1" <?= setting('wx_enable', '0') === '1' ? 'checked' : '' ?>><span>启用</span>
        </label>
      </div>
      <div class="grid grid-2" style="margin-top:16px;">
        <div class="field">
          <label>商户号（mchid）</label>
          <input class="input" type="text" name="wx_mchid" value="<?= e(setting('wx_mchid', '')) ?>" maxlength="16" placeholder="1900xxxxxx">
        </div>
        <div class="field">
          <label>APPID（已与商户号绑定的公众号/开放平台应用）</label>
          <input class="input" type="text" name="wx_appid" value="<?= e(setting('wx_appid', '')) ?>" maxlength="32" placeholder="wxXXXXXXXX">
        </div>
        <div class="field">
          <label>商户 API 证书序列号</label>
          <input class="input" type="text" name="wx_serial_no" value="<?= e(setting('wx_serial_no', '')) ?>" maxlength="64" placeholder="5157F09EFDC096DE15EBE81A47057A72…">
        </div>
        <div class="field">
          <label>APIv3 密钥（32 位）</label>
          <input class="input" type="password" name="wx_api_v3_key" value="" maxlength="64" placeholder="<?= setting('wx_api_v3_key', '') !== '' ? '已保存——留空保持不变' : '32 位 APIv3 密钥' ?>">
        </div>
      </div>
      <div class="field">
        <label>商户 API 证书私钥（apiclient_key.pem 全文）</label>
        <textarea class="input" name="wx_private_key" rows="3" placeholder="<?= setting('wx_private_key', '') !== '' ? '已保存（加密存储）——留空保持不变，粘贴新值则覆盖' : '-----BEGIN PRIVATE KEY-----&#10;MIIEvQIBADANBgkqhkiG9w0BAQEFAASC…' ?>"></textarea>
      </div>
      <div class="field">
        <label>微信支付公钥（pub_key.pem 全文，用于回调验签）</label>
        <textarea class="input" name="wx_public_key" rows="3" placeholder="-----BEGIN PUBLIC KEY-----&#10;MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A…"><?= e(setting('wx_public_key', '')) ?></textarea>
      </div>
    </div>

    <div class="set-panel" data-panel="kyc">
      <p class="desc" style="font-size:13px;margin-top:0;">全局开关控制所有「跟随全局设置」的套餐是否需要实名；套餐可在编辑弹窗里单独覆盖（需要 / 不需要 / 跟随全局）。实名流程：用户提交姓名与身份证号 → 跳转所选接口完成人脸认证 → 自动通过并记录。全部默认为<b>不需要实名</b>。</p>

      <div class="pay-head" style="margin-top:14px;">
        <div class="pay-head-txt">
          <h3>全局实名要求</h3>
          <p class="desc" style="font-size:12.5px;margin:0;">开启后，未单独设置的套餐购买时需要实名认证。</p>
        </div>
        <label class="pay-toggle<?= setting('kyc_global_required', '0') === '1' ? ' on' : '' ?>">
          <input type="checkbox" name="kyc_global_required" value="1" <?= setting('kyc_global_required', '0') === '1' ? 'checked' : '' ?>><span>开启全局实名</span>
        </label>
      </div>

      <div class="field" style="margin-top:16px;">
        <label>实名接口（选择后前台按对应流程发起人脸认证）</label>
        <select class="input" name="kyc_provider" id="kycProviderSel">
          <?php $kp = setting('kyc_provider', ''); ?>
          <option value="" <?= $kp === '' ? 'selected' : '' ?>>未配置（实名功能不可用）</option>
          <option value="jidian" <?= $kp === 'jidian' ? 'selected' : '' ?>>极点实名（支付宝人脸核身分发）</option>
          <option value="alipay" <?= $kp === 'alipay' ? 'selected' : '' ?>>支付宝官方（人脸核身）</option>
          <option value="wx" <?= $kp === 'wx' ? 'selected' : '' ?>>微信官方（人脸核身）</option>
          <option value="zhijian" <?= $kp === 'zhijian' ? 'selected' : '' ?>>智简魔方</option>
        </select>
      </div>

      <div class="kyc-card" data-for="jidian">
        <div class="kyc-card-head">
          <h3>极点实名</h3>
          <span class="tag run">已对接 · 推荐</span>
          <span class="kyc-use-tag" data-use-when="jidian">当前使用</span>
        </div>
        <p class="desc" style="font-size:12.5px;margin:4px 0 10px;">接口：sm.jdidc.cn（支付宝身份验证分发）。用户提交姓名与身份证号后跳转支付宝完成人脸认证，站点自动轮询结果。</p>
        <div class="grid grid-2">
          <div class="field"><label>应用 ID（appId）</label><input class="input" type="text" name="jidian_app_id" value="<?= e(setting('jidian_app_id', '')) ?>" maxlength="64" placeholder="App_xxxxxxxxxxxx"></div>
          <div class="field"><label>应用 Key（appKey）</label><input class="input" type="text" name="jidian_app_key" value="<?= e(setting('jidian_app_key', '')) ?>" maxlength="64" placeholder="应用密钥"></div>
        </div>
        <div class="field"><label>出站中继节点 IP（可选，主服务器无法直连极点时填写；留空=直连）</label><input class="input" type="text" name="kyc_relay_ip" value="<?= e(setting('kyc_relay_ip', '')) ?>" maxlength="45" placeholder="如 203.0.113.10"></div>
      </div>

      <div class="kyc-card" data-for="alipay">
        <div class="kyc-card-head">
          <h3>支付宝官方（人脸核身）</h3>
          <span class="kyc-use-tag" data-use-when="alipay">当前使用</span>
        </div>
        <p class="desc" style="font-size:12.5px;margin:4px 0 10px;">需要已开通「人脸核身」能力的支付宝开放平台应用（凭据与支付共用时可复用支付页签的 APPID/密钥）。配置后可在此选择为实名接口。</p>
        <div class="grid grid-2">
          <div class="field"><label>APPID</label><input class="input" type="text" name="kyc_alipay_app_id" value="<?= e(setting('kyc_alipay_app_id', '')) ?>" maxlength="32" placeholder="与支付宝开放平台应用一致"></div>
          <div class="field"><label>应用私钥（PKCS8）</label><textarea class="input" name="kyc_alipay_private_key" rows="3" placeholder="<?= setting('kyc_alipay_private_key', '') !== '' ? '已保存（加密存储）——留空保持不变' : 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASC…' ?>"></textarea></div>
        </div>
      </div>

      <div class="kyc-card" data-for="wx">
        <div class="kyc-card-head">
          <h3>微信官方（人脸核身）</h3>
          <span class="kyc-use-tag" data-use-when="wx">当前使用</span>
        </div>
        <p class="desc" style="font-size:12.5px;margin:4px 0 10px;">需要微信「人脸核身」接口权限（该能力有行业准入要求）。</p>
        <div class="grid grid-2">
          <div class="field"><label>APPID</label><input class="input" type="text" name="kyc_wx_app_id" value="<?= e(setting('kyc_wx_app_id', '')) ?>" maxlength="32" placeholder="wxXXXXXXXX"></div>
          <div class="field"><label>APIv3 密钥</label><input class="input" type="password" name="kyc_wx_api_v3_key" value="" maxlength="64" placeholder="<?= setting('kyc_wx_api_v3_key', '') !== '' ? '已保存——留空保持不变' : '32 位 APIv3 密钥' ?>"></div>
        </div>
      </div>

      <div class="kyc-card" data-for="zhijian">
        <div class="kyc-card-head">
          <h3>智简魔方</h3>
          <span class="kyc-use-tag" data-use-when="zhijian">当前使用</span>
        </div>
        <p class="desc" style="font-size:12.5px;margin:4px 0 10px;">第三方实名接口（预留配置；获取凭据后填写即可切换使用）。</p>
        <div class="grid grid-2">
          <div class="field"><label>接口地址</label><input class="input" type="text" name="kyc_zhijian_url" value="<?= e(setting('kyc_zhijian_url', '')) ?>" maxlength="190" placeholder="https://…（接口根地址）"></div>
          <div class="field"><label>应用 ID</label><input class="input" type="text" name="kyc_zhijian_app_id" value="<?= e(setting('kyc_zhijian_app_id', '')) ?>" maxlength="64"></div>
        </div>
        <div class="field"><label>应用密钥</label><input class="input" type="text" name="kyc_zhijian_app_key" value="<?= e(setting('kyc_zhijian_app_key', '')) ?>" maxlength="64"></div>
      </div>

      <script>
      (function () {
        var sel = document.getElementById("kycProviderSel");
        if (!sel) return;
        var sync = function () {
          document.querySelectorAll(".kyc-card").forEach(function (c) {
            c.classList.toggle("on", c.getAttribute("data-for") === sel.value);
          });
        };
        sel.addEventListener("change", sync);
        sync();
      })();
      </script>
    </div>

    <div class="set-panel" data-panel="usdt">
      <p class="desc" style="font-size:13px;margin-top:0;">站内收款：用户充值页展示 USDT·TRC20 二维码，系统自动监听公链到账并入账，无需挂机轮询第三方。</p>
      <div class="field">
        <label>启用 USDT 支付（勾选后前台才显示）</label>
        <div class="pay-channels">
          <label class="pay-channel"><input type="checkbox" name="usdt_enable" value="1" <?= setting('usdt_enable', '0') === '1' ? 'checked' : '' ?>><span>USDT · TRC20</span></label>
        </div>
      </div>
      <div class="field">
        <label>TRC20 收款地址</label>
        <input class="input" type="text" name="usdt_address" value="<?= e(setting('usdt_address', '')) ?>" maxlength="120" placeholder="T 开头的 TRC20 地址">
      </div>
      <div class="field">
        <label>USDT 汇率（1 USDT = 多少元，如 7.30）</label>
        <input class="input" type="number" step="0.01" min="0.01" name="usdt_rate" value="<?= e(setting('usdt_rate', '7.30')) ?>">
      </div>
    </div>

    <div class="set-savebar">
      <button class="btn btn-primary btn-lg" type="submit">保存全部设置</button>
      <span class="tip" style="margin:0;">所有页签一次保存。</span>
    </div>
  </div>
</form>
<script>
(function () {
  var bar = document.getElementById('setTabs');
  if (!bar) return;
  var panels = document.querySelectorAll('.set-panel');
  function act(key) {
    bar.querySelectorAll('button[data-tab]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-tab') === key);
    });
    panels.forEach(function (p) {
      p.classList.toggle('on', p.getAttribute('data-panel') === key);
    });
    try { localStorage.setItem('nc_set_tab', key); } catch (e) {}
  }
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-tab]');
    if (b) act(b.getAttribute('data-tab'));
  });
  var saved = null;
  try { saved = localStorage.getItem('nc_set_tab'); } catch (e) {}
  if (saved && bar.querySelector('button[data-tab="' + saved + '"]')) act(saved);
})();
</script>
<?php $content = ob_get_clean();
$subTitle = '站点基础参数配置';
include __DIR__ . '/_shell.php'; ?>
