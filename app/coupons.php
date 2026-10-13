<?php
declare(strict_types=1);

/* NAT Cloud - 优惠码（折扣下单/续费）
 * coupons：码本身；coupon_redemptions：核销记录（每用户每码限用一次）。
 * 校验与核销分离：校验供表单提交即时反馈，核销必须在订单事务内调用。 */

function coupon_ensure_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db_exec("CREATE TABLE IF NOT EXISTS coupons (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      code VARCHAR(32) NOT NULL UNIQUE,
      type VARCHAR(10) NOT NULL DEFAULT 'percent',
      value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      min_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      quota INT NOT NULL DEFAULT 1,
      used INT NOT NULL DEFAULT 0,
      expires_at DATETIME NULL,
      status TINYINT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_exec("CREATE TABLE IF NOT EXISTS coupon_redemptions (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      coupon_id INT UNSIGNED NOT NULL,
      uid INT UNSIGNED NOT NULL,
      order_no VARCHAR(32) NOT NULL DEFAULT '',
      discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_cr (coupon_id, uid),
      KEY idx_cr_coupon (coupon_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function coupon_find(string $code): ?array
{
    coupon_ensure_tables();
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * 校验优惠码对（用户, 金额）是否可用。
 * 返回 [ok=>bool, msg=>string, discount=>float 抵扣金额]。
 */
function coupon_validate(?array $cp, int $uid, float $amount): array
{
    if (!$cp) {
        return [false, '优惠码不存在', 0.0];
    }
    if ((int) $cp['status'] !== 1) {
        return [false, '优惠码已停用', 0.0];
    }
    if ($cp['expires_at'] !== null && strtotime((string) $cp['expires_at']) < time()) {
        return [false, '优惠码已过期', 0.0];
    }
    if ((int) $cp['used'] >= (int) $cp['quota']) {
        return [false, '优惠码已被领完', 0.0];
    }
    $stmt = db()->prepare('SELECT id FROM coupon_redemptions WHERE coupon_id = ? AND uid = ? LIMIT 1');
    $stmt->execute([(int) $cp['id'], $uid]);
    if ($stmt->fetch()) {
        return [false, '该优惠码你已经使用过', 0.0];
    }
    if ($amount < (float) $cp['min_amount']) {
        return [false, '订单金额未达到优惠码使用门槛（满 ' . money((float) $cp['min_amount']) . ' 元可用）', 0.0];
    }
    $discount = (string) $cp['type'] === 'percent'
        ? round($amount * (float) $cp['value'] / 100, 2)
        : min((float) $cp['value'], $amount);
    $discount = min($discount, $amount);
    if ($discount <= 0) {
        return [false, '优惠码无可用折扣', 0.0];
    }
    return [true, '', $discount];
}

/**
 * 核销（必须在订单事务内调用）：配额原子递增防超发，写核销记录。
 * 配额竞争失败会抛异常，由调用方回滚整笔订单。
 */
function coupon_redeem(array $cp, int $uid, string $orderNo, float $discount): void
{
    $upd = db()->prepare('UPDATE coupons SET used = used + 1 WHERE id = ? AND used < quota');
    $upd->execute([(int) $cp['id']]);
    if ($upd->rowCount() !== 1) {
        throw new RuntimeException('优惠码配额不足');
    }
    $ins = db()->prepare('INSERT INTO coupon_redemptions (coupon_id, uid, order_no, discount) VALUES (?, ?, ?, ?)');
    $ins->execute([(int) $cp['id'], $uid, $orderNo, $discount]);
}
