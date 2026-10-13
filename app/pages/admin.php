<?php
declare(strict_types=1);

/* NAT Cloud - 管理后端 */

function admin_handle(string $path, string $method): void
{
    /* ---------- 仪表盘 ---------- */
    /* 仪表盘概览片段：每页 5 条，区块内独立翻页 */
    $dashUsersFragment = function (int $page) {
        $page = max(1, $page);
        $per = 5;
        $total = (int) db_val('SELECT COUNT(*) FROM users');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT id, username, email, balance, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr><td class="mono">' . (int) $r['id'] . '</td>'
                . '<td><a href="/admin/users/' . (int) $r['id'] . '">' . e($r['username']) . '</a></td>'
                . '<td class="mono">' . e($r['email']) . '</td>'
                . '<td>¥' . e(money($r['balance'])) . '</td>'
                . '<td class="mono">' . e(dt($r['created_at'])) . '</td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="5"><div class="empty" style="padding:18px;">暂无数据</div></td></tr>';
        }
        $links = '';
        if ($pages > 1) {
            $links .= $page > 1 ? '<a class="pager-btn" data-page="' . ($page - 1) . '">‹</a>' : '<span class="pager-btn off">‹</span>';
            for ($i = 1; $i <= $pages; $i++) {
                $links .= $i === $page ? '<span class="pager-btn on">' . $i . '</span>' : '<a class="pager-btn" data-page="' . $i . '">' . $i . '</a>';
            }
            $links .= $page < $pages ? '<a class="pager-btn" data-page="' . ($page + 1) . '">›</a>' : '<span class="pager-btn off">›</span>';
        }
        return [
            'html' => $body,
            'pager' => '<span class="pager-info">共 ' . $total . ' 条</span><span class="pager-links">' . $links . '</span>',
        ];
    };
    $dashOrdersFragment = function (int $page) {
        $page = max(1, $page);
        $per = 5;
        $total = (int) db_val('SELECT COUNT(*) FROM orders');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT o.*, u.username FROM orders o LEFT JOIN users u ON u.id = o.uid ORDER BY o.id DESC LIMIT ? OFFSET ?');
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        $body = '';
        foreach ($rows as $o) {
            $body .= '<tr><td class="mono">' . e(mb_substr((string) $o['order_no'], -10)) . '</td>'
                . '<td>' . e($o['username'] ?? '-') . '</td>'
                . '<td class="wrap">' . e($o['title']) . '</td>'
                . '<td>¥' . e(money($o['amount'])) . '</td>'
                . '<td><span class="tag ' . e($o['status']) . '">' . e($o['status']) . '</span></td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="5"><div class="empty" style="padding:18px;">暂无数据</div></td></tr>';
        }
        $links = '';
        if ($pages > 1) {
            $links .= $page > 1 ? '<a class="pager-btn" data-page="' . ($page - 1) . '">‹</a>' : '<span class="pager-btn off">‹</span>';
            for ($i = 1; $i <= $pages; $i++) {
                $links .= $i === $page ? '<span class="pager-btn on">' . $i . '</span>' : '<a class="pager-btn" data-page="' . $i . '">' . $i . '</a>';
            }
            $links .= $page < $pages ? '<a class="pager-btn" data-page="' . ($page + 1) . '">›</a>' : '<span class="pager-btn off">›</span>';
        }
        return [
            'html' => $body,
            'pager' => '<span class="pager-info">共 ' . $total . ' 条</span><span class="pager-links">' . $links . '</span>',
        ];
    };
    $dashView = function () use ($dashUsersFragment, $dashOrdersFragment) {
        $usersTotal = (int) db_val('SELECT COUNT(*) FROM users');
        $usersToday = (int) db_val('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()');
        $instRun = (int) db_val("SELECT COUNT(*) FROM instances WHERE status = 'running'");
        $instTotal = (int) db_val('SELECT COUNT(*) FROM instances WHERE status != ?', ['released']);
        $balanceSum = (float) db_val('SELECT COALESCE(SUM(balance),0) FROM users');
        $incomeToday = (float) db_val("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'paid' AND paid_at >= CURDATE()");
        $pendingTopups = (int) db_val("SELECT COUNT(*) FROM orders WHERE type = 'topup' AND status = 'pending'");
        $openTickets = (int) db_val("SELECT COUNT(*) FROM tickets WHERE status != 'closed'");
        $pendingKyc = (int) db_val("SELECT COUNT(*) FROM kyc WHERE status = 'pending'");
        $recentUsers = $dashUsersFragment(1);
        $recentOrders = $dashOrdersFragment(1);
        respond(view('admin/dash', [
            'title' => '管理后台',
            'usersTotal' => $usersTotal, 'usersToday' => $usersToday,
            'instRun' => $instRun, 'instTotal' => $instTotal,
            'balanceSum' => $balanceSum, 'incomeToday' => $incomeToday,
            'pendingTopups' => $pendingTopups, 'openTickets' => $openTickets,
            'pendingKyc' => $pendingKyc,
            'recentUsers' => $recentUsers, 'recentOrders' => $recentOrders,
        ]));
    };
    /* ---------- 管理员登录 ---------- */
    if ($path === '/admin/login') {
        if ($method === 'GET') {
            if (auth_admin()) {
                $dashView();
            }
            respond(view('admin/login', ['title' => '管理员登录', 'old' => []]));
        }
        csrf_check();
        if (!captcha_check()) {
            flash('err', '图形验证码不正确。');
            respond(view('admin/login', ['title' => '管理员登录', 'old' => $old ?? []]), 422);
        }
        $account = post_str('account', 60);
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $old = ['account' => $account];
        if ($account === '' || $password === '') {
            flash('err', '请输入账号和密码。');
            respond(view('admin/login', ['title' => '管理员登录', 'old' => $old]), 422);
        }
        if (auth_recent_failures('admin:' . $account, client_ip()) >= 8) {
            flash('err', '失败次数过多，请 10 分钟后再试。');
            respond(view('admin/login', ['title' => '管理员登录', 'old' => $old]), 429);
        }
        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$account]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            auth_record_failure('admin:' . $account, client_ip());
            flash('err', '账号或密码不正确。');
            respond(view('admin/login', ['title' => '管理员登录', 'old' => $old]), 401);
        }
        auth_clear_failures('admin:' . $account);
        auth_issue_token((int) $admin['id'], 1, true);
        flash('ok', '欢迎回来，' . $admin['username']);
        $dashView();
    }
    if ($path === '/admin/logout' && $method === 'POST') {
        csrf_check();
        auth_logout(NC_ADMIN_COOKIE, 1);
        flash('ok', '已退出管理后台。');
        respond(view('admin/login', ['title' => '管理员登录', 'old' => []]));
    }

    $admin = auth_admin();
    if (!$admin) {
        flash('warn', '请先登录管理后台。');
        respond(view('admin/login', ['title' => '管理员登录', 'old' => []]));
    }
    $adminId = (int) $admin['id'];

    /* 仪表盘 AJAX 片段（需管理员身份） */
    if ($path === '/admin/dash/users' && $method === 'GET') {
        json_out($dashUsersFragment(max(1, (int) query_str('page', 1))));
    }
    if ($path === '/admin/dash/orders' && $method === 'GET') {
        json_out($dashOrdersFragment(max(1, (int) query_str('page', 1))));
    }

    /* ---------- 管理员账号设置（用户名与密码一次保存） ---------- */
    if ($path === '/admin/profile' && $method === 'GET') {
        respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]));
    }
    if ($path === '/admin/profile' && $method === 'POST') {
        csrf_check();
        $name = post_str('username', 32);
        if (!preg_match('~^[a-zA-Z0-9_]{3,32}$~', $name)) {
            flash('err', '用户名需为 3-32 位字母 / 数字 / 下划线。');
            respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 422);
        }
        $stmt = db()->prepare('SELECT id FROM admins WHERE username = ? AND id != ? LIMIT 1');
        $stmt->execute([$name, $adminId]);
        if ($stmt->fetch()) {
            flash('err', '该用户名已被使用。');
            respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 409);
        }
        $oldpw = isset($_POST['old_password']) && is_string($_POST['old_password']) ? $_POST['old_password'] : '';
        $newpw = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $newpw2 = isset($_POST['new_password2']) && is_string($_POST['new_password2']) ? $_POST['new_password2'] : '';
        $changePw = $newpw !== '';
        if ($changePw) {
            $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute([$adminId]);
            $hash = (string) $stmt->fetchColumn();
            if ($oldpw === '' || !password_verify($oldpw, $hash)) {
                flash('err', '当前密码不正确。');
                respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 401);
            }
            if (strlen($newpw) < 8 || strlen($newpw) > 72) {
                flash('err', '新密码长度需为 8-72 位。');
                respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 422);
            }
            if ($newpw !== $newpw2) {
                flash('err', '两次输入的新密码不一致。');
                respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 422);
            }
            if (password_verify($newpw, $hash)) {
                flash('err', '新密码不能与当前密码相同。');
                respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $admin['username']]), 422);
            }
        }
        $upd = db()->prepare('UPDATE admins SET username = ? WHERE id = ?');
        $upd->execute([$name, $adminId]);
        if ($changePw) {
            $upd = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($newpw, PASSWORD_DEFAULT), $adminId]);
            auth_logout_all($adminId, 1);
            flash('ok', '账号与密码已更新，请使用新用户名和新密码重新登录。');
            respond(view('admin/login', ['title' => '管理员登录', 'old' => []]));
        }
        flash('ok', '账号设置已保存。');
        respond(view('admin/profile', ['title' => '账号设置', 'adminName' => $name]));
    }

    if ($path === '/admin' && $method === 'GET') {
        $dashView();
    }

    /* ---------- 用户管理 ---------- */
    if ($path === '/admin/users' && $method === 'GET') {
        $search = query_str('q', 60);
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE username LIKE ? OR email LIKE ? OR id = ?';
            $like = '%' . $search . '%';
            $bind = [$like, $like, $search];
        }
        $total = (int) db_val("SELECT COUNT(*) FROM users $where", $bind);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $bind[] = $per;
        $bind[] = ($page - 1) * $per;
        $stmt = db()->prepare("SELECT * FROM users $where ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        respond(view('admin/users', [
            'title' => '用户管理', 'rows' => $rows, 'search' => $search,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    $usersView = function (int $status = 200) {
        $total = (int) db_val('SELECT COUNT(*) FROM users');
        $pages = max(1, (int) ceil($total / 20));
        $rows = db_all('SELECT * FROM users ORDER BY id DESC LIMIT 20');
        respond(view('admin/users', [
            'title' => '用户管理', 'rows' => $rows, 'search' => '',
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if (preg_match('~^/admin/users/(\d+)$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $u = $stmt->fetch();
        if (!$u) {
            respond(view('errors/404', ['title' => '用户不存在']), 404);
        }
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ? ORDER BY id DESC');
        $stmt->execute([$u['id']]);
        $insts = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT * FROM transactions WHERE uid = ? ORDER BY id DESC LIMIT 30');
        $stmt->execute([$u['id']]);
        $tx = $stmt->fetchAll();
        respond(view('admin/user_detail', ['title' => '用户 ' . $u['username'], 'u' => $u, 'insts' => $insts, 'tx' => $tx]));
    }
    if (preg_match('~^/admin/users/(\d+)/balance$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $u = $stmt->fetch();
        if (!$u) {
            respond(view('errors/404', ['title' => '用户不存在']), 404);
        }
        $amount = (float) post_str('amount', 14);
        $remark = post_str('remark', 100);
        if ($amount == 0.0) {
            flash('err', '调整金额不能为 0。');
            $usersView(422);
        }
        billing_credit((int) $u['id'], $amount, 'adjust', $remark !== '' ? $remark : '管理员人工调整');
        flash('ok', sprintf('已为 %s %s %.2f 元。', $u['username'], $amount > 0 ? '增加' : '扣减', abs($amount)));
        $usersView();
    }
    if (preg_match('~^/admin/users/(\d+)/status$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $target = (int) $m[1];
        if ($target === 1 && false) { /* 占位：无特殊保护用户 */ }
        $stmt = db()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$target]);
        $cur = $stmt->fetchColumn();
        if ($cur !== false) {
            $newStatus = ((int) $cur) === 1 ? 0 : 1;
            $upd = db()->prepare('UPDATE users SET status = ? WHERE id = ?');
            $upd->execute([$newStatus, $target]);
            if ($newStatus === 0) {
                $del = db()->prepare('DELETE FROM login_tokens WHERE user_id = ? AND is_admin = 0');
                $del->execute([$target]);
            }
            flash('ok', '用户状态已更新。');
        }
        $usersView();
    }
    if (preg_match('~^/admin/users/(\d+)/resetpw$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $newPw = bin2hex(random_bytes(6));
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($newPw, PASSWORD_DEFAULT), (int) $m[1]]);
        $del = db()->prepare('DELETE FROM login_tokens WHERE user_id = ? AND is_admin = 0');
        $del->execute([(int) $m[1]]);
        flash('ok', '密码已重置为：' . $newPw . '（请立即告知用户并提醒修改）。');
        $usersView();
    }

    if (preg_match('~^/admin/users/(\d+)/edit$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $target = (int) $m[1];
        $newName = post_str('username', 32);
        $newEmail = post_str('email', 120);
        if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_]{2,31}$~', $newName) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash('err', '用户名或邮箱格式不正确。');
            $usersView(422);
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1');
        $stmt->execute([$newName, $newEmail, $target]);
        if ($stmt->fetch()) {
            flash('err', '用户名或邮箱已被其他账号占用。');
            $rows = db_all('SELECT * FROM users ORDER BY id DESC LIMIT 100');
            respond(view('admin/users', ['title' => '用户管理', 'rows' => $rows, 'search' => '']), 409);
        }
        $upd = db()->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
        $upd->execute([$newName, $newEmail, $target]);
        flash('ok', '用户资料已更新。');
        $usersView();
    }
    if (preg_match('~^/admin/users/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $target = (int) $m[1];
        $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$target]);
        $u = $stmt->fetch();
        if (!$u) {
            respond(view('errors/404', ['title' => '用户不存在']), 404);
        }
        /* 名下实例：节点实例入队销毁，本地实例直接删 */
        $stmt = db()->prepare('SELECT * FROM instances WHERE uid = ?');
        $stmt->execute([$target]);
        foreach ($stmt->fetchAll() as $inst) {
            if ((int) $inst['node_id'] > 0 && $inst['status'] !== 'released') {
                node_lifecycle_on_delete($inst);
            }
        }
        db_exec('DELETE FROM instances WHERE uid = ?', [$target]);
        db_exec('DELETE FROM ticket_replies WHERE ticket_id IN (SELECT id FROM tickets WHERE uid = ?)', [$target]);
        db_exec('DELETE FROM tickets WHERE uid = ?', [$target]);
        db_exec('DELETE FROM kyc WHERE uid = ?', [$target]);
        db_exec('DELETE FROM ssh_keys WHERE uid = ?', [$target]);
        db_exec('DELETE FROM transactions WHERE uid = ?', [$target]);
        db_exec('DELETE FROM orders WHERE uid = ?', [$target]);
        db_exec('DELETE FROM login_tokens WHERE user_id = ? AND is_admin = 0', [$target]);
        db_exec('DELETE FROM users WHERE id = ?', [$target]);
        flash('ok', '用户 ' . $u['username'] . ' 及其全部数据已删除。');
        $usersView();
    }

    /* ---------- 实例管理 ---------- */
    /* instances.display_sort 自愈（实例卡片拖拽排序用） */
    $instEnsureSort = function () {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            db_val('SELECT display_sort FROM instances LIMIT 1');
        } catch (Throwable $e) {
            db_exec('ALTER TABLE instances ADD COLUMN display_sort INT NOT NULL DEFAULT 0');
        }
    };
    /* 实例卡片拖拽排序：按提交顺序写 display_sort */
    if ($path === '/admin/instances/reorder' && $method === 'POST') {
        csrf_check();
        $instEnsureSort();
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? array_slice($_POST['ids'], 0, 100) : [];
        $i = 0;
        foreach ($rawIds as $v) {
            $n = (int) $v;
            if ($n <= 0) {
                continue;
            }
            $i++;
            $upd = db()->prepare('UPDATE instances SET display_sort = ? WHERE id = ?');
            $upd->execute([$i, $n]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($path === '/admin/instances' && $method === 'GET') {
        $instEnsureSort();
        $search = query_str('q', 60);
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $where = '';
        $bind = [];
        if ($search !== '') {
            $where = 'WHERE i.name LIKE ? OR u.username LIKE ? OR i.ip LIKE ?';
            $like = '%' . $search . '%';
            $bind = [$like, $like, $like];
        }
        $total = (int) db_val("SELECT COUNT(*) FROM instances i LEFT JOIN users u ON u.id = i.uid $where", $bind);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $bind[] = $per;
        $bind[] = ($page - 1) * $per;
        $stmt = db()->prepare("SELECT i.*, u.username, COALESCE(i.display_sort, 0) AS dsort FROM instances i LEFT JOIN users u ON u.id = i.uid $where ORDER BY COALESCE(i.display_sort, 0) ASC, i.id DESC LIMIT ? OFFSET ?");
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        $nodesImgs = [];
        foreach (db_all('SELECT id, images FROM nodes') as $n) {
            $list = json_decode((string) ($n['images'] ?? ''), true);
            $nodesImgs[(int) $n['id']] = is_array($list) ? $list : [];
        }
        respond(view('admin/instances', [
            'title' => '实例管理', 'rows' => $rows, 'search' => $search,
            'page' => $page, 'pages' => $pages, 'total' => $total,
            'nodesImgs' => $nodesImgs,
        ]));
    }
    $instListView = function (int $status = 200) {
        $total = (int) db_val('SELECT COUNT(*) FROM instances');
        $pages = max(1, (int) ceil($total / 20));
        $rows = db_all('SELECT i.*, u.username, COALESCE(i.display_sort, 0) AS dsort FROM instances i LEFT JOIN users u ON u.id = i.uid ORDER BY COALESCE(i.display_sort, 0) ASC, i.id DESC LIMIT 20');
        respond(view('admin/instances', [
            'title' => '实例管理', 'rows' => $rows, 'search' => '',
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if (preg_match('~^/admin/instances/(\d+)/edit$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT i.*, u.username FROM instances i LEFT JOIN users u ON u.id = i.uid WHERE i.id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        respond(view('admin/instance_edit', ['title' => '编辑实例', 'inst' => $inst, 'osList' => node_images((int) $inst['node_id'])]));
    }
    if (preg_match('~^/admin/instances/(\d+)/update$~', $path, $m) && $method === 'POST') {
        csrf_check();
        billing_ensure_pricing_columns();
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        $status = post_str('status', 10);
        $cycle = post_str('cycle', 12);
        if (!in_array($cycle, ['monthly', 'quarterly', 'semiannually', 'annually'], true)) {
            $cycle = billing_instance_cycle($inst);
        }
        $errView = function (int $code) use ($inst) {
            respond(view('admin/instance_edit', ['title' => '编辑实例', 'inst' => $inst]), $code);
        };
        if (!in_array($status, ['running', 'stopped', 'overdue'], true)) {
            flash('err', '状态不合法。');
            $errView(422);
        }
        $expire = null;
        $expireRaw = post_str('expire_at', 16);
        if ($expireRaw !== '') {
            $ts = strtotime($expireRaw);
            if ($ts === false) {
                flash('err', '到期时间格式不正确。');
                $errView(422);
            }
            $expire = date('Y-m-d H:i:s', $ts);
        }
        $cpu = max(1, min(64, post_int('cpu', (int) $inst['cpu'])));
        $ram = max(64, min(65536, post_int('ram_mb', (int) $inst['ram_mb'])));
        $disk = max(1, min(1024, post_int('disk_gb', (int) $inst['disk_gb'])));
        $traffic = max(0, min(999999, post_int('traffic_gb', (int) $inst['traffic_gb'])));
        $os = post_str('os', 40);
        $os = $os !== '' ? $os : (string) $inst['os'];
        $nodeImages = node_images((int) $inst['node_id']);
        if ($nodeImages && !in_array($os, $nodeImages, true)) {
            flash('err', '系统镜像不在该节点的可用列表中（可选：' . implode('、', $nodeImages) . '）。');
            $errView(422);
        }
        $upd = db()->prepare('UPDATE instances SET status = ?, cycle = ?, expire_at = ?, cpu = ?, ram_mb = ?, disk_gb = ?, traffic_gb = ?, os = ?, monthly_price = ?, hourly_price = ? WHERE id = ?');
        $upd->execute([
            $status, $cycle, $expire, $cpu, $ram, $disk, $traffic,
            $os,
            max(0, (float) post_str('monthly_price', 10)),
            max(0, (float) post_str('hourly_price', 10)),
            $inst['id'],
        ]);
        /* 状态变化联动节点真实开关机，并维护计费时间戳 */
        if ($status !== $inst['status']) {
            if ($status === 'running') {
                db_exec('UPDATE instances SET last_billed_at = NOW() WHERE id = ?', [$inst['id']]);
                if ((int) $inst['node_id'] > 0) {
                    node_enqueue((int) $inst['node_id'], 'start', ['name' => $inst['name']]);
                }
            } elseif ($status === 'stopped') {
                db_exec('UPDATE instances SET stopped_at = NOW() WHERE id = ?', [$inst['id']]);
                if ((int) $inst['node_id'] > 0) {
                    node_enqueue((int) $inst['node_id'], 'stop', ['name' => $inst['name']]);
                }
            }
        }
        flash('ok', '实例 ' . $inst['name'] . ' 已更新。');
        $instListView();
    }
    if (preg_match('~^/admin/instances/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        /* 节点实例先入队销毁容器，再删除记录 */
        if ((int) $inst['node_id'] > 0 && $inst['status'] !== 'released') {
            node_lifecycle_on_delete($inst);
        }
        $del = db()->prepare('DELETE FROM instances WHERE id = ?');
        $del->execute([$inst['id']]);
        flash('ok', '实例 ' . $inst['name'] . ' 已删除。');
        $instListView();
    }
    if (preg_match('~^/admin/instances/(\d+)/action$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT * FROM instances WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $inst = $stmt->fetch();
        if (!$inst) {
            respond(view('errors/404', ['title' => '实例不存在']), 404);
        }
        $action = post_str('do', 20);
        if ($action === 'start') {
            $upd = db()->prepare("UPDATE instances SET status = 'running', last_billed_at = NOW() WHERE id = ?");
            $upd->execute([$inst['id']]);
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'start', ['name' => $inst['name']]);
            }
            flash('ok', '实例已开机。');
        } elseif ($action === 'stop') {
            $upd = db()->prepare("UPDATE instances SET status = 'stopped', stopped_at = NOW() WHERE id = ?");
            $upd->execute([$inst['id']]);
            if ((int) $inst['node_id'] > 0) {
                node_enqueue((int) $inst['node_id'], 'stop', ['name' => $inst['name']]);
            }
            flash('ok', '实例已关机。');
        } elseif ($action === 'release') {
            node_lifecycle_on_delete($inst);
            billing_release_instance($inst);
            flash('ok', '实例已释放。');
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
                flash('ok', '控制台启动指令已下发（约 1 分钟内生效，重复点击会自动重启控制台并更换密码）。打开 http://' . $inst['ip'] . ':' . $cport . '/ ，账号 nc，密码 ' . $token . '（6 小时内有效，用户侧实例详情页也会持续显示）。');
            }
        } elseif ($action === 'rebuild') {
            $img = post_str('img', 32);
            if ($img === '') {
                $img = (string) $inst['os'];
            }
            $r = billing_instance_rebuild($inst, $img);
            flash($r['ok'] ? 'ok' : 'err', $r['msg']);
        } elseif ($action === 'clear') {
            $upd = db()->prepare("DELETE FROM instances WHERE id = ? AND status = 'released'");
            $upd->execute([$inst['id']]);
            flash('ok', '已清理释放记录。');
        } else {
            flash('err', '未知操作。');
        }
        $instListView();
    }

    /* ---------- 节点管理 ---------- */
    /* nodes.sort 自愈（拖拽排序用）：列缺失时自动补 */
    $nodesEnsureSort = function () {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            db_val("SELECT sort FROM nodes LIMIT 1");
        } catch (Throwable $e) {
            db_exec('ALTER TABLE nodes ADD COLUMN sort INT NOT NULL DEFAULT 0');
        }
    };
    if ($path === '/admin/nodes' && $method === 'GET') {
        $nodesEnsureSort();
        $page = max(1, (int) query_str('page', 1));
        $per = 12;
        $total = (int) db_val('SELECT COUNT(*) FROM nodes');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT n.*, (SELECT COUNT(*) FROM instances i WHERE i.node_id = n.id AND i.status != ?) AS used FROM nodes n ORDER BY n.sort ASC, n.id ASC LIMIT ? OFFSET ?');
        $stmt->execute(['released', $per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        respond(view('admin/nodes', [
            'title' => '节点管理', 'rows' => $rows,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    $nodesView = function (int $status = 200) {
        $nodesEnsureSort();
        $total = (int) db_val('SELECT COUNT(*) FROM nodes');
        $pages = max(1, (int) ceil($total / 12));
        $stmt = db()->prepare('SELECT n.*, (SELECT COUNT(*) FROM instances i WHERE i.node_id = n.id AND i.status != ?) AS used FROM nodes n ORDER BY n.sort ASC, n.id ASC LIMIT 12');
        $stmt->execute(['released']);
        respond(view('admin/nodes', [
            'title' => '节点管理', 'rows' => $stmt->fetchAll(),
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    /* 节点拖拽排序：按提交顺序写 sort */
    if ($path === '/admin/nodes/reorder' && $method === 'POST') {
        csrf_check();
        $nodesEnsureSort();
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? array_slice($_POST['ids'], 0, 100) : [];
        $i = 0;
        foreach ($rawIds as $v) {
            $n = (int) $v;
            if ($n <= 0) {
                continue;
            }
            $i++;
            $upd = db()->prepare('UPDATE nodes SET sort = ? WHERE id = ?');
            $upd->execute([$i, $n]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($path === '/admin/nodes/create' && $method === 'POST') {
        csrf_check();
        $name = post_str('name', 60);
        if ($name === '') {
            flash('err', '节点名称不能为空。');
            $nodesView(422);
        }
        $key = bin2hex(random_bytes(24));
        $nodesEnsureSort();
        $maxSort = (int) db_val('SELECT COALESCE(MAX(sort), 0) FROM nodes');
        $ins = db()->prepare('INSERT INTO nodes (name, node_key, pub_ip, net_prefix, port_start, port_end, max_instances, status, remark, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([
            $name, $key,
            post_str('pub_ip', 45),
            post_str('net_prefix', 12) ?: '10.0.3',
            max(1024, post_int('port_start', 20000)),
            max(1025, post_int('port_end', 60000)),
            max(1, post_int('max_instances', 500)),
            post_int('status', 1) === 0 ? 0 : 1,
            post_str('remark', 190),
        ]);
        flash('ok', '节点已添加。节点密钥（请配置到节点机 natnode.conf 的 NODE_KEY）：' . $key);
        $nodesView();
    }
    if (preg_match('~^/admin/nodes/(\d+)/update$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $nodesEnsureSort();
        $upd = db()->prepare('UPDATE nodes SET name = ?, pub_ip = ?, net_prefix = ?, port_start = ?, port_end = ?, max_instances = ?, status = ?, remark = ?, sort = ? WHERE id = ?');
        $upd->execute([
            post_str('name', 60),
            post_str('pub_ip', 45),
            post_str('net_prefix', 12) ?: '10.0.3',
            max(1024, post_int('port_start', 20000)),
            max(1025, post_int('port_end', 60000)),
            max(1, post_int('max_instances', 500)),
            post_int('status', 1) === 0 ? 0 : 1,
            post_str('remark', 190),
            max(0, post_int('sort', 0)),
            (int) $m[1],
        ]);
        flash('ok', '节点已更新。');
        $nodesView();
    }
    if (preg_match('~^/admin/nodes/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE node_id = ? AND status != ?');
        $stmt->execute([(int) $m[1], 'released']);
        if ((int) $stmt->fetchColumn() > 0) {
            $upd = db()->prepare('UPDATE nodes SET status = 0 WHERE id = ?');
            $upd->execute([(int) $m[1]]);
            flash('info', '该节点仍有实例，已改为停用而非删除。');
        } else {
            $del = db()->prepare('DELETE FROM nodes WHERE id = ?');
            $del->execute([(int) $m[1]]);
            flash('ok', '节点已删除。');
        }
        $nodesView();
    }

    /* ---------- 套餐管理 ---------- */
    /* 套餐拖拽排序：按提交顺序写 sort */
    if ($path === '/admin/plans/reorder' && $method === 'POST') {
        csrf_check();
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? array_slice($_POST['ids'], 0, 100) : [];
        $i = 0;
        foreach ($rawIds as $v) {
            $n = (int) $v;
            if ($n <= 0) {
                continue;
            }
            $i++;
            $upd = db()->prepare('UPDATE plans SET sort = ? WHERE id = ?');
            $upd->execute([$i, $n]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($path === '/admin/plans' && $method === 'GET') {
        billing_ensure_pricing_columns();
        $page = max(1, (int) query_str('page', 1));
        $per = 12;
        $total = (int) db_val('SELECT COUNT(*) FROM plans');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT * FROM plans ORDER BY sort ASC, id ASC LIMIT ? OFFSET ?');
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        respond(view('admin/plans', [
            'title' => '套餐管理', 'rows' => $rows,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    $plansView = function (int $status = 200) {
        billing_ensure_pricing_columns();
        $total = (int) db_val('SELECT COUNT(*) FROM plans');
        $pages = max(1, (int) ceil($total / 12));
        $stmt = db()->prepare('SELECT * FROM plans ORDER BY sort ASC, id ASC LIMIT 12');
        $stmt->execute();
        respond(view('admin/plans', [
            'title' => '套餐管理', 'rows' => $stmt->fetchAll(),
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if ($path === '/admin/plans/create' && $method === 'POST') {
        csrf_check();
        billing_ensure_pricing_columns();
        $name = post_str('name', 64);
        if ($name === '') {
            flash('err', '套餐名称不能为空。');
            $plansView(422);
        }
        $images = isset($_POST['images']) && is_array($_POST['images']) ? array_values(array_filter($_POST['images'], 'is_string')) : [];
        $newSort = post_int('sort', -1) >= 0 ? post_int('sort', 0) : ((int) db_val('SELECT COALESCE(MAX(sort), -1) FROM plans') + 1);
        $ins = db()->prepare('INSERT INTO plans (name, node_code, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, quarterly_price, semiannually_price, annually_price, traffic_gb, bandwidth_mbps, stock, nat_type, sort, status, badge, description, node_id, port_count, images, kyc_required, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $ins->execute([
            $name,
            'jpty1',
            post_str('node_name', 60) ?: '东京 #1',
            post_str('region', 20) ?: '东京',
            max(1, post_int('cpu', 1)), max(256, post_int('ram_mb', 1024)), max(5, post_int('disk_gb', 10)),
            max(0, (float) post_str('monthly_price', 10)), max(0, (float) post_str('hourly_price', 10)),
            max(0, (float) post_str('quarterly_price', 10)), max(0, (float) post_str('semiannually_price', 10)), max(0, (float) post_str('annually_price', 10)),
            max(0, post_int('traffic_gb', 1000)), max(1, post_int('bandwidth_mbps', 100)),
            max(0, post_int('stock', 50)), post_str('nat_type', 30) ?: '共享IP · NAT端口段',
            $newSort, post_int('status', 1) === 0 ? 0 : 1,
            post_str('badge', 20), post_str('description', 500),
            post_int('node_id', 0), max(1, min(64, post_int('port_count', 15))), json_encode($images) ?: '[]', max(0, min(2, post_int('kyc_required', 0))),
        ]);
        flash('ok', '套餐已创建。');
        $plansView();
    }
    if (preg_match('~^/admin/plans/(\d+)/update$~', $path, $m) && $method === 'POST') {
        csrf_check();
        billing_ensure_pricing_columns();
        $images = isset($_POST['images']) && is_array($_POST['images']) ? array_values(array_filter($_POST['images'], 'is_string')) : [];
        $upd = db()->prepare('UPDATE plans SET name = ?, node_name = ?, region = ?, cpu = ?, ram_mb = ?, disk_gb = ?, monthly_price = ?, hourly_price = ?, quarterly_price = ?, semiannually_price = ?, annually_price = ?, traffic_gb = ?, bandwidth_mbps = ?, stock = ?, nat_type = ?, sort = ?, status = ?, badge = ?, description = ?, node_id = ?, port_count = ?, images = ?, kyc_required = ? WHERE id = ?');
        $upd->execute([
            post_str('name', 64),
            post_str('node_name', 60) ?: '东京 #1',
            post_str('region', 20) ?: '东京',
            max(1, post_int('cpu', 1)), max(256, post_int('ram_mb', 1024)), max(5, post_int('disk_gb', 10)),
            max(0, (float) post_str('monthly_price', 10)), max(0, (float) post_str('hourly_price', 10)),
            max(0, (float) post_str('quarterly_price', 10)), max(0, (float) post_str('semiannually_price', 10)), max(0, (float) post_str('annually_price', 10)),
            max(0, post_int('traffic_gb', 1000)), max(1, post_int('bandwidth_mbps', 100)),
            max(0, post_int('stock', 50)), post_str('nat_type', 30) ?: '共享IP · NAT端口段',
            post_int('sort', 0), post_int('status', 1) === 0 ? 0 : 1,
            post_str('badge', 20), post_str('description', 500),
            post_int('node_id', 0), max(1, min(64, post_int('port_count', 15))), json_encode($images) ?: '[]',
            max(0, min(2, post_int('kyc_required', 0))),
            (int) $m[1],
        ]);
        /* 同步规格到该套餐下所有未释放实例（带宽/流量等记录与计费口径；实际容器规格需重装生效） */
        $sync = db()->prepare('UPDATE instances SET cpu = ?, ram_mb = ?, disk_gb = ?, traffic_gb = ?, bandwidth_mbps = ?, node_name = ?, region = ? WHERE plan_id = ? AND status != ?');
        $sync->execute([
            max(1, post_int('cpu', 1)), max(256, post_int('ram_mb', 1024)), max(5, post_int('disk_gb', 10)),
            max(0, post_int('traffic_gb', 1000)), max(1, post_int('bandwidth_mbps', 100)),
            post_str('node_name', 60) ?: '东京 #1', post_str('region', 20) ?: '东京',
            (int) $m[1], 'released',
        ]);
        $synced = (int) $sync->rowCount();
        flash('ok', '套餐已更新。' . ($synced > 0 ? '已同步 ' . $synced . ' 台实例的配置信息。' : ''));
        $plansView();
    }
    if (preg_match('~^/admin/plans/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT COUNT(*) FROM instances WHERE plan_id = ? AND status != ?');
        $stmt->execute([(int) $m[1], 'released']);
        if ((int) $stmt->fetchColumn() > 0) {
            $upd = db()->prepare('UPDATE plans SET status = 0 WHERE id = ?');
            $upd->execute([(int) $m[1]]);
            flash('info', '该套餐存在实例引用，已改为下架而非删除。');
        } else {
            $del = db()->prepare('DELETE FROM plans WHERE id = ?');
            $del->execute([(int) $m[1]]);
            flash('ok', '套餐已删除。');
        }
        $plansView();
    }

    /* ---------- 财务：订单与充值 ---------- */
    $financeView = function (string $tab = 'topups', int $status = 200) {
        $where = $tab === 'orders' ? "WHERE o.type != 'topup'" : "WHERE o.type = 'topup'";
        $total = (int) db_val("SELECT COUNT(*) FROM orders o $where");
        $pages = max(1, (int) ceil($total / 20));
        $stmt = db()->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON u.id = o.uid $where ORDER BY o.id DESC LIMIT 20");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $orders = $tab === 'orders' ? $rows : [];
        $topups = $tab === 'topups' ? $rows : [];
        respond(view('admin/finance', [
            'title' => '财务管理', 'tab' => $tab, 'orders' => $orders, 'topups' => $topups,
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if (preg_match('~^/admin/orders/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $del = db()->prepare('DELETE FROM orders WHERE id = ?');
        $del->execute([(int) $m[1]]);
        flash('ok', '订单记录已删除。');
        $financeView('orders');
    }
    if ($path === '/admin/finance/clear' && $method === 'POST') {
        csrf_check();
        $type = post_str('clear_type', 10);
        if ($type === 'topups') {
            db_exec("DELETE FROM orders WHERE type = 'topup'");
            flash('ok', '已清空全部充值单记录。');
        } elseif ($type === 'orders') {
            db_exec("DELETE FROM orders WHERE type != 'topup'");
            flash('ok', '已清空全部消费订单记录。');
        }
        $financeView($type === 'orders' ? 'orders' : 'topups');
    }
    if ($path === '/admin/finance' && $method === 'GET') {
        $tab = query_str('tab') === 'orders' ? 'orders' : 'topups';
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $where = $tab === 'orders' ? "WHERE o.type != 'topup'" : "WHERE o.type = 'topup'";
        $total = (int) db_val("SELECT COUNT(*) FROM orders o $where");
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON u.id = o.uid $where ORDER BY o.id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        $orders = $tab === 'orders' ? $rows : [];
        $topups = $tab === 'topups' ? $rows : [];
        respond(view('admin/finance', [
            'title' => '财务管理', 'tab' => $tab, 'orders' => $orders, 'topups' => $topups,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    if (preg_match('~^/admin/topups/(\d+)/confirm$~', $path, $m) && $method === 'POST') {
        csrf_check();
        db()->beginTransaction();
        try {
            $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? AND type = 'topup' AND status = 'pending' FOR UPDATE");
            $stmt->execute([(int) $m[1]]);
            $order = $stmt->fetch();
            if (!$order) {
                db()->rollBack();
                flash('err', '充值单不存在或已处理。');
                $financeView('topups', 404);
            }
            $upd = db()->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ?");
            $upd->execute([$order['id']]);
            $stmt = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([(float) $order['amount'], $order['uid']]);
            $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$order['uid']]);
            $after = (float) $stmt->fetchColumn();
            $ins = db()->prepare("INSERT INTO transactions (uid, type, amount, balance_after, remark, created_at) VALUES (?, 'topup', ?, ?, ?, NOW())");
            $ins->execute([$order['uid'], (float) $order['amount'], $after, '充值到账 单号' . $order['order_no']]);
            db()->commit();
            try {
                ref_award_on_topup((int) $order['uid'], (float) $order['amount']);
            } catch (Throwable $e2) {
                /* 返佣失败不影响入账 */
            }
            flash('ok', '充值单已确认到账。');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('err', '操作失败：' . $e->getMessage());
        }
        $financeView('topups');
    }
    if (preg_match('~^/admin/topups/(\d+)/cancel$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $upd = db()->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND type = 'topup' AND status = 'pending'");
        $upd->execute([(int) $m[1]]);
        flash('ok', '充值单已取消。');
        $financeView('topups');
    }
    if ($path === '/admin/transactions' && $method === 'GET') {
        $page = max(1, (int) query_str('page', 1));
        $per = 30;
        $total = (int) db_val('SELECT COUNT(*) FROM transactions');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id = t.uid ORDER BY t.id DESC LIMIT ? OFFSET ?');
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        respond(view('admin/transactions', [
            'title' => '余额流水', 'rows' => $rows,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    $txView = function (int $status = 200) {
        $total = (int) db_val('SELECT COUNT(*) FROM transactions');
        $pages = max(1, (int) ceil($total / 30));
        $stmt = db()->prepare('SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON u.id = t.uid ORDER BY t.id DESC LIMIT 30');
        $stmt->execute();
        respond(view('admin/transactions', [
            'title' => '余额流水', 'rows' => $stmt->fetchAll(),
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if (preg_match('~^/admin/transactions/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $del = db()->prepare('DELETE FROM transactions WHERE id = ?');
        $del->execute([(int) $m[1]]);
        flash('ok', '流水记录已删除。');
        $txView();
    }
    if ($path === '/admin/transactions/clear' && $method === 'POST') {
        csrf_check();
        db_exec('DELETE FROM transactions');
        flash('ok', '已清空全部余额流水。');
        $txView();
    }

    /* ---------- 工单管理 ---------- */
    if (preg_match('~^/admin/tickets/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $tid = (int) $m[1];
        $del = db()->prepare('DELETE FROM ticket_replies WHERE ticket_id = ?');
        $del->execute([$tid]);
        $del = db()->prepare('DELETE FROM tickets WHERE id = ?');
        $del->execute([$tid]);
        flash('ok', '工单已删除。');
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $total = (int) db_val('SELECT COUNT(*) FROM tickets');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $offset = ($page - 1) * $per;
        $stmt = db()->prepare('SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.uid ORDER BY FIELD(t.status, ?, ?, ?) = 0, FIELD(t.status, ?, ?), t.updated_at DESC LIMIT ? OFFSET ?');
        $stmt->execute(['open', 'replied', 'closed', 'open', 'replied', $per, $offset]);
        respond(view('admin/tickets', ['title' => '工单管理', 'rows' => $stmt->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total]));
    }
    if ($path === '/admin/tickets' && $method === 'GET') {
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $total = (int) db_val('SELECT COUNT(*) FROM tickets');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $offset = ($page - 1) * $per;
        $stmt = db()->prepare('SELECT t.*, u.username FROM tickets t LEFT JOIN users u ON u.id = t.uid ORDER BY FIELD(t.status, ?, ?, ?) = 0, FIELD(t.status, ?, ?), t.updated_at DESC LIMIT ? OFFSET ?');
        $stmt->execute(['open', 'replied', 'closed', 'open', 'replied', $per, $offset]);
        $rows = $stmt->fetchAll();
        respond(view('admin/tickets', ['title' => '工单管理', 'rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total]));
    }
    if (preg_match('~^/admin/tickets/(\d+)$~', $path, $m) && $method === 'GET') {
        $stmt = db()->prepare('SELECT t.*, u.username, u.email FROM tickets t LEFT JOIN users u ON u.id = t.uid WHERE t.id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            respond(view('errors/404', ['title' => '工单不存在']), 404);
        }
        $rstmt = db()->prepare('SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON u.id = r.uid WHERE r.ticket_id = ? ORDER BY r.id ASC');
        $rstmt->execute([$ticket['id']]);
        $replies = $rstmt->fetchAll();
        respond(view('admin/ticket_detail', ['title' => '工单 #' . $ticket['id'], 'ticket' => $ticket, 'replies' => $replies]));
    }
    if (preg_match('~^/admin/tickets/(\d+)/reply$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            respond(view('errors/404', ['title' => '工单不存在']), 404);
        }
        $content = post_str('content', 3000);
        $close = post_str('close') === '1';
        if ($content !== '') {
            $ins = db()->prepare("INSERT INTO ticket_replies (ticket_id, uid, is_admin, content, created_at) VALUES (?, ?, 1, ?, NOW())");
            $ins->execute([$ticket['id'], $adminId, $content]);
        }
        $newStatus = $close ? 'closed' : 'replied';
        $upd = db()->prepare('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$newStatus, $ticket['id']]);
        /* 邮件通知工单所有者（SMTP 未配置或发送失败不影响回复主流程） */
        if ($content !== '') {
            try {
                if (smtp_ready()) {
                    $mstmt = db()->prepare('SELECT u.email, u.username FROM tickets t LEFT JOIN users u ON u.id = t.uid WHERE t.id = ? LIMIT 1');
                    $mstmt->execute([(int) $ticket['id']]);
                    $mu = $mstmt->fetch();
                    if ($mu && filter_var((string) $mu['email'], FILTER_VALIDATE_EMAIL)) {
                        mail_send_ticket_reply((string) $mu['email'], (string) $mu['username'], (int) $ticket['id'], (string) $ticket['title']);
                    }
                    tg_notify_user((int) $ticket['uid'], "💬 工单 #{$ticket['id']} {$ticket['title']} 有新的客服回复，请登录控制台查看。");
                }
            } catch (Throwable $e) {
                /* 通知失败静默 */
            }
        }
        flash('ok', $close ? '已回复并关闭工单。' : '回复已发送。');
        $rstmt = db()->prepare('SELECT r.*, u.username FROM ticket_replies r LEFT JOIN users u ON u.id = r.uid WHERE r.ticket_id = ? ORDER BY r.id ASC');
        $rstmt->execute([$ticket['id']]);
        $replies = $rstmt->fetchAll();
        $stmt = db()->prepare('SELECT t.*, u.username, u.email FROM tickets t LEFT JOIN users u ON u.id = t.uid WHERE t.id = ? LIMIT 1');
        $stmt->execute([$ticket['id']]);
        respond(view('admin/ticket_detail', ['title' => '工单 #' . $ticket['id'], 'ticket' => $stmt->fetch(), 'replies' => $replies]));
    }

    /* ---------- 优惠码管理 ---------- */
    if ($path === '/admin/coupons' && $method === 'GET') {
        coupon_ensure_tables();
        $rows = db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200');
        respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => $rows]));
    }
    if ($path === '/admin/coupons/create' && $method === 'POST') {
        csrf_check();
        $code = strtoupper(trim(post_str('code', 32)));
        $type = post_str('type', 10) === 'fixed' ? 'fixed' : 'percent';
        $value = round((float) post_str('value', 12), 2);
        $minAmount = round((float) post_str('min_amount', 12), 2);
        $quota = max(1, (int) post_str('quota', 9));
        $expires = trim(post_str('expires_at', 19));
        if (!preg_match('~^[A-Z0-9]{3,32}$~', $code)) {
            flash('err', '优惠码需为 3-32 位大写字母 / 数字。');
            respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200')]));
        }
        if ($value <= 0 || ($type === 'percent' && $value > 99)) {
            flash('err', '折扣值不合法（百分比 1-99，立减为大于 0 的金额）。');
            respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200')]));
        }
        coupon_ensure_tables();
        try {
            $ins = db()->prepare('INSERT INTO coupons (code, type, value, min_amount, quota, expires_at, status) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $ins->execute([$code, $type, $value, $minAmount, $quota, $expires !== '' ? $expires : null]);
            flash('ok', '优惠码 ' . $code . ' 已创建。');
        } catch (PDOException $e) {
            flash('err', strpos($e->getMessage(), 'Duplicate') !== false ? '优惠码已存在。' : '创建失败：' . $e->getMessage());
        }
        respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200')]));
    }
    if (preg_match('~^/admin/coupons/(\d+)/toggle$~', $path, $m) && $method === 'POST') {
        csrf_check();
        coupon_ensure_tables();
        $upd = db()->prepare('UPDATE coupons SET status = IF(status = 1, 0, 1) WHERE id = ?');
        $upd->execute([(int) $m[1]]);
        flash('ok', '状态已切换。');
        respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200')]));
    }
    if (preg_match('~^/admin/coupons/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        coupon_ensure_tables();
        $del = db()->prepare('DELETE FROM coupons WHERE id = ?');
        $del->execute([(int) $m[1]]);
        $del = db()->prepare('DELETE FROM coupon_redemptions WHERE coupon_id = ?');
        $del->execute([(int) $m[1]]);
        flash('ok', '优惠码已删除。');
        respond(view('admin/coupons', ['title' => '优惠码管理', 'rows' => db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 200')]));
    }

    /* ---------- 公告管理 ---------- */
    if ($path === '/admin/news' && $method === 'GET') {
        $page = max(1, (int) query_str('page', 1));
        $per = 20;
        $total = (int) db_val('SELECT COUNT(*) FROM news');
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $stmt = db()->prepare('SELECT * FROM news ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->execute([$per, ($page - 1) * $per]);
        $rows = $stmt->fetchAll();
        respond(view('admin/news', [
            'title' => '公告管理', 'rows' => $rows,
            'page' => $page, 'pages' => $pages, 'total' => $total,
        ]));
    }
    $newsView = function (int $status = 200) {
        $total = (int) db_val('SELECT COUNT(*) FROM news');
        $pages = max(1, (int) ceil($total / 20));
        $rows = db_all('SELECT * FROM news ORDER BY id DESC LIMIT 20');
        respond(view('admin/news', [
            'title' => '公告管理', 'rows' => $rows,
            'page' => 1, 'pages' => $pages, 'total' => $total,
        ]), $status);
    };
    if (preg_match('~^/admin/news/(\d+)/edit$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $title = post_str('title', 120);
        $content = post_str('content', 10000);
        if ($title === '' || $content === '') {
            flash('err', '标题和内容不能为空。');
            $newsView(422);
        }
        $upd = db()->prepare('UPDATE news SET title = ?, content = ? WHERE id = ?');
        $upd->execute([$title, $content, (int) $m[1]]);
        flash('ok', '公告已更新。');
        $newsView();
    }
    if ($path === '/admin/news/create' && $method === 'POST') {
        csrf_check();
        $title = post_str('title', 120);
        $content = post_str('content', 10000);
        if ($title === '' || $content === '') {
            flash('err', '标题和内容不能为空。');
            $newsView(422);
        }
        $ins = db()->prepare('INSERT INTO news (title, content, status, created_at) VALUES (?, ?, 1, NOW())');
        $ins->execute([$title, $content]);
        flash('ok', '公告已发布。');
        $newsView();
    }
    if (preg_match('~^/admin/news/(\d+)/toggle$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $upd = db()->prepare('UPDATE news SET status = 1 - status WHERE id = ?');
        $upd->execute([(int) $m[1]]);
        flash('ok', '公告状态已切换。');
        $newsView();
    }
    if (preg_match('~^/admin/news/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $del = db()->prepare('DELETE FROM news WHERE id = ?');
        $del->execute([(int) $m[1]]);
        flash('ok', '公告已删除。');
        $newsView();
    }

    /* ---------- 实名审核 ---------- */
    if ($path === '/admin/kyc' && $method === 'GET') {
        $stmt = db()->prepare('SELECT k.*, u.username, u.email FROM kyc k LEFT JOIN users u ON u.id = k.uid ORDER BY FIELD(k.status, ?) DESC, k.id DESC LIMIT 100');
        $stmt->execute(['pending']);
        $rows = $stmt->fetchAll();
        respond(view('admin/kyc', ['title' => '实名审核', 'rows' => $rows]));
    }
    $kycView = function (int $status = 200) {
        $stmt = db()->prepare('SELECT k.*, u.username, u.email FROM kyc k LEFT JOIN users u ON u.id = k.uid ORDER BY FIELD(k.status, ?) DESC, k.id DESC LIMIT 100');
        $stmt->execute(['pending']);
        respond(view('admin/kyc', ['title' => '实名审核', 'rows' => $stmt->fetchAll()]), $status);
    };
    if (preg_match('~^/admin/kyc/(\d+)/(approve|reject)$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $status = $m[2] === 'approve' ? 'approved' : 'rejected';
        $stmt = db()->prepare('SELECT uid FROM kyc WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $row = $stmt->fetch();
        if ($row) {
            $upd = db()->prepare('UPDATE kyc SET status = ?, reviewed_at = NOW() WHERE id = ?');
            $upd->execute([$status, (int) $m[1]]);
            if ($status === 'approved') {
                $upd = db()->prepare('UPDATE users SET kyc_status = 2 WHERE id = ?');
            } else {
                $upd = db()->prepare('UPDATE users SET kyc_status = 3 WHERE id = ?');
            }
            $upd->execute([$row['uid']]);
            flash('ok', '实名申请已' . ($status === 'approved' ? '通过' : '驳回') . '。');
        }
        $kycView();
    }

    /* ---------- 实名审核 ---------- */
    if (preg_match('~^/admin/kyc/(\d+)/edit$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT uid FROM kyc WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $row = $stmt->fetch();
        if (!$row) {
            respond(view('errors/404', ['title' => '记录不存在']), 404);
        }
        $realName = post_str('real_name', 30);
        $idCard = post_str('id_card', 18);
        $phone = post_str('phone', 11);
        if ($realName === '' || !preg_match('~^\d{17}[\dXx]$~', $idCard) || !preg_match('~^1\d{10}$~', $phone)) {
            flash('err', '请填写有效的姓名、18 位身份证号与 11 位手机号。');
        $kycView(422);
        }
        $upd = db()->prepare('UPDATE kyc SET real_name = ?, id_card = ?, phone = ? WHERE id = ?');
        $upd->execute([secret_encrypt($realName), secret_encrypt($idCard), secret_encrypt($phone), (int) $m[1]]);
        flash('ok', '实名信息已更新。');
        $kycView();
    }
    if (preg_match('~^/admin/kyc/(\d+)/delete$~', $path, $m) && $method === 'POST') {
        csrf_check();
        $stmt = db()->prepare('SELECT uid FROM kyc WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $row = $stmt->fetch();
        if ($row) {
            $del = db()->prepare('DELETE FROM kyc WHERE id = ?');
            $del->execute([(int) $m[1]]);
            $upd = db()->prepare('UPDATE users SET kyc_status = 0 WHERE id = ?');
            $upd->execute([$row['uid']]);
            flash('ok', '实名记录已删除，用户实名状态已重置。');
        }
        $kycView();
    }

    /* ---------- 系统设置 ---------- */
    if ($path === '/admin/settings' && $method === 'GET') {
        respond(view('admin/settings', ['title' => '系统设置']));
    }
    if ($path === '/admin/settings/save' && $method === 'POST') {
        csrf_check();
        $keys = [
            'site_name' => post_str('site_name', 40),
            'ports_per_instance' => (string) max(3, post_int('ports_per_instance', 15)),
            'register_enabled' => post_str('register_enabled') === '0' ? '0' : '1',
            'announcement' => post_str('announcement', 200),
            'contact' => post_str('contact', 100),
            'site_url' => rtrim(trim(post_str('site_url', 190)), '/'),
            'codepay_url' => rtrim(trim(post_str('codepay_url', 190)), '/'),
            'codepay_id' => trim(post_str('codepay_id', 32)),
            'codepay_key' => trim(post_str('codepay_key', 64)),
            'codepay_alipay' => post_str('codepay_alipay') === '1' ? '1' : '0',
            'codepay_wxpay' => post_str('codepay_wxpay') === '1' ? '1' : '0',
            'codepay_qqpay' => post_str('codepay_qqpay') === '1' ? '1' : '0',
            'alipay_enable' => post_str('alipay_enable') === '1' ? '1' : '0',
            'alipay_appid' => trim(post_str('alipay_appid', 32)),
            'alipay_private_key' => trim(post_str('alipay_private_key', 5000)) !== '' ? secret_encrypt(trim(post_str('alipay_private_key', 5000))) : trim(setting('alipay_private_key', '')),
            'alipay_public_key' => trim(post_str('alipay_public_key', 5000)),
            'wx_enable' => post_str('wx_enable') === '1' ? '1' : '0',
            'wx_mchid' => trim(post_str('wx_mchid', 16)),
            'wx_appid' => trim(post_str('wx_appid', 32)),
            'wx_serial_no' => trim(post_str('wx_serial_no', 64)),
            'wx_api_v3_key' => trim(post_str('wx_api_v3_key', 64)) !== '' ? secret_encrypt(trim(post_str('wx_api_v3_key', 64))) : trim(setting('wx_api_v3_key', '')),
            'wx_private_key' => trim(post_str('wx_private_key', 6000)) !== '' ? secret_encrypt(trim(post_str('wx_private_key', 6000))) : trim(setting('wx_private_key', '')),
            'wx_public_key' => trim(post_str('wx_public_key', 5000)),
            'kyc_global_required' => post_str('kyc_global_required') === '1' ? '1' : '0',
            'kyc_provider' => in_array(($kpTmp = post_str('kyc_provider', 20)), ['', 'jidian', 'alipay', 'wx', 'zhijian'], true) ? $kpTmp : '',
            'jidian_app_id' => trim(post_str('jidian_app_id', 64)),
            'jidian_app_key' => trim(post_str('jidian_app_key', 64)),
            'kyc_relay_ip' => trim(post_str('kyc_relay_ip', 45)),
            'kyc_alipay_app_id' => trim(post_str('kyc_alipay_app_id', 32)),
            'kyc_alipay_private_key' => trim(post_str('kyc_alipay_private_key', 5000)) !== '' ? secret_encrypt(trim(post_str('kyc_alipay_private_key', 5000))) : trim(setting('kyc_alipay_private_key', '')),
            'kyc_wx_app_id' => trim(post_str('kyc_wx_app_id', 32)),
            'kyc_wx_api_v3_key' => trim(post_str('kyc_wx_api_v3_key', 64)) !== '' ? secret_encrypt(trim(post_str('kyc_wx_api_v3_key', 64))) : trim(setting('kyc_wx_api_v3_key', '')),
            'kyc_zhijian_url' => rtrim(trim(post_str('kyc_zhijian_url', 190)), '/'),
            'kyc_zhijian_app_id' => trim(post_str('kyc_zhijian_app_id', 64)),
            'kyc_zhijian_app_key' => trim(post_str('kyc_zhijian_app_key', 64)),
            'usdt_enable' => post_str('usdt_enable') === '1' ? '1' : '0',
            'usdt_address' => trim(post_str('usdt_address', 120)),
            'usdt_rate' => (string) max(0.01, (float) post_str('usdt_rate', 12)),
            'smtp_host' => trim(post_str('smtp_host', 120)),
            'smtp_port' => (string) max(1, min(65535, post_int('smtp_port', 465))),
            'smtp_encrypt' => in_array(($encTmp = post_str('smtp_encrypt', 8)), ['ssl', 'tls', 'none'], true) ? $encTmp : 'ssl',
            'smtp_user' => trim(post_str('smtp_user', 120)),
            'smtp_pass' => trim(post_str('smtp_pass', 200)) !== '' ? secret_encrypt(post_str('smtp_pass', 200)) : trim(setting('smtp_pass', '')),
            'smtp_from' => trim(post_str('smtp_from', 120)),
            'smtp_fromname' => trim(post_str('smtp_fromname', 60)),
            'reg_verify' => post_str('reg_verify') === '0' ? '0' : '1',
            'auto_renew_enabled' => post_str('auto_renew_enabled') === '0' ? '0' : '1',
            'site_accent' => in_array(($saTmp = post_str('site_accent', 12)), ['default', 'ocean', 'forest', 'violet', 'sunset', 'rose'], true) ? $saTmp : 'default',
            'ref_enabled' => post_str('ref_enabled') === '0' ? '0' : '1',
            'ref_percent' => (string) max(1, min(50, (float) post_str('ref_percent', 12))),
            'tos_html' => post_str('tos_html', 20000),
            'privacy_html' => post_str('privacy_html', 20000),
            'tg_enabled' => post_str('tg_enabled') === '1' ? '1' : '0',
            'tg_bot_token' => trim(post_str('tg_bot_token', 120)) !== '' ? secret_encrypt(trim(post_str('tg_bot_token', 120))) : trim(setting('tg_bot_token', '')),
        ];
        foreach ($keys as $k => $v) {
            try {
                setting_save($k, $v);
            } catch (Throwable $e) {
                flash('err', '保存 ' . $k . ' 失败：' . $e->getMessage());
                respond(view('admin/settings', ['title' => '系统设置']));
            }
        }
        if (post_str('do', 20) === 'mailtest') {
            $to = post_str('test_email', 120);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                flash('err', '请输入有效的测试邮箱地址。');
                respond(view('admin/settings', ['title' => '系统设置']));
            }
            [$ok, $err] = mail_send($to, setting('site_name', 'NAT Cloud') . ' SMTP 测试邮件', '<p>这是一封来自管理后台的 SMTP 测试邮件。收到即代表发信配置正确。</p>');
            flash($ok ? 'ok' : 'err', $ok ? '测试邮件已发送至 ' . $to . '，请查收。' : '发送失败：' . $err);
        } else {
            flash('ok', '设置已保存。');
        }
        respond(view('admin/settings', ['title' => '系统设置']));
    }

    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
