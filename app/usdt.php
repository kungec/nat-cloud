<?php
declare(strict_types=1);

/* NAT Cloud - USDT(TRC20) 免挂机收款
   用户在站内扫码转账 → 网站通过 TronGrid 公链接口自动核对到账并入账。
   无需跳转外部页面，无需挂机程序。 */

const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // USDT-TRC20 官方合约

function usdt_enabled(): bool
{
    return setting('usdt_enable', '0') === '1'
        && usdt_address() !== ''
        && (float) setting('usdt_rate', '0') > 0;
}

/** TRC20 收款地址（后台配置，严格校验 base58 格式） */
function usdt_address(): string
{
    $a = trim(setting('usdt_address', ''));
    return preg_match('~^T[1-9A-HJ-NP-Za-km-z]{33}$~', $a) ? $a : '';
}

function usdt_rate(): float
{
    return max(0.01, (float) setting('usdt_rate', '7.30'));
}

function usdt_ensure_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach ([
        'SELECT usdt_amount FROM orders LIMIT 1' => 'ALTER TABLE orders ADD COLUMN usdt_amount DECIMAL(12,4) NULL DEFAULT NULL',
        'SELECT usdt_tx FROM orders LIMIT 1' => 'ALTER TABLE orders ADD COLUMN usdt_tx VARCHAR(90) NULL DEFAULT NULL',
    ] as $probe => $alter) {
        try {
            db_val($probe);
        } catch (Throwable $e) {
            db_exec($alter);
        }
    }
}

/** 人民币金额 → 应付 USDT 数量（两位小数） */
function usdt_amount_for(float $cny): float
{
    return round($cny / usdt_rate(), 2);
}

/**
 * 在 TronGrid 公链记录中查找该订单的到账交易。
 * 匹配条件：转入地址一致、USDT 合约一致、金额分单位完全一致、交易未被其他订单认领。
 * 未到账/查询失败返回 null（调用方可稍后重试）。
 */
function usdt_find_payment(array $order): ?string
{
    $addr = usdt_address();
    $expect = (int) round((float) ($order['usdt_amount'] ?? 0) * 1000000);
    if ($addr === '' || $expect <= 0) {
        return null;
    }
    $createdMs = (strtotime((string) $order['created_at']) - 120) * 1000;
    $url = 'https://api.trongrid.io/v1/accounts/' . rawurlencode($addr) . '/transactions/trc20'
         . '?limit=50&min_timestamp=' . $createdMs . '&contract_address=' . USDT_CONTRACT;
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "Accept: application/json\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);
    if (!is_array($j) || empty($j['success']) || !isset($j['data']) || !is_array($j['data'])) {
        return null;
    }
    foreach ($j['data'] as $tx) {
        if (!is_array($tx) || ($tx['to'] ?? '') !== $addr) {
            continue;
        }
        if (strtoupper((string) ($tx['type'] ?? '')) !== 'TRANSFER') {
            continue;
        }
        if (($tx['token_info']['address'] ?? '') !== USDT_CONTRACT || (int) ($tx['token_info']['decimals'] ?? 0) !== 6) {
            continue;
        }
        if ((int) ($tx['value'] ?? 0) !== $expect) {
            continue;
        }
        $txid = (string) ($tx['transaction_id'] ?? '');
        if ($txid === '') {
            continue;
        }
        $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE usdt_tx = ? AND id != ?');
        $st->execute([$txid, (int) $order['id']]);
        if ((int) $st->fetchColumn() > 0) {
            continue; /* 该笔交易已被其他订单认领 */
        }
        return $txid;
    }
    return null;
}

/** 到账入账（事务 + 幂等）：订单已处理时也返回 true */
function usdt_credit_order(array $order, string $txid): bool
{
    db()->beginTransaction();
    try {
        $st = db()->prepare("SELECT * FROM orders WHERE id = ? AND status = 'pending' FOR UPDATE");
        $st->execute([(int) $order['id']]);
        $fresh = $st->fetch();
        if (!$fresh) {
            db()->rollBack();
            return true; /* 已被处理，视为成功 */
        }
        $upd = db()->prepare("UPDATE orders SET status = 'paid', paid_at = NOW(), usdt_tx = ? WHERE id = ?");
        $upd->execute([$txid, (int) $order['id']]);
        $st = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $st->execute([(float) $fresh['amount'], $fresh['uid']]);
        $st = db()->prepare('SELECT balance FROM users WHERE id = ?');
        $st->execute([$fresh['uid']]);
        $after = (float) $st->fetchColumn();
        $ins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'topup', ?, ?, ?, NOW())");
        $ins->execute([$fresh['uid'], (float) $fresh['amount'], $after, 'USDT 充值到账 单号' . $fresh['order_no']]);
        db()->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return false;
    }
}
