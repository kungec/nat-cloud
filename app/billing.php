<?php
declare(strict_types=1);

/* NAT Cloud - 计费引擎
 * - 按量计费：按整小时从余额扣费，余额不足转入欠费停机，欠费超过72小时释放
 * - 包年包月：到期后进入欠费停机，超过72小时释放
 * - 停机（用户手动关机）不产生按量扣费
 */

function billing_run_user(int $userId): void
{
    $stmt = db()->prepare("SELECT * FROM instances WHERE uid = ? AND status IN ('running','overdue')");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $inst) {
        billing_process_instance($inst);
    }
}

function billing_run_all(): array
{
    $stats = ['billed' => 0, 'overdue' => 0, 'released' => 0];
    $rows = db_all("SELECT * FROM instances WHERE status IN ('running','overdue')");
    foreach ($rows as $inst) {
        $before = $inst['status'];
        $result = billing_process_instance($inst);
        if ($result['charged']) {
            $stats['billed']++;
        }
        if ($before !== 'overdue' && $result['status'] === 'overdue') {
            $stats['overdue']++;
        }
        if ($result['status'] === 'released') {
            $stats['released']++;
        }
    }
    return $stats;
}

/**
 * 部署卡死自愈：creating 超过 15 分钟仍未运行，判定部署失败。
 * 退款到余额（流水 type=refund）、回补库存、标记 failed 并下发节点 delete 清理残留。
 */
function billing_cleanup_stuck_creating(): int
{
    $rows = db_all("SELECT * FROM instances WHERE status = 'creating' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $n = 0;
    foreach ($rows as $inst) {
        db()->beginTransaction();
        try {
            $st = db()->prepare('SELECT status FROM instances WHERE id = ? FOR UPDATE');
            $st->execute([(int) $inst['id']]);
            if ($st->fetchColumn() !== 'creating') {
                db()->rollBack();
                continue;
            }
            $o = db_one('SELECT id, amount FROM orders WHERE instance_id = ? AND status = ? ORDER BY id DESC LIMIT 1', [(int) $inst['id'], 'paid']);
            if ($o && (float) $o['amount'] > 0) {
                billing_credit((int) $inst['uid'], (float) $o['amount'], 'refund', '实例 ' . $inst['name'] . ' 部署失败，自动退款');
                $u = db()->prepare("UPDATE orders SET status = 'refunded' WHERE id = ?");
                $u->execute([(int) $o['id']]);
            }
            if ((int) $inst['plan_id'] > 0) {
                db_exec('UPDATE plans SET stock = stock + 1 WHERE id = ?', [(int) $inst['plan_id']]);
            }
            $u2 = db()->prepare("UPDATE instances SET status = 'failed' WHERE id = ?");
            $u2->execute([(int) $inst['id']]);
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'delete', ['name' => $inst['name'], 'ip_octet' => (int) $inst['ip_octet']]);
            }
            db()->commit();
            $n++;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
        }
    }
    return $n;
}

/**
 * 处理单个实例的计费与状态流转。返回 ['charged'=>bool,'status'=>string]
 */
