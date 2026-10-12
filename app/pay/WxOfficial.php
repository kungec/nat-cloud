<?php
declare(strict_types=1);

/* NAT Cloud - 微信官方支付（Native 扫码，APIv3）
   商户私钥 SHA256withRSA 生成 Authorization 头 → /v3/pay/transactions/native → code_url；
   回调验签用「微信支付公钥」（新商户默认公钥模式），报文体 AES-256-GCM 解密。 */

function wx_config(): array
{
    return [
        'enable' => setting('wx_enable', '0') === '1',
        'mchid' => trim(setting('wx_mchid', '')),
        'appid' => trim(setting('wx_appid', '')),
        'serial_no' => trim(setting('wx_serial_no', '')),
        'api_v3_key' => secret_decrypt(trim(setting('wx_api_v3_key', ''))),
        'private_key' => secret_decrypt(trim(setting('wx_private_key', ''))),
        'public_key' => trim(setting('wx_public_key', '')),
    ];
}

function wx_ready(): bool
{
    $c = wx_config();
    return $c['enable'] && $c['mchid'] !== '' && $c['appid'] !== '' && $c['serial_no'] !== ''
        && $c['api_v3_key'] !== '' && $c['private_key'] !== '' && $c['public_key'] !== '';
}

/** APIv3 请求签名串：HTTP方法\nURL路径\n时间戳\n随机串\n报文\n */
function wx_sign(string $message, string $privateKeyPem): string
{
    $pem = pay_normalize_pem($privateKeyPem, 'private');
    $res = openssl_pkey_get_private($pem);
    if ($res === false) {
        return '';
    }
    $sig = '';
    $ok = openssl_sign($message, $sig, $res, OPENSSL_ALGO_SHA256);
    openssl_free_key($res);
    return $ok ? base64_encode($sig) : '';
}

/** 带鉴权头的 APIv3 请求（GET body 为空串） */
function wx_api(string $method, string $urlPath, array $body = []): ?array
{
    $c = wx_config();
    if (!wx_ready()) {
        return null;
    }
    $payload = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
    $nonce = bin2hex(random_bytes(8));
    $ts = (string) time();
    $signMsg = strtoupper($method) . "\n" . $urlPath . "\n" . $ts . "\n" . $nonce . "\n" . $payload . "\n";
    $signature = wx_sign($signMsg, $c['private_key']);
    if ($signature === '') {
        return null;
    }
    $auth = 'WECHATPAY2-SHA256-RSA2048 mchid="' . $c['mchid'] . '",nonce_str="' . $nonce
        . '",timestamp="' . $ts . '",serial_no="' . $c['serial_no'] . '",signature="' . $signature . '"';
    $ch = curl_init('https://api.mch.weixin.qq.com' . $urlPath);
    $headers = ['Accept: application/json', 'Authorization: ' . $auth, 'Content-Type: application/json'];
    if ($payload !== '') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers[] = 'User-Agent: natcloud/1.0';
    }
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($resp) || $resp === '') {
        return null;
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return null;
    }
    $json['_http'] = $code;
    return $json;
}

/** Native 下单：成功返回 ['code_url'=>...]，失败 ['err'=>msg] */
function wx_native_order(string $orderNo, float $amount, string $notifyUrl, string $description = '余额充值'): array
{
    $c = wx_config();
    $resp = wx_api('POST', '/v3/pay/transactions/native', [
        'appid' => $c['appid'],
        'mchid' => $c['mchid'],
        'description' => $description,
        'out_trade_no' => $orderNo,
        'time_expire' => date('YmdHis', time() + 900),
        'notify_url' => $notifyUrl,
        'amount' => ['total' => (int) round($amount * 100), 'currency' => 'CNY'],
    ]);
    if ($resp === null) {
        return ['err' => '微信支付网关请求失败'];
    }
    if (!empty($resp['code_url'])) {
        return ['code_url' => (string) $resp['code_url']];
    }
    return ['err' => '微信下单失败：' . ($resp['message'] ?? ($resp['code'] ?? '未知错误'))];
}

/** 回调验签：Wechatpay 时间戳/随机串/报文 用「微信支付公钥」验证 Wechatpay-Signature */
function wx_verify_callback(array $headers, string $rawBody): bool
{
    $c = wx_config();
    $ts = (string) ($headers['wechatpay-timestamp'] ?? '');
    $nonce = (string) ($headers['wechatpay-nonce'] ?? '');
    $sig = (string) ($headers['wechatpay-signature'] ?? '');
    if ($ts === '' || $nonce === '' || $sig === '') {
        return false;
    }
    if (abs(time() - (int) $ts) > 300) {
        return false; /* 防重放 */
    }
    $pem = pay_normalize_pem($c['public_key'], 'public');
    $res = openssl_pkey_get_public($pem);
    if ($res === false) {
        return false;
    }
    $ok = openssl_verify($ts . "\n" . $nonce . "\n" . $rawBody . "\n", base64_decode($sig) ?: '', $res, OPENSSL_ALGO_SHA256) === 1;
    openssl_free_key($res);
    return $ok;
}

/** AES-256-GCM 解密回调 resource（APIv3 密钥；tag 为密文末 16 字节） */
function wx_decrypt_resource(array $resource): ?array
{
    $c = wx_config();
    $cipher = base64_decode((string) ($resource['ciphertext'] ?? ''));
    $nonce = (string) ($resource['nonce'] ?? '');
    $aad = (string) ($resource['associated_data'] ?? '');
    if (strlen($cipher) <= 16 || $nonce === '') {
        return null;
    }
    $tag = substr($cipher, -16);
    $body = substr($cipher, 0, -16);
    $plain = openssl_decrypt($body, 'aes-256-gcm', $c['api_v3_key'], OPENSSL_RAW_DATA, $nonce, $tag, $aad);
    if ($plain === false) {
        return null;
    }
    $json = json_decode($plain, true);
    return is_array($json) ? $json : null;
}

/** 主动查单：'SUCCESS' 返回 true，NOTPAY/CLOSED 等返回 false；请求异常返回 null */
function wx_query_paid(string $orderNo): ?bool
{
    $resp = wx_api('GET', '/v3/pay/transactions/out-trade-no/' . urlencode($orderNo) . '?mchid=' . urlencode(wx_config()['mchid']));
    if ($resp === null) {
        return null;
    }
    return ((string) ($resp['trade_state'] ?? '')) === 'SUCCESS';
}
