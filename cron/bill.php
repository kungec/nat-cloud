<?php
declare(strict_types=1);

/* NAT Cloud - 按量计费定时任务（供 crontab 调用）
 * crontab 示例（每5分钟）：
 *   *\/5 * * * * /www/server/php/74/bin/php /www/wwwroot/你的站点/cron/bill.php >> /www/wwwroot/你的站点/cron/bill.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

define('NC_ROOT', dirname(__DIR__));
require NC_ROOT . '/app/bootstrap.php';

$stats = billing_run_all();
$stuck = billing_cleanup_stuck_creating();
echo '[' . date('Y-m-d H:i:s') . '] billed=' . $stats['billed']
    . ' overdue=' . $stats['overdue']
    . ' released=' . $stats['released']
    . ' stuck_fixed=' . $stuck . "\n";

/* 流量超额停机：月流量（双向 rx+tx）超出配额的运行实例自动停机；
   次月 1 日流量重置后，用户可自行开机恢复。 */
$trafficStopped = 0;
try {
    kyc_ensure_columns();
    $rows = db_all("SELECT id, node_id, name, traffic_gb, traffic_rx, traffic_tx FROM instances WHERE status = 'running' AND node_id > 0 AND traffic_gb > 0");
    foreach ($rows as $r) {
        $used = (int) $r['traffic_rx'] + (int) $r['traffic_tx'];
        if ($used > (int) $r['traffic_gb'] * 1073741824) {
            node_enqueue((int) $r['node_id'], 'stop', ['name' => $r['name']]);
            db_exec("UPDATE instances SET status = 'stopped' WHERE id = ?", [$r['id']]);
            $trafficStopped++;
        }
    }
} catch (Throwable $e) {
    $trafficStopped = -1;
}
echo '[' . date('Y-m-d H:i:s') . '] traffic_stopped=' . $trafficStopped . "\n";

/* 到期邮件提醒：包月运行中实例到期前 7/3/1 天各发一封（余额自动续费在 billing_run_all 内完成） */
$reminded = 0;
try {
    $reminded = billing_send_expiry_reminders();
} catch (Throwable $e) {
    $reminded = -1;
}
echo '[' . date('Y-m-d H:i:s') . '] expiry_reminded=' . $reminded . "\n";

/* USDT(TRC20) 充值单兜底核对：轮询公链自动入账，超时未支付自动关闭 */
$usdtDone = 0;
try {
    usdt_ensure_columns();
    $rows = db_all("SELECT * FROM orders WHERE type = 'topup' AND status = 'pending' AND usdt_amount IS NOT NULL AND created_at > DATE_SUB(NOW(), INTERVAL 40 MINUTE) ORDER BY id ASC LIMIT 20");
    foreach ($rows as $o) {
        $tx = usdt_find_payment($o);
        if ($tx !== null && usdt_credit_order($o, $tx)) {
            $usdtDone++;
        }
    }
    db_exec("UPDATE orders SET status = 'cancelled' WHERE type = 'topup' AND status = 'pending' AND usdt_amount IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 45 MINUTE)");
} catch (Throwable $e) {
    $usdtDone = -1;
}
echo '[' . date('Y-m-d H:i:s') . '] usdt_paid=' . $usdtDone . "\n";
