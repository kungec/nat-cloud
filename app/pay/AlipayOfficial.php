<?php
declare(strict_types=1);

/* NAT Cloud - 支付宝官方接口（当面付 alipay.trade.precreate 扫码支付）
   公钥模式（非证书）：应用私钥 RSA2 签名 → openapi 网关 → 返回 qr_code；
   异步通知与同步响应均用「支付宝公钥」验签（SHA256withRSA）。 */

function alipay_config(): array
{
    return [
        'enable' => setting('alipay_enable', '0') === '1',
        'appid' => trim(setting('alipay_appid', '')),
        'private_key' => secret_decrypt(trim(setting('alipay_private_key', ''))),
        'public_key' => trim(setting('alipay_public_key', '')),
    ];
}

function alipay_ready(): bool
{
    $c = alipay_config();
    return $c['enable'] && $c['appid'] !== '' && $c['private_key'] !== '' && $c['public_key'] !== '';
}

/** 统一规范 PEM：支持完整 PEM（多行原样/单行重建）或裸 base64（PKCS8/PKCS1 双格式尝试） */
function pay_normalize_pem(string $key, string $type): string
{
    $key = str_replace(['\\n', "\r\n"], "\n", trim($key));
    if (strpos($key, '-----BEGIN') !== false) {
        if (strpos($key, "\n") !== false) {
            return substr($key, -1) === "\n" ? $key : $key . "\n";
        }
        /* 单行 PEM（空白已被吞）：剥掉头尾，按裸 base64 重建 */
        if (preg_match('~-----BEGIN[A-Z ]*?-----([A-Za-z0-9+/=]+)-----END[A-Z ]*?-----~', $key, $m)) {
            $key = $m[1];
        } else {
            return $key . "\n";
        }
    }
    $body = preg_replace('~\s+~', '', $key);
    $split = static function (string $head) use ($body): string {
        return "-----BEGIN {$head}-----\n" . chunk_split($body, 64, "\n") . "-----END {$head}-----\n";
    };
    if ($type === 'public') {
        return $split('PUBLIC KEY');
    }
    foreach (['PRIVATE KEY', 'RSA PRIVATE KEY'] as $head) {
        $cand = $split($head);
        $res = openssl_pkey_get_private($cand);
        if ($res !== false) {
            if (function_exists('openssl_free_key')) {
                openssl_free_key($res);
            }
            return $cand;
        }
    }
    return $split('PRIVATE KEY');
}

function alipay_sign(array $params, string $privateKeyPem): string
{
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'sign_type' || $v === '' || $v === null) {
            continue;
        }
        $filtered[(string) $k] = (string) $v;
    }
    ksort($filtered, SORT_STRING);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $data = implode('&', $pairs);
    $pem = pay_normalize_pem($privateKeyPem, 'private');
    $res = openssl_pkey_get_private($pem);
    if ($res === false) {
        return '';
    }
    $sig = '';
    $ok = openssl_sign($data, $sig, $res, OPENSSL_ALGO_SHA256);
    openssl_free_key($res);
    return $ok ? base64_encode($sig) : '';
}

/** 支付宝公钥验签（异步通知/同步响应通用）：待验串=按 key 升序的 k=v&（不含 sign/sign_type） */
function alipay_verify(array $params, string $sign): bool
{
    $c = alipay_config();
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'sign' || $k === 'sign_type' || $v === '' || $v === null) {
            continue;
        }
        $filtered[(string) $k] = (string) $v;
    }
    ksort($filtered, SORT_STRING);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $pem = pay_normalize_pem($c['public_key'], 'public');
    $res = openssl_pkey_get_public($pem);
    if ($res === false) {
        return false;
    }
    $ok = openssl_verify(implode('&', $pairs), base64_decode($sign) ?: '', $res, OPENSSL_ALGO_SHA256) === 1;
    openssl_free_key($res);
    return $ok;
}

/** 支付宝 openapi 通用请求：返回业务响应数组或 null（网络/签名失败） */
function alipay_api(string $method, array $biz, string $notifyUrl = ''): ?array
{
    $c = alipay_config();
    if (!alipay_ready()) {
        return null;
    }
    $params = [
        'app_id' => $c['appid'],
        'method' => $method,
        'format' => 'JSON',
        'charset' => 'utf-8',
        'sign_type' => 'RSA2',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0',
    ];
    if ($notifyUrl !== '') {
        $params['notify_url'] = $notifyUrl;
    }
    $params['biz_content'] = json_encode($biz, JSON_UNESCAPED_UNICODE);
    $params['sign'] = alipay_sign($params, $c['private_key']);
    if ($params['sign'] === '') {
        return null;
    }
    $ch = curl_init('https://openapi.alipay.com/gateway.do');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp) || $resp === '') {
        return null;
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return null;
    }
    $node = $method . '_response';
    return isset($json[$node]) ? $json[$node] : null;
}

/** 当面付预下单：成功返回 ['qr'=>qr_code, ...]，失败返回 ['err'=>msg] */
function alipay_precreate(string $orderNo, float $amount, string $notifyUrl): array
{
    $resp = alipay_api('alipay.trade.precreate', [
        'out_trade_no' => $orderNo,
        'total_amount' => number_format($amount, 2, '.', ''),
        'subject' => '余额充值',
        'timeout_express' => '15m',
    ], $notifyUrl);
    if ($resp === null) {
        return ['err' => '支付宝网关请求失败'];
    }
    if ((string) ($resp['code'] ?? '') !== '10000') {
        return ['err' => '支付宝预下单失败：' . ($resp['sub_msg'] ?? ($resp['msg'] ?? '未知错误'))];
    }
    $qr = trim((string) ($resp['qr_code'] ?? ''));
    if ($qr === '') {
        return ['err' => '支付宝未返回收款二维码'];
    }
    return ['qr' => $qr];
}

/** 主动查单：'TRADE_SUCCESS'/'TRADE_FINISHED' 返回 true（已支付），其余 false；请求异常返回 null */
function alipay_query_paid(string $orderNo): ?bool
{
    $resp = alipay_api('alipay.trade.query', [
        'out_trade_no' => $orderNo,
    ]);
    if ($resp === null) {
        return null;
    }
    if ((string) ($resp['code'] ?? '') !== '10000') {
        return false; /* 40004 订单不存在等 */
    }
    $st = (string) ($resp['trade_status'] ?? '');
    return $st === 'TRADE_SUCCESS' || $st === 'TRADE_FINISHED';
}
