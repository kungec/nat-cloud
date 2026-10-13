<?php
declare(strict_types=1);

/* NAT Cloud - 推荐返利
 * 用户拥有专属邀请码（懒生成），新注册带 ?ref=邀请码 即归因给邀请人；
 * 被邀请人每次在线充值到账后，邀请人按比例获得返佣（进余额+流水）。 */

function ref_ensure_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach ([
        'SELECT ref_code FROM users LIMIT 1' => 'ALTER TABLE users ADD COLUMN ref_code VARCHAR(16) NULL',
        'SELECT ref_by FROM users LIMIT 1' => 'ALTER TABLE users ADD COLUMN ref_by INT UNSIGNED NULL',
    ] as $probe => $alter) {
        try {
            db_val($probe);
        } catch (Throwable $e) {
            db_exec($alter);
        }
    }
    try {
        db_exec('ALTER TABLE users ADD UNIQUE KEY uq_ref_code (ref_code)');
    } catch (Throwable $e) {
        /* 索引已存在 */
    }
}

function ref_enabled(): bool
{
    return setting('ref_enabled', '1') === '1';
}

function ref_percent(): float
{
    return max(1.0, min(50.0, (float) setting('ref_percent', '10')));
}

/** 懒生成当前用户专属邀请码（8 位大写字母数字，避开易混字符） */
function ref_code_for(int $uid): string
{
    ref_ensure_columns();
    $code = (string) db_val('SELECT ref_code FROM users WHERE id = ? LIMIT 1', [$uid]);
    if ($code !== '') {
        return $code;
    }
    $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($try = 0; $try < 5; $try++) {
        $code = 'NC';
        for ($i = 0; $i < 6; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }
        try {
            $ins = db()->prepare('UPDATE users SET ref_code = ? WHERE id = ? AND ref_code IS NULL');
            $ins->execute([$code, $uid]);
            if ($ins->rowCount() === 1) {
                return $code;
            }
            return (string) db_val('SELECT ref_code FROM users WHERE id = ? LIMIT 1', [$uid]);
        } catch (Throwable $e) {
            /* 码冲突，换一个重试 */
        }
    }
    return '';
}

/** 按邀请码解析邀请人 uid（码不存在或停用返回 0） */
function ref_resolve(string $code): int
{
    if (!ref_enabled() || !preg_match('~^NC[A-Z0-9]{6}$~', $code)) {
        return 0;
    }
    ref_ensure_columns();
    return (int) db_val('SELECT id FROM users WHERE ref_code = ? AND status = 1 LIMIT 1', [$code]);
}

/**
 * 充值到账后的返佣（仅在线充值 type='topup' 触发；返佣本身不再产生返佣）。
 */
function ref_award_on_topup(int $uid, float $amount): void
{
    if (!ref_enabled() || $amount <= 0) {
        return;
    }
    ref_ensure_columns();
    $refBy = (int) db_val('SELECT ref_by FROM users WHERE id = ? LIMIT 1', [$uid]);
    if ($refBy <= 0 || $refBy === $uid) {
        return;
    }
    $referrer = db_one('SELECT id, username, status FROM users WHERE id = ? AND status = 1 LIMIT 1', [$refBy]);
    if (!$referrer) {
        return;
    }
    $commission = round($amount * ref_percent() / 100, 2);
    if ($commission < 0.01) {
        return;
    }
    try {
        billing_credit($refBy, $commission, 'referral', sprintf('推荐返佣：受邀用户 %s 充值 ¥%s，按 %d%% 返佣', db_val('SELECT username FROM users WHERE id = ' . (int) $uid), money($amount), (int) ref_percent()));
    } catch (Throwable $e) {
        /* 返佣失败不影响充值主流程 */
    }
}

/** 邀请页统计 */
function ref_stats(int $uid): array
{
    ref_ensure_columns();
    return [
        'invited' => (int) db_val('SELECT COUNT(*) FROM users WHERE ref_by = ?', [$uid]),
        'commission' => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE uid = ? AND type = 'referral'", [$uid]),
    ];
}