function billing_process_instance(array $inst): array
{
    $now = time();
    $result = ['charged' => false, 'status' => $inst['status']];

    if ($inst['status'] === 'released') {
        return $result;
    }

    /* 按量计费 */
    if ($inst['billing_mode'] === 'hourly') {
        /* 欠费停机：充值后余额重新满足门槛 → 自动恢复运行；欠费超 7 天释放回收 */
        if ($inst['status'] === 'overdue') {
            $price = (float) $inst['hourly_price'];
            $stmt = db()->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $inst['uid']]);
            $bal = (float) $stmt->fetchColumn();
            if ($bal >= max(5, round($price * 24, 2))) {
                $upd = db()->prepare("UPDATE instances SET status = 'running', stopped_at = NULL, last_billed_at = NOW() WHERE id = ? AND status = 'overdue'");
                $upd->execute([$inst['id']]);
                if ((int) $inst['node_id'] > 0) {
                    node_enqueue((int) $inst['node_id'], 'start', ['name' => $inst['name']]);
                }
                $result['status'] = 'running';
            } elseif ($inst['stopped_at'] && strtotime((string) $inst['stopped_at']) < $now - 7 * 86400) {
                node_lifecycle_on_delete($inst);
                billing_release_instance($inst);
                $result['status'] = 'released';
            }
            return $result;
        }
        if ($inst['status'] !== 'running') {
            return $result;
        }
        $last = $inst['last_billed_at'] ? strtotime((string) $inst['last_billed_at']) : $now;
        $hours = (int) floor(($now - $last) / 3600);
        if ($hours < 1) {
            return $result;
        }
        $price = (float) $inst['hourly_price'];
        $charge = round($hours * $price, 2);
        $uid = (int) $inst['uid'];
        $stmt = db()->prepare('SELECT id, balance, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if (!$user) {
            return $result;
        }
        if ((float) $user['balance'] < $charge) {
            /* 余额不足：欠费停机并记录扣费时刻 */
            $upd = db()->prepare("UPDATE instances SET status = 'overdue', stopped_at = NOW(), last_billed_at = NOW() WHERE id = ? AND status = 'running'");
            $upd->execute([$inst['id']]);
            $result['status'] = 'overdue';
            return $result;
        }
        db()->beginTransaction();
        try {
            $upd = db()->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
            $upd->execute([$charge, $uid, $charge]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('balance race');
            }
            $upd = db()->prepare("UPDATE instances SET last_billed_at = DATE_ADD(last_billed_at, INTERVAL ? HOUR) WHERE id = ?");
            $upd->execute([$hours, $inst['id']]);
            $ins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) SELECT ?, 'hourly', ?, users.balance, ?, NOW() FROM users WHERE users.id = ?");
            $ins->execute([$uid, -$charge, sprintf('实例 %s 按量扣费 %s 小时', $inst['name'], $hours), $uid]);
            db()->commit();
            $result['charged'] = true;
        } catch (Throwable $e) {
            db()->rollBack();
        }
        return $result;
    }

    /* 包年包月 */
    if ($inst['status'] === 'running' && $inst['expire_at'] !== null && strtotime((string) $inst['expire_at']) < $now) {
        $upd = db()->prepare("UPDATE instances SET status = 'overdue', stopped_at = NOW() WHERE id = ? AND status = 'running'");
        $upd->execute([$inst['id']]);
        node_lifecycle_on_expire($inst);
        $result['status'] = 'overdue';
        $inst['status'] = 'overdue';
        $inst['stopped_at'] = date('Y-m-d H:i:s');
    }
    if ($inst['status'] === 'overdue') {
        $anchor = $inst['expire_at'] ?: $inst['stopped_at'];
        /* 节点实例超 7 天删除数据；本机实例沿用 72 小时 */
        $graceHours = ((int) $inst['node_id'] > 0) ? 24 * 7 : 72;
        if ($anchor && strtotime((string) $anchor) < $now - $graceHours * 3600) {
            node_lifecycle_on_delete($inst);
            billing_release_instance($inst);
            $result['status'] = 'released';
        }
    }
    return $result;
}

/* 套餐剩余库存：stock 为剩余可开数量（开通递减、释放回补） */
function billing_plan_stock_left(array $plan): int
{
    return max(0, (int) $plan['stock']);
}

/* ---------- 计费周期（月付/季付/半年付/年付） ---------- */

function billing_ensure_pricing_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach ([
        'SELECT quarterly_price FROM plans LIMIT 1' => 'ALTER TABLE plans ADD COLUMN quarterly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'SELECT semiannually_price FROM plans LIMIT 1' => 'ALTER TABLE plans ADD COLUMN semiannually_price DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'SELECT annually_price FROM plans LIMIT 1' => 'ALTER TABLE plans ADD COLUMN annually_price DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'SELECT `cycle` FROM instances LIMIT 1' => "ALTER TABLE instances ADD COLUMN cycle VARCHAR(12) NOT NULL DEFAULT 'monthly'",
    ] as $probe => $alter) {
        try {
            db_val($probe);
        } catch (Throwable $e) {
            db_exec($alter);
        }
    }
}

function billing_cycle_months(string $cycle): int
{
    switch ($cycle) {
        case 'quarterly':
            return 3;
        case 'semiannually':
            return 6;
        case 'annually':
            return 12;
        default:
            return 1;
    }
}

function billing_cycle_name(string $cycle): string
{
    switch ($cycle) {
        case 'quarterly':
            return '季付';
        case 'semiannually':
            return '半年付';
        case 'annually':
            return '年付';
        case 'hourly':
            return '按量计费';
        default:
            return '月付';
    }
}

