<?php
declare(strict_types=1);

/* NAT Cloud - 用户中心：总览/服务/创建/钱包/账单/工单/实名/设置 */

/**
 * 工单图片上传的会话级暂存目录（强随机目录名，与其它会话隔离）。
 */
function ticket_tmp_dir(): string
{
    if (empty($_SESSION['ticket_updir']) || !preg_match('~^[a-f0-9]{32}$~', (string) $_SESSION['ticket_updir'])) {
        $_SESSION['ticket_updir'] = bin2hex(random_bytes(16));
    }
    $dir = NC_ROOT . '/storage/tmp/' . $_SESSION['ticket_updir'];
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

/**
 * 工单图片输出：仅工单所有者或管理员可读，文件名白名单 + realpath 限定目录。
 */
function ticket_file(): void
{
    $tid = (int) ($_GET['tid'] ?? 0);
    $file = (string) ($_GET['f'] ?? '');
    $user = auth_user();
    $admin = auth_admin();
    if (!$user && !$admin) {
        http_response_code(403);
        exit;
    }
    if (!preg_match('~^img_[a-f0-9]{10}\.(png|jpg|gif|webp)$~', $file) || $tid < 1) {
        http_response_code(404);
        exit;
    }
    $ticket = db_one('SELECT uid FROM tickets WHERE id = ? LIMIT 1', [$tid]);
    if (!$ticket || (!$admin && (int) $ticket['uid'] !== (int) $user['id'])) {
        http_response_code(403);
        exit;
    }
    $base = NC_ROOT . '/storage/tickets/' . $tid;
    $path = $base . '/' . $file;
    if (!is_file($path) || strpos((string) realpath($path), (string) realpath($base)) !== 0) {
        http_response_code(404);
        exit;
    }
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function dash_handle(string $path, string $method): void
{
    $user = auth_user();
    if (!$user) {
        flash('warn', '请先登录后继续。');
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }
    $uid = (int) $user['id'];

    /* ---------- 总览 ---------- */
    if ($path === '/dashboard' && $method === 'GET') {
        $stmt = db()->prepare("SELECT COUNT(*) FROM instances WHERE uid = ? AND status = 'running'");
        $stmt->execute([$uid]);
        $running = (int) $stmt->fetchColumn();
        $stmt = db()->prepare("SELECT COALESCE(SUM(hourly_price),0) FROM instances WHERE uid = ? AND status = 'running' AND billing_mode = 'hourly'");
        $stmt->execute([$uid]);
        $hourlyTotal = (float) $stmt->fetchColumn();
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND status != ? ORDER BY id DESC LIMIT 5');
        $stmt->execute([$uid, 'released']);
        $recent = $stmt->fetchAll();
        $tstmt = db()->prepare('SELECT * FROM transactions WHERE uid = ? ORDER BY id DESC LIMIT 6');
        $tstmt->execute([$uid]);
        $tx = $tstmt->fetchAll();
        $runnable = $hourlyTotal > 0 ? floor($user['balance'] / max(0.0001, $hourlyTotal)) : null;
        respond(view('dashboard/index', [
            'title' => '控制台总览',
            'running' => $running,
            'hourlyTotal' => $hourlyTotal,
            'recent' => $recent,
            'tx' => $tx,
            'runnable' => $runnable,
        ]));
    }

    /* ---------- 我的服务 ---------- */
    if ($path === '/dashboard/services' && $method === 'GET') {
        $tab = query_str('tab');
        $page = max(1, (int) query_str('page', 1));
        $per = 12;
        $tabOk = $tab === 'running' || $tab === 'stopped' || $tab === 'overdue' || $tab === 'released';
        if (!$tabOk) {
            $tab = '';
        }
        $cstmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE uid = ?');
        if ($tabOk) {
            $cstmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE uid = ? AND status = ?');
        }
        $cstmt->execute($tabOk ? [$uid, $tab] : [$uid]);
        $total = (int) $cstmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $listParams = $tabOk ? [$uid, $tab, $per, ($page - 1) * $per] : [$uid, $per, ($page - 1) * $per];
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? ORDER BY COALESCE(display_sort, 0) ASC, id DESC LIMIT ? OFFSET ?');
        if ($tabOk) {
            $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND status = ? ORDER BY COALESCE(display_sort, 0) ASC, id DESC LIMIT ? OFFSET ?');
        }
        $stmt->execute($listParams);
        $rows = $stmt->fetchAll();
        respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $rows, 'tab' => $tab, 'page' => $page, 'pages' => $pages, 'total' => $total]));
    }

    /* 任务状态轮询：busy 时页面每 8 秒探测，完成后前端自动刷新 */
    if (preg_match('~^/dashboard/services/(\d+)/busy$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT id, node_id, name FROM instances WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        $row = $stmt->fetch();
        $busyNow = false;
        if ($row && (int) $row['node_id'] > 0) {
            $tstmt = db()->prepare("SELECT COUNT(*) FROM node_tasks WHERE node_id = ? AND status IN ('pending','sent') AND action <> 'console' AND payload LIKE ?");
            $tstmt->execute([(int) $row['node_id'], '%"' . $row['name'] . '"%']);
            $busyNow = ((int) $tstmt->fetchColumn()) > 0;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['busy' => $busyNow]);
        exit;
    }

    /* 批量续费：勾选多台月付实例按同一周期续期（逐台复用单台续费的扣费/延期/开机链路） */
    if ($path === '/dashboard/services/bulk-renew' && $method === 'POST') {
        csrf_check();
        $cycle = post_str('cycle', 12);
        if (!in_array($cycle, ['monthly', 'quarterly', 'semiannually', 'annually'], true)) {
            $cycle = 'monthly';
        }
        $tab = post_str('tab', 10);
        $tabOk = $tab === 'running' || $tab === 'stopped' || $tab === 'overdue' || $tab === 'released';
        if (!$tabOk) {
            $tab = '';
        }
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
        $ids = [];
        foreach ($rawIds as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values(array_slice($ids, 0, 100)); /* 上限防刷：单次最多 100 台 */
        $truncated = count($rawIds) > 100 ? max(0, count(array_filter(array_map('intval', $rawIds), fn($v) => $v > 0))) - 100 : 0;
        $okCount = 0;
        $failMsgs = [];
        foreach ($ids as $iid) {
            $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? AND uid = ? LIMIT 1');
            $stmt->execute([$iid, $uid]);
            $inst = $stmt->fetch();
            if (!$inst) {
                $failMsgs[] = '#' . $iid . ' 不存在';
                continue;
            }
            $r = billing_renew_instance($user, $inst, $cycle);
            if ($r['ok']) {
                $okCount++;
            } else {
                $failMsgs[] = $inst['name'] . '：' . $r['msg'];
            }
        }
        if ($okCount > 0) {
            flash('ok', '批量续费完成：' . $okCount . ' 台已按「' . billing_cycle_name($cycle) . '」续期。' . ($truncated > 0 ? '另有 ' . $truncated . ' 台因单次上限（100 台）未处理，请分批操作。' : '') . ($failMsgs ? ' 未成功：' . implode('；', $failMsgs) : ''));
        } else {
            flash('err', '批量续费未成功：' . ($truncated > 0 ? '有 ' . $truncated . ' 台因单次上限（100 台）未处理，请分批操作。' : '') . ($failMsgs ? implode('；', $failMsgs) : '未勾选任何实例'));
        }
        /* 回列表（保留筛选页签，回第一页） */
        $per = 12;
        $cstmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE uid = ?');
        if ($tabOk) {
            $cstmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE uid = ? AND status = ?');
        }
        $cstmt->execute($tabOk ? [$uid, $tab] : [$uid]);
        $total = (int) $cstmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $per));
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? ORDER BY COALESCE(display_sort, 0) ASC, id DESC LIMIT ? OFFSET ?');
        if ($tabOk) {
            $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? AND status = ? ORDER BY COALESCE(display_sort, 0) ASC, id DESC LIMIT ? OFFSET ?');
        }
        $stmt->execute($tabOk ? [$uid, $tab, $per, 0] : [$uid, $per, 0]);
        $rows = $stmt->fetchAll();
        respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $rows, 'tab' => $tab, 'page' => 1, 'pages' => $pages, 'total' => $total]));
    }

    /* 服务详情 */
    if (preg_match('~^/dashboard/services/(\d+)$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        $ostmt = db()->prepare('SELECT * FROM orders WHERE uid = ? AND instance_id = ? ORDER BY id DESC LIMIT 10');
        $ostmt->execute([$uid, $inst['id']]);
        $orders = $ostmt->fetchAll();
        respond(view('dashboard/service_detail', ['title' => '实例 ' . $inst['name'], 'inst' => $inst, 'orders' => $orders]));
    }

    /* 服务操作：开关机/重启/释放/续费/重置密码/重装系统 */
    if (preg_match('~^/dashboard/services/(\d+)/action$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        $renderDetail = function (int $id) use ($uid) {
            $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? AND uid = ? LIMIT 1');
            $stmt->execute([$id, $uid]);
            $inst = $stmt->fetch();
            if (!$inst) {
                respond(view('errors/404', ['title' => '实例不存在']), 404);
            }
            $ostmt = db()->prepare('SELECT * FROM orders WHERE uid = ? AND instance_id = ? ORDER BY id DESC LIMIT 10');
            $ostmt->execute([$uid, $inst['id']]);
            $planImages = [];
            if ((int) $inst['plan_id'] > 0) {
                $p2 = db()->prepare('SELECT images FROM plans WHERE id = ? LIMIT 1');
                $p2->execute([(int) $inst['plan_id']]);
                $planImages = json_decode((string) $p2->fetchColumn(), true) ?: [];
            }
            respond(view('dashboard/service_detail', [
                'title' => '实例 ' . $inst['name'],
                'inst' => $inst,
                'orders' => $ostmt->fetchAll(),
                'planImages' => $planImages,
            ]));
        };
        $action = post_str('do', 20);
        if ($action === 'start' && $inst['status'] === 'stopped') {
            $upd = db()->prepare("UPDATE instances SET status = 'running', last_billed_at = NOW() WHERE id = ?");
            $upd->execute([$inst['id']]);
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'start', ['name' => $inst['name']]);
            }
            flash('ok', '实例已开机。');
        } elseif ($action === 'stop' && $inst['status'] === 'running') {
            $upd = db()->prepare("UPDATE instances SET status = 'stopped', stopped_at = NOW() WHERE id = ?");
            $upd->execute([$inst['id']]);
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'stop', ['name' => $inst['name']]);
            }
            flash('ok', '实例已关机，按量计费已暂停。');
        } elseif ($action === 'reboot' && $inst['status'] === 'running') {
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'restart', ['name' => $inst['name']]);
            }
            flash('ok', '重启指令已下发。');
        } elseif ($action === 'release' && $inst['status'] !== 'released') {
            node_lifecycle_on_delete($inst);
            billing_release_instance($inst);
            flash('ok', '实例已释放，端口资源已回收。');
        } elseif ($action === 'resetpw') {
            $r = billing_instance_reset_password($inst);
            flash($r['ok'] ? 'ok' : 'err', $r['msg']);
        } elseif ($action === 'console') {
            if ((int) $inst['node_id'] <= 0 || $inst['status'] !== 'running') {
                flash('err', '实例需处于运行中才能打开控制台。');
            } else {
                $token = bin2hex(random_bytes(8));
                $cport = 30000 + max(2, (int) $inst['ip_octet']);
                node_enqueue((int) $inst['node_id'], 'console', [
                    'name' => $inst['name'], 'port' => $cport, 'token' => $token, 'ttl' => 21600,
                ]);
                try {
                    /* 首次部署自愈：列已存在时静默跳过 */
                    db_exec("ALTER TABLE instances ADD COLUMN vnc_token VARCHAR(32) DEFAULT '', ADD COLUMN vnc_expire_at DATETIME NULL");
                } catch (Throwable $e) {
                    /* ignore duplicate column */
                }
                $vstmt = db()->prepare('UPDATE instances SET vnc_token = ?, vnc_expire_at = DATE_ADD(NOW(), INTERVAL 21600 SECOND) WHERE id = ?');
                $vstmt->execute([$token, (int) $inst['id']]);
                flash('ok', '控制台启动指令已下发（约 1 分钟内生效，重复点击会自动重启控制台并更换密码）。连接地址与密码已显示在本页「VNC 控制台」卡片，6 小时内有效。');
            }
        } elseif ($action === 'rebuild') {
            $img = post_str('img', 32);
            if ($img === '') {
                $img = (string) $inst['os'];
            }
            $r = billing_instance_rebuild($inst, $img);
            flash($r['ok'] ? 'ok' : 'err', $r['msg']);
        } elseif ($action === 'renew') {
            $result = billing_renew_instance($user, $inst, post_str('cycle', 12));
            flash($result['ok'] ? 'ok' : 'err', $result['msg']);
        } else {
            flash('err', '当前状态无法执行该操作。');
        }
        $renderDetail((int) $inst['id']);
    }

    /* NAT 端口转发管理（用户自设转发规则） */
    $natGuard = function (int $id) use ($uid) {
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([$id, $uid]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        return $inst;
    };
    $renderNat = function (array $inst) {
        respond(view('dashboard/service_nat', [
            'title' => 'NAT 端口转发 · ' . $inst['name'],
            'inst' => $inst,
            'rules' => nat_rules_of((int) $inst['id']),
        ]));
    };
    if (preg_match('~^/dashboard/services/(\d+)/nat$~', $path, $m) && $method === 'GET') {
        $renderNat($natGuard((int) $m[1]));
    }
    if (preg_match('~^/dashboard/services/(\d+)/nat/save$~', $path, $m) && $method === 'POST') {
        $inst = $natGuard((int) $m[1]);
        csrf_check();
        $r = nat_rules_save($inst, post_int('port', 0), post_int('to', 0), post_str('proto', 4));
        flash($r['ok'] ? 'ok' : 'err', $r['msg']);
        $renderNat($inst);
    }
    if (preg_match('~^/dashboard/services/(\d+)/nat/del$~', $path, $m) && $method === 'POST') {
        $inst = $natGuard((int) $m[1]);
        csrf_check();
        $r = nat_rules_delete($inst, post_int('port', 0));
        flash($r['ok'] ? 'ok' : 'err', $r['msg']);
        $renderNat($inst);
    }

    /* ---------- 创建实例 ---------- */
    if ($path === '/dashboard/create') {
        billing_ensure_pricing_columns();
        if ($method === 'GET') {
            $oses = ['Ubuntu 24.04', 'Ubuntu 22.04', 'Debian 12', 'AlmaLinux 9', 'Rocky Linux 9', 'Windows Server 2022', 'Docker', '宝塔面板'];
            $nodePlans = db_all('SELECT * FROM plans WHERE status = 1 AND node_id > 0 ORDER BY sort ASC, id ASC');
            foreach ($nodePlans as &$np) {
                $np['images'] = json_decode((string) $np['images'], true) ?: [];
                $np['stock_left'] = billing_plan_stock_left($np);
            }
            unset($np);
            respond(view('dashboard/create', ['title' => '创建实例', 'oses' => $oses, 'nodePlans' => $nodePlans]));
        }
        csrf_check();
        $oses = ['Ubuntu 24.04', 'Ubuntu 22.04', 'Debian 12', 'AlmaLinux 9', 'Rocky Linux 9', 'Windows Server 2022', 'Docker', '宝塔面板'];
        $oses = ['Ubuntu 24.04', 'Ubuntu 22.04', 'Debian 12', 'AlmaLinux 9', 'Rocky Linux 9', 'Windows Server 2022', 'Docker', '宝塔面板'];
        $planId = post_int('plan_id', 0);
        /* 422 统一出口：重查套餐（含库存）后重渲染创建页 */
        $createErr = function (string $msg) use ($oses) {
            flash('err', $msg);
            $nodePlans = db_all('SELECT * FROM plans WHERE status = 1 AND node_id > 0 ORDER BY sort ASC, id ASC');
            foreach ($nodePlans as &$np) {
                $np['images'] = json_decode((string) $np['images'], true) ?: [];
                $np['stock_left'] = billing_plan_stock_left($np);
            }
            unset($np);
            respond(view('dashboard/create', ['title' => '创建实例', 'oses' => $oses, 'nodePlans' => $nodePlans]), 422);
        };
        $planId = post_int('plan_id', 0);

        /* ---- 路径A：节点套餐（全自动真实开通） ---- */
        if ($planId > 0) {
            $stmt = db()->prepare('SELECT * FROM plans WHERE id = ? AND status = 1 AND node_id > 0 LIMIT 1');
            $stmt->execute([$planId]);
            $plan = $stmt->fetch();
            if (!$plan) {
                $createErr('所选套餐不可用。');
            }
            if (billing_plan_stock_left($plan) <= 0) {
                $createErr('该套餐已售罄，请选择其他套餐。');
            }
            $img = post_str('image', 20);
            $mode = post_str('mode', 10) === 'hourly' ? 'hourly' : 'monthly';
            $cycle = post_str('cycle', 12);
            if (!in_array($cycle, ['monthly', 'quarterly', 'semiannually', 'annually'], true)) {
                $cycle = 'monthly';
            }
            $result = billing_create_node_instance($user, $plan, $img, $mode, $cycle);
            if (!$result['ok']) {
                $createErr($result['msg']);
            }
            flash('ok', $result['msg']);
            $svcRows = db_all('SELECT * FROM instances WHERE uid = ? ORDER BY id DESC', [$uid]);
            respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $svcRows, 'tab' => '', 'page' => 1, 'pages' => 1, 'total' => count($svcRows)]));
        }

        /* ---- 路径B：本机自定义配置（模拟开通）——无上架本机套餐时关闭 ---- */
        if (!(bool) db_val('SELECT COUNT(*) FROM plans WHERE status = 1 AND node_id = 0')) {
            $createErr('自定义配置未开放，请从「套餐实例」下单。');
        }
        $cpu = min(16, max(1, post_int('cpu', 1)));
        $ramGb = min(16, max(1, post_int('ram_gb', 1)));
        $diskGb = min(100, max(10, post_int('disk_gb', 10)));
        $mode = post_str('mode', 10) === 'hourly' ? 'hourly' : 'monthly';
        $os = post_str('os', 40);
        $months = min(36, max(1, post_int('months', 1)));

        /* 价格与服务端设置一致，防止前端篡改 */
        $base = (float) setting('price_base', '23.00');
        $cpuP = (float) setting('price_cpu', '10.00');
        $ramP = (float) setting('price_ram', '12.00');
        $diskP = (float) setting('price_disk', '0.60');
        $monthly = round($base + max(0, $cpu - 1) * $cpuP + max(0, $ramGb - 1) * $ramP + max(0, $diskGb - 10) * $diskP, 2);
        $hourly = round($monthly / 720, 4);

        $cfg = [
            'mode' => $mode, 'cpu' => $cpu, 'ram_mb' => $ramGb * 1024, 'disk_gb' => $diskGb,
            'monthly_price' => $monthly, 'hourly_price' => $hourly,
            'os' => $os !== '' ? $os : 'Ubuntu 24.04', 'months' => $months,
            'node_name' => setting('node_name', '东京 #1'), 'region' => setting('node_region', '东京'),
        ];
        $result = billing_create_config_instance($user, $cfg);
        if (!$result['ok']) {
            $createErr($result['msg']);
        }
        flash('ok', '实例开通成功，可在「我的服务」中查看。');
        $svcRows = db_all('SELECT * FROM instances WHERE uid = ? ORDER BY id DESC', [$uid]);
        respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $svcRows, 'tab' => '', 'page' => 1, 'pages' => 1, 'total' => count($svcRows)]));
    }

    /* ---------- 钱包 ---------- */
    $wallet_channels = function () {
        $ch = codepay_enabled() ? codepay_channels() : [];
        if (alipay_ready()) {
            $ch['alipay_official'] = '支付宝（官方·扫码）';
        }
        if (wx_ready()) {
            $ch['wx_official'] = '微信支付（官方·扫码）';
        }
        if (usdt_enabled()) {
            $ch['usdt'] = 'USDT · TRC20';
        }
        return $ch;
    };
    if ($path === '/dashboard/wallet' && $method === 'GET') {
        respond(view('dashboard/wallet', [
            'title' => '钱包',
            'channels' => $wallet_channels(),
            'paid' => query_str('paid', 10) === '1',
        ]));
    }
    /* 扫码支付订单状态轮询（支付宝当面付/微信 Native 共用） */
    if (preg_match('~^/dashboard/wallet/order-status$~', $path) && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $no = trim((string) ($_GET['no'] ?? ''));
        $paid = false;
        $gone = false;
        if (preg_match('~^\d{14}[0-9A-F]{6}$~', $no)) {
            $stmt = db()->prepare("SELECT status FROM orders WHERE order_no = ? AND uid = ? AND type = 'topup' LIMIT 1");
            $stmt->execute([$no, $uid]);
            $st = (string) $stmt->fetchColumn();
            if ($st === 'paid') {
                $paid = true;
            } elseif ($st === '' || ($st !== 'pending' && $st !== 'processing')) {
                $gone = true;
            }
        } else {
            $gone = true;
        }
        echo json_encode(['paid' => $paid, 'gone' => $gone]);
        exit;
    }
    if ($path === '/dashboard/wallet/usdt-check' && $method === 'POST') {
        csrf_check();
        header('Content-Type: application/json; charset=utf-8');
        $no = post_str('order_no', 32);
        $stmt = db()->prepare("SELECT * FROM orders WHERE order_no = ? AND uid = ? AND type = 'topup' LIMIT 1");
        $stmt->execute([$no, $uid]);
        $order = $stmt->fetch();
        if (!$order) {
            echo '{"paid": false, "gone": true}';
            exit;
        }
        if ($order['status'] !== 'pending') {
            echo '{"paid": true}';
            exit;
        }
        if ((float) ($order['usdt_amount'] ?? 0) <= 0) {
            echo '{"paid": false}';
            exit;
        }
        $tx = usdt_find_payment($order);
        if ($tx !== null) {
            usdt_credit_order($order, $tx);
            echo '{"paid": true}';
            exit;
        }
        $expired = time() - strtotime((string) $order['created_at']) > 2100;
        echo json_encode(['paid' => false, 'expired' => $expired]);
        exit;
    }
    if ($path === '/dashboard/wallet/topup' && $method === 'POST') {
        csrf_check();
        $amountText = post_str('amount', 12);
        $amount = (float) $amountText;
        if ($amount < 10 || $amount > 10000) {
            flash('err', '单次充值金额需在 10 - 10000 元之间。');
            respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $wallet_channels(), 'paid' => false]), 422);
        }
        $orderNo = random_order_no();
        /* 在线支付：管理后台勾选启用的渠道才会出现；未选渠道一律拒绝 */
        $channel = post_str('pay_channel', 10);
        $channels = $wallet_channels();
        if ($channel === '' || !isset($channels[$channel])) {
            flash('err', '请选择支付方式。');
            respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 422);
        }
        if ($channel === 'usdt') {
            usdt_ensure_columns();
            $uAmount = usdt_amount_for($amount);
            if ($uAmount <= 0) {
                flash('err', 'USDT 支付暂不可用，请联系管理员。');
                respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 503);
            }
            /* 防同金额订单串单：叠加分位偏移，确保待支付订单的 USDT 金额全场唯一 */
            $offset = 0;
            while ($offset < 20) {
                $cand = round($uAmount + $offset * 0.01, 2);
                $clash = (int) db_val("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND usdt_amount = ? AND created_at > DATE_SUB(NOW(), INTERVAL 45 MINUTE)", [$cand]);
                if ($clash === 0) {
                    $uAmount = $cand;
                    break;
                }
                $offset++;
            }
            $ins = db()->prepare("INSERT INTO orders (order_no, uid, type, title, amount, status, usdt_amount, created_at) VALUES (?, ?, 'topup', '余额充值(USDT)', ?, 'pending', ?, NOW())");
            $ins->execute([$orderNo, $uid, $amount, $uAmount]);
            $stmt = db()->prepare('SELECT * FROM orders WHERE order_no = ? LIMIT 1');
            $stmt->execute([$orderNo]);
            respond(view('dashboard/pay_usdt', [
                'title' => 'USDT 支付',
                'order' => $stmt->fetch(),
                'addr' => usdt_address(),
            ]));
        }
        if ($channel === 'alipay_official' || $channel === 'wx_official') {
            $siteBase = codepay_site_base();
            if ($siteBase === '') {
                flash('err', '支付暂不可用：站点地址未配置。');
                respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 503);
            }
            $ins = db()->prepare("INSERT INTO orders (order_no, uid, type, title, amount, status, created_at) VALUES (?, ?, 'topup', '余额充值', ?, 'pending', NOW())");
            $ins->execute([$orderNo, $uid, $amount]);
            if ($channel === 'alipay_official') {
                $r = alipay_precreate($orderNo, $amount, $siteBase . '/pay/alipay/notify');
                if (isset($r['err'])) {
                    $done = db()->prepare("UPDATE orders SET status = 'failed' WHERE order_no = ?");
                    $done->execute([$orderNo]);
                    flash('err', $r['err']);
                    respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 502);
                }
                $qr = $r['qr'];
                $payName = '支付宝（官方）';
            } else {
                $r = wx_native_order($orderNo, $amount, $siteBase . '/pay/wx/notify');
                if (isset($r['err'])) {
                    $done = db()->prepare("UPDATE orders SET status = 'failed' WHERE order_no = ?");
                    $done->execute([$orderNo]);
                    flash('err', $r['err']);
                    respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 502);
                }
                $qr = $r['code_url'];
                $payName = '微信支付（官方）';
            }
            respond(view('dashboard/pay_qrcode', [
                'title' => '扫码支付',
                'orderNo' => $orderNo,
                'amount' => $amount,
                'qr' => $qr,
                'payName' => $payName,
            ]));
        }

        $endpoint = codepay_endpoint('/submit.php');
        $siteBase = codepay_site_base();
        if ($endpoint === '' || $siteBase === '') {
            flash('err', '在线支付暂不可用，请稍后再试或联系管理员。');
            respond(view('dashboard/wallet', ['title' => '钱包', 'channels' => $channels, 'paid' => false]), 503);
        }
        $ins = db()->prepare("INSERT INTO orders (order_no, uid, type, title, amount, status, created_at) VALUES (?, ?, 'topup', '余额充值', ?, 'pending', NOW())");
        $ins->execute([$orderNo, $uid, $amount]);
        $payment = codepay_build_payment($channel, $orderNo, $amount);
        respond(view('dashboard/pay_jump', ['title' => '正在跳转支付', 'endpoint' => $endpoint, 'payment' => $payment]));
    }

    /* ---------- 账单 ---------- */
    if ($path === '/dashboard/billing' && $method === 'GET') {
        $tab = query_str('tab') === 'topups' ? 'topups' : 'tx';
        $type = query_str('type', 20);
        if ($tab === 'tx') {
            if ($type === 'topup' || $type === 'hourly' || $type === 'monthly' || $type === 'adjust' || $type === 'refund') {
                $stmt = db()->prepare('SELECT * FROM transactions WHERE uid = ? AND type = ? ORDER BY id DESC LIMIT 100');
                $stmt->execute([$uid, $type]);
            } else {
                $type = '';
                $stmt = db()->prepare('SELECT * FROM transactions WHERE uid = ? ORDER BY id DESC LIMIT 100');
                $stmt->execute([$uid]);
            }
            $rows = $stmt->fetchAll();
            respond(view('dashboard/billing', ['title' => '账单明细', 'tab' => $tab, 'type' => $type, 'rows' => $rows]));
        }
        $stmt = db()->prepare("SELECT * FROM orders WHERE uid = ? AND type = 'topup' ORDER BY id DESC LIMIT 100");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
        respond(view('dashboard/billing', ['title' => '账单明细', 'tab' => $tab, 'type' => '', 'rows' => $rows]));
    }

    /* ---------- 工单 ---------- */
    if ($path === '/dashboard/tickets' && $method === 'GET') {
        $tab = query_str('tab');
        $page = max(1, (int) query_str('page', 1));
        $per = 15;
        $tabOk = $tab === 'open' || $tab === 'replied' || $tab === 'closed';
        if (!$tabOk) {
            $tab = '';
        }
        $cstmt = db()->prepare('SELECT COUNT(*) FROM tickets WHERE uid = ?');
        if ($tabOk) {
            $cstmt = db()->prepare('SELECT COUNT(*) FROM tickets WHERE uid = ? AND status = ?');
        }
        $cstmt->execute($tabOk ? [$uid, $tab] : [$uid]);
        $total = (int) $cstmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $offset = ($page - 1) * $per;
        $stmt = db()->prepare('SELECT * FROM tickets WHERE uid = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        if ($tabOk) {
            $stmt = db()->prepare('SELECT * FROM tickets WHERE uid = ? AND status = ? ORDER BY id DESC LIMIT ? OFFSET ?');
        }
        $stmt->execute($tabOk ? [$uid, $tab, $per, $offset] : [$uid, $per, $offset]);
        $rows = $stmt->fetchAll();
        respond(view('dashboard/tickets', ['title' => '工单', 'rows' => $rows, 'tab' => $tab, 'page' => $page, 'pages' => $pages, 'total' => $total]));
    }
    if ($path === '/dashboard/tickets/upload' && $method === 'POST') {
        /* 工单图片上传：会话级暂存，建单后转移到正式目录 */
        $file = $_FILES['file'] ?? null;
        if (!$file || !is_uploaded_file($file['tmp_name'])) {
            json_out(['ok' => false, 'msg' => '未收到文件'], 400);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            json_out(['ok' => false, 'msg' => '图片不能超过 5 MB'], 422);
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            json_out(['ok' => false, 'msg' => '只支持图片文件'], 422);
        }
        $ext = image_type_to_extension($info[2], false);
        if (!in_array($ext, ['png', 'jpeg', 'gif', 'webp'], true)) {
            json_out(['ok' => false, 'msg' => '只支持 PNG/JPG/GIF/WebP'], 422);
        }
        $dir = ticket_tmp_dir();
        /* 清理24小时前的遗留暂存 */
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        if (count(glob($dir . '/*') ?: []) >= 5) {
            json_out(['ok' => false, 'msg' => '最多上传 5 张图片'], 422);
        }
        $name = 'img_' . bin2hex(random_bytes(5)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target) || strpos((string) realpath($target), (string) realpath($dir)) !== 0) {
            json_out(['ok' => false, 'msg' => '保存失败'], 500);
        }
        chmod($target, 0640);
        json_out(['ok' => true, 'name' => $name]);
    }
    if ($path === '/dashboard/tickets/new') {
        if ($method === 'GET') {
            $insts = db_all('SELECT id, name FROM instances WHERE uid = ? AND status != ? ORDER BY id DESC', [$uid, 'released']);
            respond(view('dashboard/ticket_new', ['title' => '提交工单', 'old' => [], 'insts' => $insts]));
        }
        csrf_check();
        $title = post_str('title', 100);
        $category = post_str('category', 20);
        $priority = post_str('priority', 10);
        $instanceId = post_int('instance_id', 0);
        $content = post_str('content', 5000);
        if ($title === '' || $content === '') {
            flash('err', '请填写标题和问题描述。');
            respond(view('dashboard/ticket_new', ['title' => '提交工单', 'old' => ['title' => $title, 'content' => $content]]), 422);
        }
        if (!in_array($category, ['billing', 'instance', 'network', 'other'], true)) {
            $category = 'other';
        }
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }
        if ($instanceId > 0) {
            $stmt = db()->prepare('SELECT id FROM instances WHERE id = ? AND uid = ? LIMIT 1');
            $stmt->execute([$instanceId, $uid]);
            if (!$stmt->fetch()) {
                $instanceId = 0;
            }
        } else {
            $instanceId = 0;
        }
        $ins = db()->prepare("INSERT INTO tickets (uid, title, category, priority, instance_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'open', NOW(), NOW())");
        $ins->execute([$uid, $title, $category, $priority, $instanceId ?: null]);
        $ticketId = (int) db()->lastInsertId();

        /* 转移会话暂存图片到正式工单目录 */
        $tmpDir = NC_ROOT . '/storage/tmp/' . sha1(session_id());
        $dstDir = NC_ROOT . '/storage/tickets/' . $ticketId;
        $images = json_decode(post_str('images', 1000), true);
        if (is_array($images) && $images && is_dir($tmpDir)) {
            if (!is_dir($dstDir)) {
                mkdir($dstDir, 0700, true);
            }
            $saved = [];
            foreach ($images as $imgName) {
                if (is_string($imgName) && preg_match('~^img_[a-f0-9]{10}\.(png|jpg|gif|webp)$~', $imgName) && is_file($tmpDir . '/' . $imgName)) {
                    rename($tmpDir . '/' . $imgName, $dstDir . '/' . $imgName);
                    $saved[] = $imgName;
                }
            }
            if ($saved) {
                $upd = db()->prepare('UPDATE tickets SET images = ? WHERE id = ?');
                $upd->execute([json_encode($saved), $ticketId]);
            }
        }

        $rins = db()->prepare("INSERT INTO ticket_replies (ticket_id, uid, is_admin, content, created_at) VALUES (?, ?, 0, ?, NOW())");
        $rins->execute([$ticketId, $uid, $content]);
        flash('ok', '工单已提交，我们会尽快回复。');
        $rows = db_all('SELECT * FROM tickets WHERE uid = ? ORDER BY id DESC', [$uid]);
        respond(view('dashboard/tickets', ['title' => '工单', 'rows' => $rows, 'tab' => '', 'page' => 1, 'pages' => 1, 'total' => count($rows)]));
    }
    if (preg_match('~^/dashboard/tickets/(\d+)$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            respond(view('errors/404', ['title' => '工单不存在']), 404);
        }
        $rstmt = db()->prepare('SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON u.id = r.uid WHERE r.ticket_id = ? ORDER BY r.id ASC');
        $rstmt->execute([$ticket['id']]);
        $replies = $rstmt->fetchAll();
        respond(view('dashboard/ticket_detail', ['title' => '工单 #' . $ticket['id'], 'ticket' => $ticket, 'replies' => $replies]));
    }
    if (preg_match('~^/dashboard/tickets/(\d+)/reply$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        $ticket = $stmt->fetch();
        if (!$ticket || $ticket['status'] === 'closed') {
            flash('err', '工单不存在或已关闭。');
            $rows = db_all('SELECT * FROM tickets WHERE uid = ? ORDER BY id DESC', [$uid]);
            respond(view('dashboard/tickets', ['title' => '工单', 'rows' => $rows, 'tab' => '', 'page' => 1, 'pages' => 1, 'total' => count($rows)]), 404);
        }
        $content = post_str('content', 3000);
        if ($content !== '') {
            $ins = db()->prepare("INSERT INTO ticket_replies (ticket_id, uid, is_admin, content, created_at) VALUES (?, ?, 0, ?, NOW())");
            $ins->execute([$ticket['id'], $uid, $content]);
            $upd = db()->prepare("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = ?");
            $upd->execute([$ticket['id']]);
        }
        $rstmt = db()->prepare('SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON u.id = r.uid WHERE r.ticket_id = ? ORDER BY r.id ASC');
        $rstmt->execute([$ticket['id']]);
        respond(view('dashboard/ticket_detail', ['title' => '工单 #' . $ticket['id'], 'ticket' => $ticket, 'replies' => $rstmt->fetchAll()]));
    }
    if (preg_match('~^/dashboard/tickets/(\d+)/close$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT id FROM tickets WHERE id = ? AND uid = ? LIMIT 1');
        $stmt->execute([(int) $m[1], $uid]);
        if ($stmt->fetch()) {
            $upd = db()->prepare("UPDATE tickets SET status = 'closed', updated_at = NOW() WHERE id = ?");
            $upd->execute([(int) $m[1]]);
            flash('ok', '工单已关闭。');
        }
        $rows = db_all('SELECT * FROM tickets WHERE uid = ? ORDER BY id DESC', [$uid]);
        respond(view('dashboard/tickets', ['title' => '工单', 'rows' => $rows, 'tab' => '', 'page' => 1, 'pages' => 1, 'total' => count($rows)]));
    }

    /* ---------- 实名认证（姓名+身份证 → 接口人脸核身 → 自动通过） ---------- */
    if ($path === '/dashboard/kyc') {
        kyc_ensure_columns();
        if ($method === 'GET') {
            $stmt = db()->prepare('SELECT * FROM kyc WHERE uid = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$uid]);
            $kyc = $stmt->fetch();
            $certUrl = is_array($kyc) ? (string) ($kyc['cert_url'] ?? '') : '';
            respond(view('dashboard/kyc', ['title' => '实名认证', 'kyc' => $kyc, 'certUrl' => $certUrl]));
        }
        $kycView = function (int $status = 200) use ($uid) {
            $stmt = db()->prepare('SELECT * FROM kyc WHERE uid = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$uid]);
            respond(view('dashboard/kyc', ['title' => '实名认证', 'kyc' => $stmt->fetch()]), $status);
        };
        csrf_check();
        $stmt = db()->prepare('SELECT kyc_status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        if ((int) $stmt->fetchColumn() === 2) {
            flash('info', '你已完成实名认证，无需重复提交。');
            $kycView();
        }
        $realName = post_str('real_name', 30);
        $idCard = post_str('id_card', 18);
        if ($realName === '' || !preg_match('~^\d{17}[\dXx]$~', strtoupper($idCard))) {
            flash('err', '请填写真实的姓名与 18 位身份证号。');
            $kycView(422);
        }
        $idCard = strtoupper($idCard);
        $r = kyc_start_verify($realName, $idCard);
        if (!$r['ok']) {
            flash('err', $r['msg']);
            $kycView(502);
        }
        kyc_ensure_columns();
        $ins = db()->prepare("INSERT INTO kyc (uid, real_name, id_card, certify_id, cert_url, provider, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $ins->execute([$uid, secret_encrypt($realName), secret_encrypt($idCard), $r['certify_id'], $r['cert_url'], kyc_config()['provider']]);
        $upd = db()->prepare('UPDATE users SET kyc_status = 1 WHERE id = ? AND kyc_status = 0');
        $upd->execute([$uid]);
        flash('ok', '信息已提交，请在新打开的页面完成人脸认证，完成后本页会自动确认。');
        $kycView();
    }
    /* 实名状态查询/确认（人脸完成后自动通过） */
    if ($path === '/dashboard/kyc/check' && $method === 'POST') {
        csrf_check();
        header('Content-Type: application/json; charset=utf-8');
        $stmt = db()->prepare('SELECT * FROM kyc WHERE uid = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$uid]);
        $kyc = $stmt->fetch();
        if (!$kyc) {
            echo json_encode(['state' => 'none']);
            exit;
        }
        if ($kyc['status'] === 'passed') {
            echo json_encode(['state' => 'passed']);
            exit;
        }
        $r = kyc_check_verify((string) $kyc['provider'], (string) $kyc['certify_id']);
        if ($r['state'] === 'passed') {
            db_exec("UPDATE kyc SET status = 'passed', passed_at = NOW() WHERE id = ?", [$kyc['id']]);
            db_exec('UPDATE users SET kyc_status = 2 WHERE id = ? AND kyc_status < 2', [$uid]);
        }
        echo json_encode(['state' => $r['state'], 'msg' => $r['msg'] ?? '']);
        exit;
    }

    /* ---------- 设置 ---------- */
    if ($path === '/dashboard/settings' && $method === 'GET') {
        $keys = db_all('SELECT id, name, public_key, created_at FROM ssh_keys WHERE uid = ? ORDER BY id DESC', [$uid]);
        $sessions = auth_sessions($uid, 0);
        respond(view('dashboard/settings', ['title' => '设置', 'keys' => $keys, 'sessions' => $sessions]));
    }
    $settingsView = function () use ($uid) {
        $keys = db_all('SELECT id, name, public_key, created_at FROM ssh_keys WHERE uid = ? ORDER BY id DESC', [$uid]);
        $sessions = auth_sessions($uid, 0);
        respond(view('dashboard/settings', ['title' => '设置', 'keys' => $keys, 'sessions' => $sessions]));
    };
    if ($path === '/dashboard/settings/username' && $method === 'POST') {
        csrf_check();
        $newName = post_str('username', 32);
        if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_]{2,31}$~', $newName)) {
            flash('err', '用户名格式不正确。');
            $settingsView();
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $stmt->execute([$newName, $uid]);
        if ($stmt->fetch()) {
            flash('err', '该用户名已被占用。');
            $settingsView();
        }
        $upd = db()->prepare('UPDATE users SET username = ? WHERE id = ?');
        $upd->execute([$newName, $uid]);
        flash('ok', '用户名已更新。');
        $settingsView();
    }
    if ($path === '/dashboard/settings/password' && $method === 'POST') {
        csrf_check();
        $oldPw = isset($_POST['old_password']) && is_string($_POST['old_password']) ? $_POST['old_password'] : '';
        $newPw = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $newPw2 = isset($_POST['new_password2']) && is_string($_POST['new_password2']) ? $_POST['new_password2'] : '';
        if (!password_verify($oldPw, (string) $user['password_hash'])) {
            flash('err', '当前密码不正确。');
            $settingsView();
        }
        if (strlen($newPw) < 8 || $newPw !== $newPw2) {
            flash('err', '新密码至少 8 位，且两次输入一致。');
            $settingsView();
        }
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($newPw, PASSWORD_DEFAULT), $uid]);
        auth_logout_all($uid, 0);
        auth_issue_token($uid, 0, false);
        flash('ok', '密码已修改，其他设备已被安全下线。');
        $settingsView();
    }
    if ($path === '/dashboard/settings/logout_all' && $method === 'POST') {
        csrf_check();
        auth_logout_all($uid, 0);
        auth_logout(NC_AUTH_COOKIE, 0);
        flash('ok', '已退出所有设备。');
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }
    if (preg_match('~^/dashboard/settings/sessions/(\d+)/kill$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('DELETE FROM login_tokens WHERE id = ? AND user_id = ? AND is_admin = 0');
        $stmt->execute([(int) $m[1], $uid]);
        flash('ok', '该设备已下线。');
        $settingsView();
    }
    if ($path === '/dashboard/settings/sshkeys/add' && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT COUNT(*) FROM ssh_keys WHERE uid = ?');
        $stmt->execute([$uid]);
        if ((int) $stmt->fetchColumn() >= 20) {
            flash('err', '最多保存 20 把公钥。');
            $settingsView();
        }
        $keyName = post_str('key_name', 40);
        $pub = post_str('public_key', 1200);
        if ($keyName === '' || (strpos($pub, 'ssh-') !== 0 && strpos($pub, 'ecdsa-') !== 0 && strpos($pub, 'sk-') !== 0)) {
            flash('err', '请填写名称和有效的 SSH 公钥（以 ssh-rsa / ssh-ed25519 等开头）。');
            $settingsView();
        }
        $ins = db()->prepare('INSERT INTO ssh_keys (uid, name, public_key, created_at) VALUES (?, ?, ?, NOW())');
        $ins->execute([$uid, $keyName, $pub]);
        flash('ok', '公钥已保存。');
        $settingsView();
    }
    if (preg_match('~^/dashboard/settings/sshkeys/(\d+)/del$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('DELETE FROM ssh_keys WHERE id = ? AND uid = ?');
        $stmt->execute([(int) $m[1], $uid]);
        flash('ok', '公钥已删除。');
        $settingsView();
    }

    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
