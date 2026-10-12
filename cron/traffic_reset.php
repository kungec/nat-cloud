<?php
declare(strict_types=1);

/* NAT Cloud - 流量周期重置（按订购日锚定，供 crontab 每日调用）
 * crontab 示例：
 *   5 0 * * * /www/server/php/74/bin/php /www/wwwroot/你的站点/cron/traffic_reset.php >> /www/wwwroot/你的站点/cron/traffic_reset.log 2>&1
 * 规则：每月到达该实例「订购日」（created_at 的日号）时重置双向流量计数；
 * 当月无对应日号（如 31 日）时在当月最后一天重置；
 * 因流量超额被停机的实例仅重置计数，不自动开机，由用户自行开机恢复。
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

define('NC_ROOT', dirname(__DIR__));
require NC_ROOT . '/app/bootstrap.php';

/* traffic_reset_at 列自愈 */
try {
    db_val('SELECT traffic_reset_at FROM instances LIMIT 1');
} catch (Throwable $e) {
    db_exec('ALTER TABLE instances ADD COLUMN traffic_reset_at DATETIME NULL');
}

$today = new DateTimeImmutable('today');
$reset = 0;
$skipped = 0;

$rows = db_all("SELECT id, name, created_at, traffic_reset_at FROM instances WHERE status != 'released'");
foreach ($rows as $r) {
    try {
        $created = new DateTimeImmutable((string) $r['created_at']);
    } catch (Throwable $e) {
        continue;
    }
    $day = (int) $created->format('j');
    $y = (int) $today->format('Y');
    $m = (int) $today->format('n');
    /* 本月锚点：订购日；日号超出当月天数时取当月最后一天 */
    $anchor = DateTimeImmutable::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-%d 00:00:00', $y, $m, min($day, (int) $today->format('t'))));
    if ($anchor === false) {
        continue;
    }
    $last = $r['traffic_reset_at'] ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $r['traffic_reset_at']) : null;
    /* 本锚点之后已重置过则跳过（每日任务幂等） */
    if ($last !== false && $last !== null && $last >= $anchor) {
        $skipped++;
        continue;
    }
    db_exec('UPDATE instances SET traffic_rx = 0, traffic_tx = 0, traffic_reset_at = NOW() WHERE id = ?', [$r['id']]);
    $reset++;
}
echo '[' . date('Y-m-d H:i:s') . '] traffic_reset reset=' . $reset . ' skipped=' . $skipped . ' total=' . count($rows) . "\n";