/** 周期价格：优先使用套餐设置的固定周期价，未设置则按月价 × 月数 */
function billing_cycle_price(array $plan, string $cycle): float
{
    billing_ensure_pricing_columns();
    $months = billing_cycle_months($cycle);
    $map = ['quarterly' => 'quarterly_price', 'semiannually' => 'semiannually_price', 'annually' => 'annually_price'];
    if (isset($map[$cycle])) {
        $fixed = (float) ($plan[$map[$cycle]] ?? 0);
        if ($fixed > 0) {
            return round($fixed, 2);
        }
    }
    return round((float) $plan['monthly_price'] * $months, 2);
}

/** 实例周期（旧数据回落：按订单月数推断，默认月付） */
function billing_instance_cycle(array $inst): string
{
    if ($inst['billing_mode'] !== 'monthly') {
        return 'hourly';
    }
    $cycle = (string) ($inst['cycle'] ?? 'monthly');
    return in_array($cycle, ['monthly', 'quarterly', 'semiannually', 'annually'], true) ? $cycle : 'monthly';
}

/** 配置展示：1核 / 512M 内存 / 16G 硬盘 */
function billing_spec_text(array $inst): string
{
    $ram = (int) $inst['ram_mb'] >= 1024
        ? (round($inst['ram_mb'] / 1024 * 10) / 10) . 'G'
        : (int) $inst['ram_mb'] . 'M';
    return (int) $inst['cpu'] . '核 / ' . $ram . ' 内存 / ' . (int) $inst['disk_gb'] . 'G 硬盘';
}

function billing_release_instance(array $inst): void
{
    try {
        $upd = db()->prepare("UPDATE instances SET status = 'released', released_at = NOW(), vnc_token = '', vnc_expire_at = NULL WHERE id = ?");
        $upd->execute([$inst['id']]);
    } catch (Throwable $e) {
        /* vnc 列尚未创建（首次部署）：只更新基础字段 */
        $upd = db()->prepare("UPDATE instances SET status = 'released', released_at = NOW() WHERE id = ?");
        $upd->execute([$inst['id']]);
    }
    if ((int) $inst['plan_id'] > 0) {
        db_exec('UPDATE plans SET stock = stock + 1 WHERE id = ?', [(int) $inst['plan_id']]);
    }
}

/**
 * 从设置中取 NAT 端口段分配：返回 [起始端口, 端口数] 或 null（耗尽）
 */
function billing_allocate_ports(): ?array
{
    $start = setting_int('nat_port_start', 20000);
    $per = setting_int('ports_per_instance', 15);
    $maxBlocks = setting_int('nat_port_blocks', 3000);
    $rows = db_all('SELECT forwarded_ports FROM instances WHERE status != ? ', ['released']);
    $used = [];
    foreach ($rows as $row) {
        $block = json_decode((string) $row['forwarded_ports'], true);
        if (is_array($block) && isset($block['start'])) {
            $used[] = (int) $block['start'];
        }
    }
    sort($used);
    $candidate = $start;
    $idx = 0;
    for ($i = 0; $i < $maxBlocks; $i++) {
        $candidate = $start + $i * $per;
        if ($idx >= count($used) || $used[$idx] !== $candidate) {
            return ['start' => $candidate, 'count' => $per];
        }
        $idx++;
    }
    return null;
}

/**
 * 按自定义配置开通实例（创建页滑杆选配，价格由服务端按设置单价计算）。
 * $cfg: mode, cpu, ram_mb, disk_gb, monthly_price, hourly_price, os, months, node_name, region
 */
