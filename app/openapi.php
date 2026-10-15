<?php
declare(strict_types=1);

/* NAT Cloud - 用户开放 API（/open/v1，Bearer Token 鉴权）
 * v1：实例列表 / 实例详情 / 开关机重启。token 在控制台「账号与安全」生成（仅存哈希）。 */

function openapi_ensure_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db_val('SELECT api_token FROM users LIMIT 1');
    } catch (Throwable $e) {
        db_exec('ALTER TABLE users ADD COLUMN api_token CHAR(64) NULL');
    }
    try {
        db_exec('ALTER TABLE users ADD UNIQUE KEY uq_api_token (api_token)');
    } catch (Throwable $e) {
        /* 索引已存在 */
    }
}

function openapi_user_by_token(string $token): ?array
{
    if (!preg_match('~^[a-f0-9]{64}$~', $token)) {
        return null;
    }
    openapi_ensure_columns();
    /* 数据库只存 token 的 SHA-256，明文仅在生成时展示一次 */
    $stmt = db()->prepare('SELECT id, username, email, balance, status FROM users WHERE api_token = ? AND status = 1 LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function openapi_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    /* 个性化 Bearer API 响应禁止任何缓存（防代理/CDN 串户泄露余额与实例信息） */
    header('Cache-Control: private, no-store');
    header('Vary: Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function openapi_fail(string $msg, int $status): void
{
    openapi_json(['ok' => false, 'error' => $msg], $status);
}

function openapi_instance_shape(array $i): array
{
    return [
        'name' => $i['name'],
        'status' => $i['status'],
        'billing_mode' => $i['billing_mode'],
        'cycle' => $i['cycle'],
        'plan_id' => $i['plan_id'] !== null ? (int) $i['plan_id'] : null,
        'node_name' => $i['node_name'],
        'region' => $i['region'],
        'ip' => $i['ip'],
        'nat_ports' => $i['nat_ports'],
        'ssh_port' => (int) $i['ssh_port'],
        'cpu' => (int) $i['cpu'],
        'ram_mb' => (int) $i['ram_mb'],
        'disk_gb' => (int) $i['disk_gb'],
        'traffic_gb' => (int) $i['traffic_gb'],
        'traffic_used_gb' => round(((int) $i['traffic_rx'] + (int) $i['traffic_tx']) / 1073741824, 2),
        'os' => $i['os'],
        'monthly_price' => (float) $i['monthly_price'],
        'hourly_price' => (float) $i['hourly_price'],
        'created_at' => $i['created_at'],
        'expire_at' => $i['expire_at'],
    ];
}

function openapi_handle(string $path, string $method): void
{
    header('X-Content-Type-Options: nosniff');
    $token = '';
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'bearer ') === 0) {
        $token = trim(substr($auth, 7));
    }
    if ($token === '') {
        openapi_fail('missing bearer token', 401);
    }
    $user = openapi_user_by_token($token);
    if (!$user) {
        openapi_fail('invalid token', 401);
    }
    $uid = (int) $user['id'];

    /* GET /open/v1/me */
    if ($path === '/open/v1/me' && $method === 'GET') {
        openapi_json(['ok' => true, 'username' => $user['username'], 'email' => $user['email'], 'balance' => (float) $user['balance']]);
    }

    /* GET /open/v1/instances */
    if ($path === '/open/v1/instances' && $method === 'GET') {
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND status != \'released\' ORDER BY id DESC');
        $stmt->execute([$uid]);
        $rows = array_map('openapi_instance_shape', $stmt->fetchAll());
        openapi_json(['ok' => true, 'total' => count($rows), 'instances' => $rows]);
    }

    /* GET /open/v1/instances/{name} */
    if (preg_match('~^/open/v1/instances/([a-z0-9][a-z0-9-]{2,20})$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND name = ? AND status != \'released\' LIMIT 1');
        $stmt->execute([$uid, $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            openapi_fail('instance not found', 404);
        }
        openapi_json(['ok' => true, 'instance' => openapi_instance_shape($inst)]);
    }

    /* POST /open/v1/instances/{name}/power  body: {"action":"start|stop|reboot"} */
    if (preg_match('~^/open/v1/instances/([a-z0-9][a-z0-9-]{2,20})/power$~', $path, $m) && $method === 'POST') {
        $raw = json_decode((string) file_get_contents('php://input'), true);
        $action = is_array($raw) ? (string) ($raw['action'] ?? '') : '';
        if (!in_array($action, ['start', 'stop', 'reboot'], true)) {
            openapi_fail('action must be start / stop / reboot', 422);
        }
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND name = ? AND status != \'released\' LIMIT 1');
        $stmt->execute([$uid, $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            openapi_fail('instance not found', 404);
        }
        $status = (string) $inst['status'];
        $apply = null;
        if ($action === 'start' && $status === 'stopped') {
            /* 与控制台开机路径一致：重置按量计费起点，避免把停机期间计入扣费 */
            $apply = "UPDATE instances SET status = 'running', stopped_at = NULL, last_billed_at = NOW() WHERE id = " . (int) $inst['id'];
        } elseif ($action === 'stop' && $status === 'running') {
            $apply = "UPDATE instances SET status = 'stopped', stopped_at = NOW() WHERE id = " . (int) $inst['id'];
        } elseif ($action === 'reboot' && $status === 'running') {
            $apply = null; /* 重启只下发节点指令，不改状态 */
        } else {
            openapi_fail("instance status '{$status}' does not allow '{$action}'", 409);
        }
        if ($apply !== null) {
            db_exec($apply);
        }
        if ((int) $inst['node_id'] > 0) {
            /* 节点脚本的动作名是 restart（reboot 需映射，否则节点收到未知动作永不执行） */
            node_enqueue((int) $inst['node_id'], $action === 'reboot' ? 'restart' : $action, ['name' => $inst['name']]);
        }
        openapi_json(['ok' => true, 'name' => $inst['name'], 'action' => $action, 'status' => $apply !== null ? ($action === 'start' ? 'running' : 'stopped') : $status]);
    }

    openapi_fail('not found', 404);
}
