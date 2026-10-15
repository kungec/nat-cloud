<?php
declare(strict_types=1);

/* NAT Cloud - 码支付（易支付协议）对接辅助
   协议：POST {网关}/submit.php 表单提交；MD5 签名（参数 ASCII 升序拼 k=v& 后接商户密钥，小写）；
   异步通知 GET/POST 回传 trade_status=TRADE_SUCCESS，需回 success。 */

function codepay_config(): array
{
    return [
        'url' => rtrim(trim(setting('codepay_url', '')), '/'),
        'pid' => trim(setting('codepay_id', '')),
        'key' => trim(setting('codepay_key', '')),
        'alipay' => setting('codepay_alipay', '0') === '1',
        'wxpay' => setting('codepay_wxpay', '0') === '1',
        'qqpay' => setting('codepay_qqpay', '0') === '1',
    ];
}

function codepay_enabled(): bool
{
    $c = codepay_config();
    return $c['url'] !== '' && $c['pid'] !== '' && $c['key'] !== '';
}

/** 管理后台勾选后才出现的可用支付渠道：key => 显示名 */
function codepay_channels(): array
{
    $c = codepay_config();
    $all = [
        'alipay' => '支付宝',
        'wxpay' => '微信支付',
        'qqpay' => 'QQ钱包',
    ];
    $out = [];
    foreach ($all as $key => $name) {
        if ($c[$key]) {
            $out[$key] = $name;
        }
    }
    return $out;
}

/** 主机白名单校验：拒绝 localhost、环回、私有与保留地址（含 IPv4/IPv6） */
function codepay_host_allowed(string $host): bool
{
    $host = strtolower($host);
    if ($host === '' || $host === 'localhost' || substr($host, -10) === '.localhost') {
        return false;
    }
    $ip = filter_var($host, FILTER_VALIDATE_IP);
    if ($ip !== false) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    return (bool) preg_match('~^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$~', $host);
}

/** 网关接口地址：scheme 仅 http/https，主机须为公网域名/IP，否则返回空串 */
function codepay_endpoint(string $path): string
{
    $c = codepay_config();
    if ($c['url'] === '') {
        return '';
    }
    $parts = parse_url($c['url']);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (($scheme !== 'http' && $scheme !== 'https') || strpos($c['url'], '@') !== false) {
        return '';
    }
    if (!codepay_host_allowed($host)) {
        return '';
    }
    return $c['url'] . $path;
}

/** 站点对外基础地址：优先取后台配置 site_url；未配置时按当前请求推断，
 *  但 Host 必须通过公网域名/IP 校验（防 Host 头污染把带令牌的邮件链接指向攻击者域名） */
function codepay_site_base(): string
{
    $configured = trim(setting('site_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || !codepay_host_allowed($host)) {
        return '';
    }
    return ($https ? 'https://' : 'http://') . $host;
}

/** 易支付 MD5 签名：过滤空值与 sign/sign_type → ASCII 升序 → k=v& 拼接 → 追加密钥 → md5 小写 */
function codepay_sign(array $params, string $key): string
{
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'sign_type' || $v === null || (string) $v === '') {
            continue;
        }
        $filtered[(string) $k] = (string) $v;
    }
    ksort($filtered, SORT_STRING);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return md5(implode('&', $pairs) . $key);
}

/** 组装跳转支付表单参数（含签名） */
function codepay_build_payment(string $channel, string $orderNo, float $amount): array
{
    $c = codepay_config();
    $params = [
        'pid' => $c['pid'],
        'type' => $channel,
        'out_trade_no' => $orderNo,
        'notify_url' => codepay_site_base() . '/pay/notify',
        'return_url' => codepay_site_base() . '/dashboard/wallet?paid=1',
        'name' => '余额充值',
        'money' => number_format($amount, 2, '.', ''),
        'sitename' => setting('site_name', 'NAT Cloud'),
    ];
    $params['sign'] = codepay_sign($params, $c['key']);
    $params['sign_type'] = 'MD5';
    return $params;
}