function billing_create_config_instance(array $user, array $cfg): array
{
    $uid = (int) $user['id'];
    $mode = $cfg['mode'] === 'hourly' ? 'hourly' : 'monthly';
    $months = max(1, min(36, (int) $cfg['months']));
    $monthly = round((float) $cfg['monthly_price'], 2);
    $hourly = round((float) $cfg['hourly_price'], 4);

    $ports = billing_allocate_ports();
    if (!$ports) {
        return ['ok' => false, 'msg' => '节点端口资源已耗尽，请联系管理员。'];
    }

    $name = random_instance_name();
    $ip = setting_public_ip();

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT id, balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $fresh = $stmt->fetch();
        if (!$fresh) {
            throw new RuntimeException('用户不存在');
        }
        $balance = (float) $fresh['balance'];

        $charge = 0.0;
        $expire = null;
        if ($mode === 'monthly') {
            $charge = round($monthly * $months, 2);
            if ($balance < $charge) {
                db()->rollBack();
                return ['ok' => false, 'msg' => '余额不足，请先充值。本次需 ' . money($charge) . ' 元。'];
            }
            $expire = date('Y-m-d H:i:s', strtotime('+' . $months . ' months'));
        } else {
            if ($balance < max(5, round($hourly * 24, 2))) {
                db()->rollBack();
                return ['ok' => false, 'msg' => '按量计费要求余额至少可运行 24 小时（约 ' . money(max(5, $hourly * 24)) . ' 元），请先充值。'];
            }
        }

        if ($charge > 0) {
            $upd = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $upd->execute([$charge, $uid]);
            $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $after = (float) $stmt->fetchColumn();
        } else {
            $after = $balance;
        }

        $forwarded = json_encode(['start' => $ports['start'], 'count' => $ports['count']], JSON_UNESCAPED_UNICODE);
        $title = sprintf('自定义 %dC / %dG / %dG（%s）', $cfg['cpu'], (int) round($cfg['ram_mb'] / 1024), $cfg['disk_gb'], $name);
        $ins = db()->prepare('INSERT INTO instances (uid, plan_id, name, status, billing_mode, node_name, region, ip, nat_ports, ssh_port, cpu, ram_mb, disk_gb, traffic_gb, bandwidth_mbps, os, hourly_price, monthly_price, forwarded_ports, last_billed_at, expire_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)');
        $ins->execute([
            $uid, $name, 'running', $mode,
            $cfg['node_name'], $cfg['region'], $ip,
            sprintf('%d-%d', $ports['start'], $ports['start'] + $ports['count'] - 1), $ports['start'],
            $cfg['cpu'], $cfg['ram_mb'], $cfg['disk_gb'], 1000, 100,
            $cfg['os'], $hourly, $monthly, $forwarded, $expire,
        ]);
        $instanceId = (int) db()->lastInsertId();

        $orderTitle = $title . ' · ' . ($mode === 'monthly' ? '包年包月 ' . $months . ' 个月' : '按量计费');
        $oins = db()->prepare('INSERT INTO orders (order_no, uid, instance_id, type, title, months, amount, status, created_at, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $oins->execute([random_order_no(), $uid, $instanceId, $mode === 'monthly' ? 'new' : 'new_hourly', $orderTitle, $mode === 'monthly' ? $months : 0, $charge, 'paid']);

        if ($charge > 0) {
            $tins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'monthly', ?, ?, ?, NOW())");
            $tins->execute([$uid, -$charge, $after, $orderTitle]);
        }

        db()->commit();
        return ['ok' => true, 'msg' => '实例开通成功。', 'instance_id' => $instanceId];
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return ['ok' => false, 'msg' => '开通失败：' . $e->getMessage()];
    }
}

/* ---------- 节点实例（真实开通） ---------- */

