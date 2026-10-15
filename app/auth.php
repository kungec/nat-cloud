<?php
declare(strict_types=1);

/* NAT Cloud - 认证：基于 选择器:验证器 的持久登录令牌，支持会话管理与全部下线 */

const NC_AUTH_COOKIE = 'nc_auth';
const NC_ADMIN_COOKIE = 'nc_admin';

function auth_user(): ?array
{
    if (array_key_exists('NC_AUTH_USER', $GLOBALS)) {
        return $GLOBALS['NC_AUTH_USER'];
    }
    $user = auth_resolve_cookie(NC_AUTH_COOKIE, 0);
    $GLOBALS['NC_AUTH_USER'] = $user;
    return $user;
}

function auth_admin(): ?array
{
    if (array_key_exists('NC_AUTH_ADMIN', $GLOBALS)) {
        return $GLOBALS['NC_AUTH_ADMIN'];
    }
    $admin = auth_resolve_cookie(NC_ADMIN_COOKIE, 1);
    $GLOBALS['NC_AUTH_ADMIN'] = $admin;
    return $admin;
}

/**
 * 解析登录 Cookie（selector:validator 明文对），校验哈希与有效期。
 * 用户令牌关联 users 表，管理员令牌关联 admins 表。
 */
function auth_resolve_cookie(string $cookieName, int $isAdmin): ?array
{
    $raw = $_COOKIE[$cookieName] ?? '';
    if (!is_string($raw) || strpos($raw, ':') === false || strlen($raw) > 100) {
        return null;
    }
    $parts = explode(':', $raw, 2);
    $selector = $parts[0];
    $validator = $parts[1];
    if (!preg_match('~^[a-f0-9]{24}$~', $selector) || strlen($validator) < 40) {
        return null;
    }
    if ($isAdmin === 1) {
        $row = db_one(
            'SELECT t.id AS token_id, t.validator_hash, t.expire_at, a.id, a.username, a.created_at FROM login_tokens t JOIN admins a ON a.id = t.user_id WHERE t.selector = ? AND t.is_admin = 1 LIMIT 1',
            [$selector]
        );
        if (!$row || !hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            return null;
        }
        if (strtotime((string) $row['expire_at']) < time()) {
            auth_delete_token((int) $row['token_id']);
            return null;
        }
        unset($row['validator_hash']);
        return $row;
    }
    $row = db_one(
        'SELECT t.id AS token_id, t.validator_hash, t.expire_at, u.* FROM login_tokens t JOIN users u ON u.id = t.user_id WHERE t.selector = ? AND t.is_admin = 0 LIMIT 1',
        [$selector]
    );
    if (!$row || !hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
        return null;
    }
    if (strtotime((string) $row['expire_at']) < time()) {
        auth_delete_token((int) $row['token_id']);
        return null;
    }
    if ((int) $row['status'] !== 1) {
        return null;
    }
    unset($row['validator_hash']);
    return $row;
}

function auth_issue_token(int $userId, int $isAdmin, bool $remember): string
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $days = $remember ? 15 : 1;
    $stmt = db()->prepare('INSERT INTO login_tokens (user_id, selector, validator_hash, ua, ip, is_admin, created_at, expire_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))');
    $stmt->execute([$userId, $selector, hash('sha256', $validator), mb_substr(user_agent(), 0, 180), client_ip(), $isAdmin, $days]);
    $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $cookieName = $isAdmin === 1 ? NC_ADMIN_COOKIE : NC_AUTH_COOKIE;
    setcookie($cookieName, $selector . ':' . $validator, [
        'expires' => $remember ? time() + $days * 86400 : 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    /* 同步本次请求的读取视图，使登录当次渲染即可识别登录态 */
    $_COOKIE[$cookieName] = $selector . ':' . $validator;
    unset($GLOBALS['NC_AUTH_USER'], $GLOBALS['NC_AUTH_ADMIN']);
    return $selector;
}

function auth_delete_token(int $tokenId): void
{
    $stmt = db()->prepare('DELETE FROM login_tokens WHERE id = ?');
    $stmt->execute([$tokenId]);
}

function auth_logout(string $cookieName, int $isAdmin): void
{
    $raw = $_COOKIE[$cookieName] ?? '';
    if (is_string($raw) && strpos($raw, ':') !== false) {
        $selector = explode(':', $raw, 2)[0];
        if (preg_match('~^[a-f0-9]{24}$~', $selector)) {
            $stmt = db()->prepare('DELETE FROM login_tokens WHERE selector = ? AND is_admin = ?');
            $stmt->execute([$selector, $isAdmin]);
        }
    }
    $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie($cookieName, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    unset($GLOBALS['NC_AUTH_USER'], $GLOBALS['NC_AUTH_ADMIN']);
}

function auth_logout_all(int $userId, int $isAdmin): void
{
    $stmt = db()->prepare('DELETE FROM login_tokens WHERE user_id = ? AND is_admin = ?');
    $stmt->execute([$userId, $isAdmin]);
}

function auth_sessions(int $userId, int $isAdmin): array
{
    $current = $_COOKIE[$isAdmin === 1 ? NC_ADMIN_COOKIE : NC_AUTH_COOKIE] ?? '';
    $currentSelector = is_string($current) && strpos($current, ':') !== false ? explode(':', $current, 2)[0] : '';
    $stmt = db()->prepare('SELECT id, selector, ua, ip, created_at, expire_at FROM login_tokens WHERE user_id = ? AND is_admin = ? AND expire_at > NOW() ORDER BY created_at DESC');
    $stmt->execute([$userId, $isAdmin]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['is_current'] = hash_equals((string) $row['selector'], $currentSelector);
    }
    return $rows;
}

/* ---------- 登录尝试限速（账号与 IP 分维度，10分钟窗口）----------
 * 账号维度阈值低（针对单账号爆破），IP 维度阈值高（避免共享出口 IP/NAT 用户被连带锁死）。 */
function auth_failure_count(string $field, string $value): int
{
    $col = $field === 'ip' ? 'ip' : 'account';
    $stmt = db()->prepare("SELECT COUNT(*) FROM auth_failures WHERE $col = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmt->execute([mb_substr($value, 0, 120)]);
    return (int) $stmt->fetchColumn();
}

function auth_record_failure(string $account, string $ip): void
{
    $stmt = db()->prepare('INSERT INTO auth_failures (account, ip, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([mb_substr($account, 0, 120), $ip]);
    /* 顺手 TTL 清理（约 2% 概率触发）：防表无限膨胀拖垮索引与磁盘 */
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM auth_failures WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    }
}

function auth_clear_failures(string $account): void
{
    $stmt = db()->prepare('DELETE FROM auth_failures WHERE account = ?');
    $stmt->execute([mb_substr($account, 0, 120)]);
}
