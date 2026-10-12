<?php
declare(strict_types=1);

/* NAT Cloud - 支付回调（公开端点，无需登录）
   /pay/notify       ：码支付（易支付协议）异步通知，MD5 验签；
   /pay/alipay/notify：支付宝当面付异步通知，RSA2 公钥验签；
   /pay/wx/notify    ：微信官方 Native 异步通知，APIv3 公钥验签 + AES-GCM 解密。
   三者共用 pay_credit_order() 幂等入账。 */

/** 幂等入账：订单 pending → paid 并加余额、写流水。返回是否本次实际入账。 */
function pay_credit_order(string $orderNo, float $amount, string $tradeNo, string $remark): bool
{
    if (!preg_match('~^\d{14}[0-9A-F]{6}$~', $orderNo)) {
        return false;
    }
    db()->beginTransaction();
    try {
        $stmt = db()->prepare("SELECT * FROM orders WHERE order_no = ? AND type = 'topup' AND status = 'pending' FOR UPDATE");
        $stmt->execute([$orderNo]);
        $order = $stmt->fetch();
        if (!$order) {
            /* 已处理或不存在：幂等确认，防止网关重复推送 */
            db()->rollBack();
            return false;
        }
        if (abs((float) $order['amount'] - $amount) > 0.01) {
            db()->rollBack();
            return false;
        }
        $upd = db()->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ?");
        $upd->execute([$order['id']]);
        $stmt = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $stmt->execute([(float) $order['amount'], $order['uid']]);
        $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$order['uid']]);
        $after = (float) $stmt->fetchColumn();
        $ins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'topup', ?, ?, ?, NOW())");
        $ins->execute([$order['uid'], (float) $order['amount'], $after, $remark]);
        db()->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return false;
    }
}

function pay_handle(string $path, string $method): void
{
    /* ---------- 码支付（易支付协议） ---------- */
    if ($path === '/pay/notify' && ($method === 'GET' || $method === 'POST')) {
        header('Content-Type: text/plain; charset=utf-8');
        $c = codepay_config();
        if ($c['url'] === '' || $c['pid'] === '' || $c['key'] === '') {
            echo 'pay not configured';
            exit;
        }
        $params = array_merge($_GET, $_POST);
        $givenSign = (string) ($params['sign'] ?? '');
        if ($givenSign === '' || !hash_equals(codepay_sign($params, $c['key']), $givenSign)) {
            echo 'sign error';
            exit;
        }
        if ((string) ($params['pid'] ?? '') !== $c['pid']) {
            echo 'pid mismatch';
            exit;
        }
        if ((string) ($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            echo 'success'; /* 非成功状态直接确认，避免网关重复推送 */
            exit;
        }
        $orderNo = trim((string) ($params['out_trade_no'] ?? ''));
        $tradeNo = trim((string) ($params['trade_no'] ?? ''));
        $money = (float) ($params['money'] ?? 0);
        pay_credit_order($orderNo, $money, $tradeNo, '在线充值到账 单号' . $orderNo . ($tradeNo !== '' ? ' 流水号' . $tradeNo : ''));
        echo 'success';
        exit;
    }

    /* ---------- 支付宝当面付 ---------- */
    if ($path === '/pay/alipay/notify' && ($method === 'GET' || $method === 'POST')) {
        header('Content-Type: text/plain; charset=utf-8');
        if (!alipay_ready()) {
            echo 'pay not configured';
            exit;
        }
        $params = array_merge($_GET, $_POST);
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '' || !alipay_verify($params, $sign)) {
            echo 'sign error';
            exit;
        }
        $st = (string) ($params['trade_status'] ?? '');
        if ($st !== 'TRADE_SUCCESS' && $st !== 'TRADE_FINISHED') {
            echo 'success';
            exit;
        }
        $orderNo = trim((string) ($params['out_trade_no'] ?? ''));
        $tradeNo = trim((string) ($params['trade_no'] ?? ''));
        $money = (float) ($params['total_amount'] ?? 0);
        pay_credit_order($orderNo, $money, $tradeNo, '支付宝充值到账 单号' . $orderNo . ($tradeNo !== '' ? ' 流水号' . $tradeNo : ''));
        echo 'success';
        exit;
    }

    /* ---------- 微信官方 Native ---------- */
    if ($path === '/pay/wx/notify' && $method === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $fail = function (string $msg) {
            echo json_encode(['code' => 'FAIL', 'message' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!wx_ready()) {
            $fail('pay not configured');
        }
        $raw = file_get_contents('php://input');
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_WECHATPAY_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = (string) $v;
            }
        }
        if (!wx_verify_callback($headers, (string) $raw)) {
            $fail('签名验证失败');
        }
        $body = json_decode((string) $raw, true);
        $resource = is_array($body) ? (array) ($body['resource'] ?? []) : [];
        $data = wx_decrypt_resource($resource);
        if ($data === null) {
            $fail('报文解密失败');
        }
        if ((string) ($data['trade_state'] ?? '') !== 'SUCCESS') {
            echo json_encode(['code' => 'SUCCESS']);
            exit;
        }
        $orderNo = trim((string) ($data['out_trade_no'] ?? ''));
        $tradeNo = trim((string) ($data['transaction_id'] ?? ''));
        $money = ((int) ($data['amount']['total'] ?? 0)) / 100;
        pay_credit_order($orderNo, $money, $tradeNo, '微信充值到账 单号' . $orderNo . ($tradeNo !== '' ? ' 流水号' . $tradeNo : ''));
        echo json_encode(['code' => 'SUCCESS']);
        exit;
    }

    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
