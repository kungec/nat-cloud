<?php
declare(strict_types=1);

/* NAT Cloud - 站点设置（settings 键值表，带请求内缓存） */

/* 自愈：表缺失则创建；字符集被还原成 latin1 等时转回 utf8mb4（否则中文保存报 1366） */
function settings_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(64) NOT NULL PRIMARY KEY, svalue TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $cs = db_val("SELECT cs.character_set_name FROM information_schema.tables t JOIN information_schema.collation_character_set_applicability cs ON cs.table_schema = t.table_schema AND cs.collation_name = t.table_collation WHERE t.table_schema = DATABASE() AND t.table_name = 'settings'");
        if ($cs !== null && $cs !== '' && stripos((string) $cs, 'utf8') !== 0) {
            db()->exec('ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        /* PEM 私钥等长值需要 TEXT：旧表是 VARCHAR(500) 时自动升级 */
        $type = db_val("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'settings' AND column_name = 'svalue'");
        if ($type !== null && $type !== '' && stripos((string) $type, 'text') === false) {
            db()->exec('ALTER TABLE settings MODIFY svalue TEXT NOT NULL');
        }
    } catch (Throwable $e) {
        /* 自愈失败不阻塞页面 */
    }
}

function &_settings_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        settings_ensure_schema();
        $cache = [];
        $rows = db_all('SELECT skey, svalue FROM settings');
        foreach ($rows as $row) {
            $cache[$row['skey']] = (string) $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $cache = &_settings_cache();
    return $cache[$key] ?? $default;
}

function setting_int(string $key, int $default = 0): int
{
    $v = setting($key, '');
    return $v !== '' ? (int) $v : $default;
}

function setting_save(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    $stmt->execute([$key, $value]);
    /* 同步请求内缓存，保证保存后当次渲染即为新值 */
    $cache = &_settings_cache();
    $cache[$key] = $value;
}

function setting_public_ip(): string
{
    return setting('nat_public_ip', '');
}
