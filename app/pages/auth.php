<?php
declare(strict_types=1);

/* NAT Cloud - 认证页面：登录 / 注册 / 找回 */

function auth_handle(string $path, string $method): void
{
    if ($path === '/auth/login') {
        if ($method === 'GET') {
            if (auth_user()) {
                respond(view('auth/login', ['title' => '登录', 'old' => []]));
            }
            respond(view('auth/login', ['title' => '登录', 'old' => []]));
        }
        /* POST 登录 */
        csrf_check();
        if (!captcha_check()) {
            flash('err', '图形验证码不正确。');
            respond(view('auth/login', ['title' => '登录', 'old' => $old]), 422);
        }
        $account = post_str('account', 120);
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']);
        $old = ['account' => $account];

        if ($account === '' || $password === '') {
            flash('err', '请输入账号和密码。');
            respond(view('auth/login', ['title' => '登录', 'old' => $old]), 422);
        }
        if (auth_recent_failures($account, client_ip()) >= 8) {
            flash('err', '失败次数过多，请 10 分钟后再试。');
            respond(view('auth/login', ['title' => '登录', 'old' => $old]), 429);
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1');
        $stmt->execute([$account, $account]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            auth_record_failure($account, client_ip());
            flash('err', '账号或密码不正确。');
            respond(view('auth/login', ['title' => '登录', 'old' => $old]), 401);
        }
        if ((int) $user['status'] !== 1) {
            if ((int) $user['status'] === 0) {
                mail_ensure_tables();
                $st = db()->prepare('SELECT COUNT(*) FROM email_verifies WHERE uid = ?');
                $st->execute([(int) $user['id']]);
                if ((int) $st->fetchColumn() > 0) {
                    flash('err', '邮箱尚未验证，请查收验证邮件，或在「重发验证邮件」页重新发送。');
                } else {
                    flash('err', '账号已被禁用，请联系管理员。');
                }
            } else {
                flash('err', '账号已被禁用，请联系管理员。');
            }
            respond(view('auth/login', ['title' => '登录', 'old' => $old]), 403);
        }
        auth_clear_failures($account);
        auth_issue_token((int) $user['id'], 0, $remember);
        $upd = db()->prepare('UPDATE users SET last_login_at = NOW(), last_ip = ? WHERE id = ?');
        $upd->execute([client_ip(), $user['id']]);
        flash('ok', '登录成功，欢迎回来。');
        $svcRows = db_all('SELECT * FROM instances WHERE uid = ? ORDER BY id DESC', [(int) $user['id']]);
        respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $svcRows, 'tab' => '']));
    }

    if ($path === '/auth/register') {
        if ($method === 'GET') {
            respond(view('auth/register', ['title' => '免费注册', 'old' => []]));
        }
        csrf_check();
        if (!captcha_check()) {
            flash('err', '图形验证码不正确。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 422);
        }
        if (setting('register_enabled', '1') !== '1') {
            flash('err', '当前已关闭注册，请联系管理员。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => []]), 403);
        }
        $username = post_str('username', 32);
        $email = post_str('email', 120);
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $password2 = isset($_POST['password2']) && is_string($_POST['password2']) ? $_POST['password2'] : '';
        $old = ['username' => $username, 'email' => $email];

        if (!preg_match('~^[a-zA-Z][a-zA-Z0-9_]{2,31}$~', $username)) {
            flash('err', '用户名需以字母开头，3-32 位字母、数字或下划线。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('err', '邮箱格式不正确。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            flash('err', '密码长度需在 8-72 位之间。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 422);
        }
        if ($password !== $password2) {
            flash('err', '两次输入的密码不一致。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 422);
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            flash('err', '用户名或邮箱已被注册。');
            respond(view('auth/register', ['title' => '免费注册', 'old' => $old]), 409);
        }
        $needVerify = setting('reg_verify', '1') === '1' && smtp_ready();
        $ins = db()->prepare('INSERT INTO users (username, email, password_hash, balance, status, created_at) VALUES (?, ?, ?, 0, ?, NOW())');
        $ins->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $needVerify ? 0 : 1]);
        $uid = (int) db()->lastInsertId();
        if ($needVerify) {
            mail_ensure_tables();
            $token = bin2hex(random_bytes(16));
            $ins = db()->prepare('INSERT INTO email_verifies (uid, email, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))');
            $ins->execute([$uid, $email, $token]);
            $link = site_base_url() . '/auth/verify?token=' . $token;
            [$ok, $err] = mail_send_verify($email, $username, $link);
            if (!$ok) {
                flash('err', '注册成功，但验证邮件发送失败：' . $err . '。请登录后在登录页点击「重发验证邮件」。');
                respond(view('auth/verify_sent', ['title' => '验证邮件', 'email' => $email]), 202);
            }
            flash('ok', '注册成功！验证邮件已发送至 ' . $email . '，请查收并完成验证（48 小时内有效）。');
            respond(view('auth/verify_sent', ['title' => '验证邮件已发送', 'email' => $email]), 200);
        }
        auth_issue_token($uid, 0, false);
        flash('ok', '注册成功，欢迎加入 ' . setting('site_name', 'NAT Cloud') . '。');
        $svcRows = db_all('SELECT * FROM instances WHERE uid = ? ORDER BY id DESC', [$uid]);
        respond(view('dashboard/services', ['title' => '我的服务', 'rows' => $svcRows, 'tab' => '']));
    }

    /* 邮箱验证（注册激活） */
    if ($path === '/auth/verify' && $method === 'GET') {
        mail_ensure_tables();
        $token = preg_match('~^[a-f0-9]{32}$~', query_str('token', 64)) ? query_str('token', 64) : '';
        if ($token === '') {
            flash('err', '验证链接无效。');
            respond(view('auth/login', ['title' => '登录', 'old' => []]));
        }
        $st = db()->prepare('SELECT * FROM email_verifies WHERE token = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch();
        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            flash('err', '验证链接无效或已过期，请重新发送验证邮件。');
            respond(view('auth/login', ['title' => '登录', 'old' => []]));
        }
        $upd = db()->prepare("UPDATE users SET status = 1 WHERE id = ? AND status = 0");
        $upd->execute([$row['uid']]);
        $del = db()->prepare('DELETE FROM email_verifies WHERE uid = ?');
        $del->execute([$row['uid']]);
        flash('ok', '邮箱验证成功，请登录。');
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }

    /* 重发验证邮件 */
    if ($path === '/auth/resend' && ($method === 'POST' || $method === 'GET')) {
        mail_ensure_tables();
        if ($method === 'GET') {
            respond(view('auth/resend', ['title' => '重发验证邮件', 'old' => []]));
        }
        csrf_check();
        $email = post_str('email', 120);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('err', '请输入注册时使用的邮箱。');
            respond(view('auth/resend', ['title' => '重发验证邮件', 'old' => ['email' => $email]]), 422);
        }
        $st = db()->prepare('SELECT id, username, status FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        /* 不暴露账号是否存在：一律走同一提示 */
        $msg = '如果该邮箱需要验证，重发邮件已发送，请查收。';
        if ($u && (int) $u['status'] === 0) {
            $cnt = (int) db_val('SELECT COUNT(*) FROM email_verifies WHERE uid = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [(int) $u['id']]);
            if ($cnt >= 3) {
                flash('err', '发送过于频繁，请 1 小时后再试。');
                respond(view('auth/resend', ['title' => '重发验证邮件', 'old' => ['email' => $email]]), 429);
            }
            mail_ensure_tables();
            $token = bin2hex(random_bytes(16));
            $ins = db()->prepare('INSERT INTO email_verifies (uid, email, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))');
            $ins->execute([(int) $u['id'], $email, $token]);
            $link = site_base_url() . '/auth/verify?token=' . $token;
            [$ok, $err] = mail_send_verify($email, (string) $u['username'], $link);
            if (!$ok) {
                flash('err', '发送失败：' . $err);
                respond(view('auth/resend', ['title' => '重发验证邮件', 'old' => ['email' => $email]]), 503);
            }
            $msg = '验证邮件已重新发送，请查收（48 小时内有效）。';
        }
        flash('ok', $msg);
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }

    if ($path === '/auth/logout') {
        if ($method === 'POST') {
            csrf_check();
        }
        auth_logout(NC_AUTH_COOKIE, 0);
        flash('ok', '已退出登录。');
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }

    /* 找回密码：SMTP 可用则自助重置，否则提示联系管理员 */
    if ($path === '/auth/forgot' && $method === 'GET') {
        respond(view('auth/forgot', ['title' => '找回密码', 'old' => []]));
    }
    if ($path === '/auth/forgot' && $method === 'POST') {
        if (!smtp_ready()) {
            flash('err', '暂未开通自助找回通道，请联系管理员。');
            respond(view('auth/forgot', ['title' => '找回密码', 'old' => []]), 422);
        }
        csrf_check();
        if (!captcha_check()) {
            flash('err', '图形验证码不正确。');
            respond(view('auth/forgot', ['title' => '找回密码', 'old' => ['email' => post_str('email', 120)]]), 422);
        }
        $email = strtolower(trim(post_str('email', 120)));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('err', '请输入注册时使用的邮箱。');
            respond(view('auth/forgot', ['title' => '找回密码', 'old' => ['email' => $email]]), 422);
        }
        $st = db()->prepare('SELECT id, username FROM users WHERE email = ? AND status = 1 LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        /* 不暴露账号是否存在：一律同一提示 */
        $msg = '如果该邮箱已注册，重置链接已发送，请查收（30 分钟内有效）。';
        if ($u) {
            $cnt = (int) db_val('SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$email]);
            if ($cnt >= 3) {
                flash('err', '发送过于频繁，请 1 小时后再试。');
                respond(view('auth/forgot', ['title' => '找回密码', 'old' => ['email' => $email]]), 429);
            }
            mail_ensure_tables();
            $token = bin2hex(random_bytes(32));
            $ins = db()->prepare('INSERT INTO password_resets (uid, email, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
            $ins->execute([(int) $u['id'], $email, hash('sha256', $token)]);
            $link = site_base_url() . '/auth/reset?token=' . $token;
            [$ok, $err] = mail_send_reset($email, (string) $u['username'], $link);
            if (!$ok) {
                flash('err', '重置邮件发送失败：' . $err . '。请稍后重试或联系管理员。');
                respond(view('auth/forgot', ['title' => '找回密码', 'old' => ['email' => $email]]), 500);
            }
        }
        flash('ok', $msg);
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }

    /* 重置密码：凭邮件链接 token 设置新密码 */
    if ($path === '/auth/reset' && ($method === 'GET' || $method === 'POST')) {
        mail_ensure_tables();
        if ($method === 'POST') {
            csrf_check();
        }
        $token = preg_match('~^[a-f0-9]{64}$~', $_POST['token'] ?? ($_GET['token'] ?? '')) ? ($_POST['token'] ?? $_GET['token']) : '';
        $row = null;
        if ($token !== '') {
            $st = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
            $st->execute([hash('sha256', (string) $token)]);
            $row = $st->fetch();
        }
        if ($method === 'GET') {
            if (!$row) {
                flash('err', '重置链接无效或已过期，请重新申请找回密码。');
                respond(view('auth/forgot', ['title' => '找回密码', 'old' => []]));
            }
            respond(view('auth/reset', ['title' => '设置新密码', 'resetToken' => $token, 'resetEmail' => $row['email']]));
        }
        if (!$row) {
            flash('err', '重置链接无效或已过期，请重新申请找回密码。');
            respond(view('auth/forgot', ['title' => '找回密码', 'old' => []]), 422);
        }
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');
        if (strlen($password) < 8 || strlen($password) > 72) {
            flash('err', '新密码长度需为 8-72 位。');
            respond(view('auth/reset', ['title' => '设置新密码', 'resetToken' => $token, 'resetEmail' => $row['email']]), 422);
        }
        if ($password !== $password2) {
            flash('err', '两次输入的密码不一致。');
            respond(view('auth/reset', ['title' => '设置新密码', 'resetToken' => $token, 'resetEmail' => $row['email']]), 422);
        }
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['uid']]);
        $done = db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        $done->execute([(int) $row['id']]);
        /* 重置成功后强制该账号所有已登录设备下线 */
        $wipe = db()->prepare('DELETE FROM login_tokens WHERE user_id = ? AND is_admin = 0');
        $wipe->execute([(int) $row['uid']]);
        flash('ok', '密码已重置，请使用新密码登录。');
        respond(view('auth/login', ['title' => '登录', 'old' => []]));
    }

    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