function node_enqueue(int $nodeId, string $action, array $payload): int
{
    $ins = db()->prepare('INSERT INTO node_tasks (node_id, action, payload) VALUES (?, ?, ?)');
    $ins->execute([$nodeId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    return (int) db()->lastInsertId();
}

/**
 * 为节点分配内网尾号(2-254)与端口段：在节点端口范围内找第一个未占用块。
 * 返回 ['octet'=>n, 'port_base'=>n] 或 null（容量满）。
 */
function node_allocate_slot(int $nodeId): ?array
{
    $node = db_one('SELECT port_start, port_end FROM nodes WHERE id = ? AND status = 1', [$nodeId]);
    if (!$node) {
        return null;
    }
    $start = (int) $node['port_start'];
    $end = (int) $node['port_end'];
    $stmt = db()->prepare("SELECT ip_octet, nat_ports FROM instances WHERE node_id = ? AND status != 'released'");
    $stmt->execute([$nodeId]);
    $usedOctets = [];
    $usedBlocks = [];
    foreach ($stmt->fetchAll() as $r) {
        if ((int) $r['ip_octet'] > 0) {
            $usedOctets[(int) $r['ip_octet']] = true;
        }
        if (preg_match('~^(\d+)-(\d+)$~', (string) $r['nat_ports'], $m)) {
            $usedBlocks[] = [(int) $m[1], (int) $m[2]];
        }
    }
    $octet = null;
    for ($i = 2; $i <= 254; $i++) {
        if (!isset($usedOctets[$i])) {
            $octet = $i;
            break;
        }
    }
    if ($octet === null) {
        return null;
    }
    $count = (int) setting('ports_per_instance', '15');
    for ($base = $start; $base + $count - 1 <= $end; $base += $count) {
        $free = true;
        foreach ($usedBlocks as $b) {
            if ($base <= $b[1] && $b[0] <= $base + $count - 1) {
                $free = false;
                break;
            }
        }
        if ($free) {
            return ['octet' => $octet, 'port_base' => $base];
        }
    }
    return null;
}

/**
 * 开通节点实例（套餐绑定节点，全自动真实开通）。
 * 成功后实例处于 creating 状态，节点回报后转 running。
 */
function billing_create_node_instance(array $user, array $plan, string $img, string $mode, string $cycle = 'monthly'): array
{
    billing_ensure_pricing_columns();
    $uid = (int) $user['id'];
    $node = db_one('SELECT * FROM nodes WHERE id = ? AND status = 1 LIMIT 1', [$plan['node_id']]);
    if (!$node) {
        return ['ok' => false, 'msg' => '套餐绑定的节点不可用，请联系管理员。'];
    }
    if ((int) $plan['stock'] < 1) {
        return ['ok' => false, 'msg' => '该套餐暂时售罄。'];
    }
    $images = json_decode((string) $plan['images'], true);
    if (!in_array($img, (array) $images, true)) {
        return ['ok' => false, 'msg' => '所选系统不在该套餐支持范围内。'];
    }
    $slot = node_allocate_slot((int) $node['id']);
    if (!$slot) {
        return ['ok' => false, 'msg' => '节点资源已耗尽，请联系管理员。'];
    }

    $name = 'v' . substr(bin2hex(random_bytes(5)), 0, 9);
    $rootpw = bin2hex(random_bytes(6));
    $hourly = (float) $plan['hourly_price'];
    if (kyc_required_for_plan($plan) && (int) $user['kyc_status'] !== 2) {
        return ['ok' => false, 'msg' => '该套餐需要完成实名认证后才能购买，请先到「实名认证」页完成认证。'];
    }
    if ($mode === 'hourly' && $hourly <= 0) {
        return ['ok' => false, 'msg' => '该套餐不支持按小时计费，请选择包年包月。'];
    }
    $months = billing_cycle_months($cycle);
    $cycleKey = array_search($months, [1 => 'monthly', 3 => 'quarterly', 6 => 'semiannually', 12 => 'annually'], true) ?: 'monthly';

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT id, balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $fresh = $stmt->fetch();
        if (!$fresh) {
            throw new RuntimeException('用户不存在');
        }
        $balance = (float) $fresh['balance'];

        $charge = 0.0;
        $expire = null;
        if ($mode === 'monthly') {
            $charge = billing_cycle_price($plan, $cycle);
            if ($balance < $charge) {
                db()->rollBack();
                return ['ok' => false, 'msg' => '余额不足，请先充值。本次需 ' . money($charge) . ' 元。'];
            }
            $expire = date('Y-m-d H:i:s', strtotime('+' . $months . ' months'));
        } else {
            if ($balance < max(5, round($hourly * 24, 2))) {
                db()->rollBack();
                return ['ok' => false, 'msg' => '按量计费要求余额至少可运行 24 小时（约 ' . money(max(5, $hourly * 24)) . ' 元），请先充值。'];
            }
        }

        if ($charge > 0) {
            $upd = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $upd->execute([$charge, $uid]);
            $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $after = (float) $stmt->fetchColumn();
        } else {
            $after = $balance;
        }

        $dec = db()->prepare('UPDATE plans SET stock = stock - 1 WHERE id = ? AND stock > 0');
        $dec->execute([$plan['id']]);
        if ($dec->rowCount() !== 1) {
            throw new RuntimeException('套餐库存不足');
        }

        $forwarded = json_encode(['start' => $slot['port_base'], 'count' => (int) $plan['port_count']], JSON_UNESCAPED_UNICODE);
        $ins = db()->prepare("INSERT INTO instances (uid, plan_id, node_id, name, status, billing_mode, cycle, node_name, region, ip, nat_ports, ssh_port, cpu, ram_mb, disk_gb, traffic_gb, bandwidth_mbps, os, hourly_price, monthly_price, forwarded_ports, ip_octet, root_pw, last_billed_at, expire_at) VALUES (?, ?, ?, ?, 'creating', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $ins->execute([
            $uid, $plan['id'], (int) $node['id'], $name,
            $mode, $cycleKey, $plan['node_name'], $plan['region'], $node['pub_ip'],
            sprintf('%d-%d', $slot['port_base'], $slot['port_base'] + (int) $plan['port_count'] - 1), $slot['port_base'],
            $plan['cpu'], $plan['ram_mb'], $plan['disk_gb'], (int) $plan['traffic_gb'], $plan['bandwidth_mbps'],
            $img, $hourly, $monthly, $forwarded, $slot['octet'], $rootpw, $expire,
        ]);
        $instanceId = (int) db()->lastInsertId();

        /* 容器名需满足 [a-z0-9-]{3,21}：instances.name(nat-xxxxxxxx) 恰好可用 */
        $container = $name;
        $payload = [
            'name' => $container,
            'img' => $img,
            'cpu_pct' => (int) $plan['cpu'] * 100,
            'mem_mb' => (int) $plan['ram_mb'],
            'disk_gb' => (int) $plan['disk_gb'],
            'ip_octet' => (int) $slot['octet'],
            'port_base' => $slot['port_base'],
            'port_count' => (int) $plan['port_count'],
            'rootpw' => $rootpw,
        ];
        node_enqueue((int) $node['id'], 'create', $payload);

        $orderTitle = sprintf('%s（%s）', $plan['name'], $name);
        $oins = db()->prepare('INSERT INTO orders (order_no, uid, instance_id, type, title, months, amount, status, created_at, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $oins->execute([random_order_no(), $uid, $instanceId, $mode === 'monthly' ? 'new' : 'new_hourly', $orderTitle, $mode === 'monthly' ? $months : 0, $charge, 'paid']);

        if ($charge > 0) {
            $tins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'monthly', ?, ?, ?, NOW())");
            $tins->execute([$uid, -$charge, $after, $orderTitle]);
        }

        db()->commit();
        return ['ok' => true, 'msg' => '实例开通成功，容器正在自动部署（约 1-2 分钟）。', 'instance_id' => $instanceId];
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return ['ok' => false, 'msg' => '开通失败：' . $e->getMessage()];
    }
}

