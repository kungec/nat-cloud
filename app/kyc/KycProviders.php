<?php
declare(strict_types=1);

/* NAT Cloud - 实名认证服务提供者
   极点（jidian）：完整对接（AlipayVerify 发起 / AlipayVerifyQuery 查询，支付宝人脸核身分发）；
   支付宝官方 / 微信官方 / 智简魔方：配置与就绪判断就绪，凭据开通后接入对应流程。 */

function kyc_config(): array
{
    return [
        'provider' => trim(setting('kyc_provider', '')),
        'global_required' => setting('kyc_global_required', '0') === '1',
        'jidian_app_id' => trim(setting('jidian_app_id', '')),
        'jidian_app_key' => trim(setting('jidian_app_key', '')),
        'alipay_app_id' => trim(setting('kyc_alipay_app_id', '')),
        'alipay_private_key' => secret_decrypt(trim(setting('kyc_alipay_private_key', ''))),
        'wx_app_id' => trim(setting('kyc_wx_app_id', '')),
        'wx_api_v3_key' => secret_decrypt(trim(setting('kyc_wx_api_v3_key', ''))),
        'zhijian_url' => rtrim(trim(setting('kyc_zhijian_url', '')), '/'),
        'zhijian_app_id' => trim(setting('kyc_zhijian_app_id', '')),
        'zhijian_app_key' => trim(setting('kyc_zhijian_app_key', '')),
    ];
}

/** 当前配置的实名接口是否可用 */
function kyc_provider_ready(): bool
{
    $c = kyc_config();
    switch ($c['provider']) {
        case 'jidian':
            return $c['jidian_app_id'] !== '' && $c['jidian_app_key'] !== '';
        case 'alipay':
            return $c['alipay_app_id'] !== '' && $c['alipay_private_key'] !== '';
        case 'wx':
            return $c['wx_app_id'] !== '' && $c['wx_api_v3_key'] !== '';
        case 'zhijian':
            return $c['zhijian_url'] !== '' && $c['zhijian_app_id'] !== '';
    }
    return false;
}

/** 极点：发起支付宝人脸认证。成功返回 ['ok'=>true,'certify_id'=>..,'cert_url'=>..] */
function kyc_jidian_curl(string $url): array
{
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25];
    /* 可选出站中继：上游屏蔽本机出站时，可经自建 TLS 透传隧道访问接口（中继 IP 在后台配置） */
    $relayIp = trim(setting('kyc_relay_ip', ''));
    if ($relayIp !== '') {
        $opts[CURLOPT_CONNECT_TO] = ['sm.jdidc.cn:443:' . $relayIp . ':28800'];
    }
    curl_setopt_array($ch, $opts);
    return [$ch];
}

function kyc_jidian_start(string $appId, string $appKey, string $name, string $idcard): array
{
    $url = 'https://sm.jdidc.cn/api/AlipayVerify?' . http_build_query([
        'appId' => $appId, 'appKey' => $appKey, 'name' => $name, 'idcard' => $idcard,
    ]);
    [$ch] = kyc_jidian_curl($url);
    $resp = curl_exec($ch);
    curl_close($ch);
    $json = is_string($resp) ? json_decode($resp, true) : null;
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => '实名接口请求失败，请稍后再试。'];
    }
    if ((int) ($json['code'] ?? 0) !== 1) {
        return ['ok' => false, 'msg' => '实名接口返回：' . (string) ($json['msg'] ?? '未知错误')];
    }
    $rd = (array) ($json['result_data'] ?? []);
    $cid = trim((string) ($rd['certify_id'] ?? ''));
    $curl = trim((string) ($rd['aliPayCertUrl'] ?? ''));
    if ($cid === '' || $curl === '') {
        return ['ok' => false, 'msg' => '实名接口未返回认证信息。'];
    }
    return ['ok' => true, 'certify_id' => $cid, 'cert_url' => $curl];
}

/** 极点：查询认证状态。返回 ['state'=>'passed'|'waiting'|'failed'|'error', 'msg'=>..] */
function kyc_jidian_query(string $appId, string $appKey, string $certifyId): array
{
    $url = 'https://sm.jdidc.cn/api/AlipayVerifyQuery?' . http_build_query([
        'appId' => $appId, 'appKey' => $appKey, 'certify_id' => $certifyId,
    ]);
    [$ch] = kyc_jidian_curl($url);
    $resp = curl_exec($ch);
    curl_close($ch);
    $json = is_string($resp) ? json_decode($resp, true) : null;
    if (!is_array($json)) {
        return ['state' => 'error', 'msg' => '实名状态查询失败，请稍后重试。'];
    }
    if ((int) ($json['code'] ?? 0) !== 1) {
        return ['state' => 'error', 'msg' => '实名状态查询：' . (string) ($json['msg'] ?? '未知错误')];
    }
    $rd = (array) ($json['result_data'] ?? []);
    $passed = strtoupper(trim((string) ($rd['passed'] ?? '')));
    if ($passed === 'T') {
        return ['state' => 'passed', 'msg' => '实名认证通过'];
    }
    return ['state' => 'waiting', 'msg' => '人脸认证尚未完成'];
}

/** 统一入口：发起认证（当前所配接口）。返回 ['ok','certify_id','cert_url'] 或 ['ok'=>false,'msg'] */
function kyc_start_verify(string $name, string $idcard): array
{
    $c = kyc_config();
    if (!kyc_provider_ready()) {
        return ['ok' => false, 'msg' => '实名接口未配置，请联系管理员。'];
    }
    switch ($c['provider']) {
        case 'jidian':
            return kyc_jidian_start($c['jidian_app_id'], $c['jidian_app_key'], $name, $idcard);
    }
    return ['ok' => false, 'msg' => '实名接口暂未开放，请联系管理员。'];
}

/** 统一入口：查询认证状态。返回 ['state'=>passed|waiting|error|none, 'msg'=>..] */
function kyc_check_verify(string $provider, string $certifyId): array
{
    $c = kyc_config();
    switch ($provider) {
        case 'jidian':
            return kyc_jidian_query($c['jidian_app_id'], $c['jidian_app_key'], $certifyId);
    }
    return ['state' => 'none', 'msg' => ''];
}