/* ---------- 节点实例生命周期任务 ---------- */

function node_lifecycle_on_expire(array $inst): void
{
    /* 到期：自动暂停容器 */
    if ((int) $inst['node_id'] > 0) {
        node_enqueue((int) $inst['node_id'], 'stop', ['name' => $inst['name']]);
    }
}

function node_lifecycle_on_renew(array $inst): void
{
    if ((int) $inst['node_id'] > 0 && $inst['status'] !== 'released') {
        node_enqueue((int) $inst['node_id'], 'start', ['name' => $inst['name']]);
    }
}

function node_lifecycle_on_delete(array $inst): void
{
    if ((int) $inst['node_id'] > 0) {
        node_enqueue((int) $inst['node_id'], 'delete', ['name' => $inst['name'], 'ip_octet' => (int) $inst['ip_octet']]);
    }
}

/**
 * 续费（包年包月）：从余额扣对应月数费用，延长到期时间。
 */
function billing_renew_instance(array $user, array $inst, string $cycle = 'monthly'): array
{
    if ($inst['billing_mode'] !== 'monthly') {
        return ['ok' => false, 'msg' => '按量计费实例无需续费，保持余额充足即可。'];
    }
    if ($inst['status'] === 'released') {
        return ['ok' => false, 'msg' => '实例已释放，无法续费。'];
    }
    billing_ensure_pricing_columns();
    $months = billing_cycle_months($cycle);
    $charge = null;
    if ((int) $inst['plan_id'] > 0) {
        $pstmt = db()->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
        $pstmt->execute([(int) $inst['plan_id']]);
        $plan = $pstmt->fetch();
        if ($plan) {
            $charge = billing_cycle_price($plan, $cycle);
        }
    }
    if ($charge === null) {
        $charge = round((float) $inst['monthly_price'] * $months, 2);
    }
    $uid = (int) $user['id'];

    db()->beginTransaction();
    try {
        $stmt = db()->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$uid]);
        $balance = (float) $stmt->fetchColumn();
        if ($balance < $charge) {
            db()->rollBack();
            return ['ok' => false, 'msg' => '余额不足，请先充值。本次需 ' . money($charge) . ' 元。'];
        }
        $upd = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
        $upd->execute([$charge, $uid]);
        $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $after = (float) $stmt->fetchColumn();

        $base = $inst['expire_at'] && strtotime((string) $inst['expire_at']) > time() ? strtotime((string) $inst['expire_at']) : time();
        $newExpire = date('Y-m-d H:i:s', strtotime('+' . $months . ' months', $base));
        $upd = db()->prepare("UPDATE instances SET expire_at = ?, status = 'running', stopped_at = NULL, cycle = ? WHERE id = ?");
        $upd->execute([$newExpire, $cycle, $inst['id']]);
        node_lifecycle_on_renew($inst);

        $oins = db()->prepare("INSERT INTO orders (order_no, uid, instance_id, type, title, months, amount, status, created_at, paid_at) VALUES (?, ?, ?, 'renew', ?, ?, ?, 'paid', NOW(), NOW())");
        $oins->execute([random_order_no(), $uid, $inst['id'], sprintf('实例 %s 续费 %d 个月', $inst['name'], $months), $months, $charge]);

        $tins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'monthly', ?, ?, ?, NOW())");
        $tins->execute([$uid, -$charge, $after, sprintf('实例 %s 续费 %d 个月', $inst['name'], $months)]);

        db()->commit();
        return ['ok' => true, 'msg' => '续费成功，新到期时间 ' . $newExpire];
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return ['ok' => false, 'msg' => '续费失败：' . $e->getMessage()];
    }
}

/**
 * 钱包入账（充值确认 / 管理员调整），写流水。
 */
function billing_credit(int $uid, float $amount, string $type, string $remark): void
{
    db()->beginTransaction();
    try {
        $upd = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
        $upd->execute([$amount, $uid]);
        $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $after = (float) $stmt->fetchColumn();
        $ins = db()->prepare('INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $ins->execute([$uid, $type, $amount, $after, $remark]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

/* ---------- 实例运维：重置密码 / 重装系统 / 自定义 NAT 转发 ---------- */

/** 解析实例端口段 nat_ports（形如 20000-20014），返回 [起始, 结束] */
function billing_node_ports_of(array $inst): array
{
    if (preg_match('~^(\d+)-(\d+)$~', (string) $inst['nat_ports'], $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    return [0, 0];
}

/** 重置 ROOT 密码：下发节点指令并更新记录，返回明文密码供一次性展示 */
function billing_instance_reset_password(array $inst): array
{
    if ((int) $inst['node_id'] <= 0) {
        return ['ok' => false, 'msg' => '本机模拟实例无真实系统，无法重置密码。'];
    }
    $pw = bin2hex(random_bytes(6));
    node_enqueue((int) $inst['node_id'], 'password', ['name' => $inst['name'], 'rootpw' => $pw]);
    $upd = db()->prepare('UPDATE instances SET root_pw = ? WHERE id = ?');
    $upd->execute([$pw, $inst['id']]);
    return ['ok' => true, 'msg' => '密码已重置，新 ROOT 密码：' . $pw . '（请立即保存）', 'pw' => $pw];
}

/** 重装系统：按实例当前规格下发 rebuild 指令，系统镜像可更换 */
function billing_instance_rebuild(array $inst, string $img): array
{
    if ((int) $inst['node_id'] <= 0) {
        return ['ok' => false, 'msg' => '本机模拟实例无需重装。'];
    }
    if (!preg_match('~^[a-z0-9][a-z0-9-]{0,31}$~', $img)) {
        return ['ok' => false, 'msg' => '镜像名不合法。'];
    }
    [$pb, $pe] = billing_node_ports_of($inst);
    if ($pb <= 0 || $pe < $pb) {
        return ['ok' => false, 'msg' => '实例缺少端口段信息，无法重装。'];
    }
    $pw = bin2hex(random_bytes(6));
    node_enqueue((int) $inst['node_id'], 'rebuild', [
        'name' => $inst['name'],
        'img' => $img,
        'cpu_pct' => (int) $inst['cpu'] * 100,
        'mem_mb' => (int) $inst['ram_mb'],
        'disk_gb' => (int) $inst['disk_gb'],
        'ip_octet' => (int) $inst['ip_octet'],
        'port_base' => $pb,
        'port_count' => $pe - $pb + 1,
        'rootpw' => $pw,
    ]);
    try {
        $upd = db()->prepare("UPDATE instances SET os = ?, root_pw = ?, vnc_token = '', vnc_expire_at = NULL WHERE id = ?");
        $upd->execute([$img, $pw, $inst['id']]);
    } catch (Throwable $e) {
        /* vnc 列尚未创建（首次部署），只更新基础字段 */
        $upd = db()->prepare('UPDATE instances SET os = ?, root_pw = ? WHERE id = ?');
        $upd->execute([$img, $pw, $inst['id']]);
    }
    return ['ok' => true, 'msg' => '重装指令已下发，约 1-2 分钟完成；新 ROOT 密码：' . $pw, 'pw' => $pw];
}

/* ---------- 自定义 NAT 转发规则 ---------- */

function nat_rules_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db_exec("CREATE TABLE IF NOT EXISTS nc_nat_rules (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      instance_id INT UNSIGNED NOT NULL,
      port INT NOT NULL,
      to_port INT NOT NULL,
      proto VARCHAR(4) NOT NULL DEFAULT 'both',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_rule (instance_id, port)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function nat_rules_of(int $instanceId): array
{
    nat_rules_ensure_table();
    $stmt = db()->prepare('SELECT port, to_port, proto FROM nc_nat_rules WHERE instance_id = ? ORDER BY port ASC');
    $stmt->execute([$instanceId]);
    return $stmt->fetchAll();
}

/** 把当前规则全量下发节点（幂等重建该实例的 DNAT） */
function nat_rules_sync(array $inst): void
{
    [$pb, $pe] = billing_node_ports_of($inst);
    $list = [];
    foreach (nat_rules_of((int) $inst['id']) as $r) {
        $list[] = ['port' => (int) $r['port'], 'to' => (int) $r['to_port'], 'proto' => (string) $r['proto']];
    }
    node_enqueue((int) $inst['node_id'], 'nat', [
        'name' => $inst['name'],
        'ip_octet' => (int) $inst['ip_octet'],
        'port_base' => $pb,
        'port_count' => $pe - $pb + 1,
        'rules' => $list,
    ]);
}

function nat_rules_save(array $inst, int $port, int $to, string $proto): array
{
    if ((int) $inst['node_id'] <= 0) {
        return ['ok' => false, 'msg' => '本机模拟实例不支持端口转发管理。'];
    }
    [$pb, $pe] = billing_node_ports_of($inst);
    if ($pb <= 0 || $pe < $pb) {
        return ['ok' => false, 'msg' => '实例缺少端口段信息。'];
    }
    if ($port < $pb + 1 || $port > $pe) {
        return ['ok' => false, 'msg' => '端口 ' . $port . ' 不在本实例的端口段（' . $pb . '-' . $pe . '）内。'];
    }
    if ($port === $pb) {
        return ['ok' => false, 'msg' => '端口 ' . $pb . ' 已被 SSH 登录占用，不能改作转发。'];
    }
    if ($to < 1 || $to > 65535) {
        return ['ok' => false, 'msg' => '目标端口需在 1 - 65535 之间。'];
    }
    if (!in_array($proto, ['tcp', 'udp', 'both'], true)) {
        $proto = 'both';
    }
    nat_rules_ensure_table();
    $stmt = db()->prepare('SELECT id FROM nc_nat_rules WHERE instance_id = ? AND port = ?');
    $stmt->execute([(int) $inst['id'], $port]);
    if ($stmt->fetch()) {
        $upd = db()->prepare('UPDATE nc_nat_rules SET to_port = ?, proto = ? WHERE instance_id = ? AND port = ?');
        $upd->execute([$to, $proto, (int) $inst['id'], $port]);
        $msg = '端口 ' . $port . ' 已有转发规则，已更新为 → ' . $to . '（' . $proto . '）。';
    } else {
        $ins = db()->prepare('INSERT INTO nc_nat_rules (instance_id, port, to_port, proto) VALUES (?, ?, ?, ?)');
        $ins->execute([(int) $inst['id'], $port, $to, $proto]);
        $msg = '已添加转发：端口 ' . $port . ' → ' . $to . '（' . $proto . '）。';
    }
    nat_rules_sync($inst);
    return ['ok' => true, 'msg' => $msg];
}

function nat_rules_delete(array $inst, int $port): array
{
    [$pb] = billing_node_ports_of($inst);
    if ($port === $pb) {
        return ['ok' => false, 'msg' => 'SSH 端口映射不能删除。'];
    }
    nat_rules_ensure_table();
    $del = db()->prepare('DELETE FROM nc_nat_rules WHERE instance_id = ? AND port = ?');
    $del->execute([(int) $inst['id'], $port]);
    nat_rules_sync($inst);
    return ['ok' => true, 'msg' => '端口 ' . $port . ' 的转发规则已删除，恢复默认同端口转发。'];
}
