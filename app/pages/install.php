<?php
declare(strict_types=1);

/* NAT Cloud - one-click installer wizard (router hands over ALL requests while not installed)
 * Flow: env self-check (auto-try permissions) -> database + admin -> write .env and install.lock
 * After install a RANDOM admin path (ADMIN_PATH) is generated, unique per deployment.
 * Security notes: schema/seed import runs through app/install_import.php (mysqli);
 * values written into .env must pass install_dbident()/install_sv() whitelists;
 * data values always use parameter binding (?). */

function install_handle(string $path, string $method): void
{
    if ($path === '/nc-rewrite-check') {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'REWRITE_OK';
        exit;
    }

    /* static assets are served by nginx (404 here also powers the run-dir probe) */
    if (preg_match('~^/assets/~', $path) || $path === '/favicon.ico' || $path === '/robots.txt') {
        http_response_code(404);
        exit('Not Found');
    }

    if ($method === 'POST') {
        install_run();
        return;
    }
    install_render_check();
}

/* identifier whitelist: letters/digits/underscore, max 64. throws otherwise. */
function install_dbident(string $name): string
{
    if (!preg_match('~^[A-Za-z0-9_]{1,64}$~', $name)) {
        throw new RuntimeException('数据库名不合法');
    }
    return $name;
}

/* config value whitelist: strip CR/LF/NUL then full-match the given pattern. */
function install_sv(string $v, string $pattern): string
{
    $v = str_replace(["\r", "\n", "\0"], '', $v);
    if (!preg_match($pattern, $v)) {
        throw new RuntimeException('配置值不合法');
    }
    return $v;
}

/* connect to the target database: db name passes the whitelist before entering DSN */
function install_connect(array $in): PDO
{
    $dsn = 'mysql:host=' . install_sv($in['db_host'], '~^[A-Za-z0-9.\-]{0,120}$~')
        . ';port=' . install_sv($in['db_port'], '~^[0-9]{0,5}$~')
        . ';dbname=' . install_dbident($in['db_name'])
        . ';charset=utf8mb4';
    $pdo = new PDO($dsn, install_sv($in['db_user'], '~^[A-Za-z0-9._\-]{0,64}$~'), install_sv($in['db_pass'], '~^[^\x00]{0,128}$~'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
    $chk = $pdo->query('SELECT 1');
    if ($chk) {
        $chk->closeCursor();
    }
    return $pdo;
}

/* ---------- environment checks ---------- */
function install_requirements(): array
{
    $root = NC_ROOT;
    /* auto-try: create storage dirs and loosen permissions (idempotent) */
    foreach ([$root . '/storage', $root . '/storage/tmp'] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
    }
    foreach ([$root, $root . '/storage', $root . '/storage/tmp', $root . '/install'] as $d) {
        if (is_dir($d) && !is_writable($d)) {
            @chmod($d, 0755);
        }
    }

    return [
        ['PHP ≥ 7.4（当前 ' . PHP_VERSION . '）', version_compare(PHP_VERSION, '7.4.0', '>='), '请升级 PHP 至 7.4 或以上（宝塔：软件商店 → PHP 切换版本）'],
        ['PDO MySQL 扩展', extension_loaded('pdo_mysql'), '宝塔：软件商店 → 对应 PHP → 安装 pdo_mysql'],
        ['MySQLi 扩展', extension_loaded('mysqli'), '宝塔：软件商店 → 对应 PHP → 安装 mysqli'],
        ['cURL 扩展', extension_loaded('curl'), '宝塔：软件商店 → 对应 PHP → 安装 curl'],
        ['GD 扩展（图形验证码）', extension_loaded('gd'), '宝塔：软件商店 → 对应 PHP → 安装 gd'],
        ['OpenSSL 扩展（数据加密）', extension_loaded('openssl'), '宝塔：软件商店 → 对应 PHP → 安装 openssl'],
        ['Session 可用', session_status() === PHP_SESSION_ACTIVE, '检查 php.ini 的 session 配置'],
        ['根目录可写（写入 .env）', is_writable($root), '宝塔：文件管理 → 站点根目录 → 权限 755，属主 www'],
        ['install 目录可写（写入安装锁）', is_writable($root . '/install'), '宝塔：文件管理 → install 目录 → 权限 755，属主 www'],
        ['storage 目录可写（工单临时文件）', is_writable($root . '/storage/tmp'), '宝塔：文件管理 → storage 目录 → 权限 755，属主 www'],
    ];
}

function install_all_pass(array $rows): bool
{
    foreach ($rows as $r) {
        if (!$r[1]) {
            return false;
        }
    }
    return true;
}

/* ---------- step 1: self-check page ---------- */
function install_render_check(string $err = '', array $old = []): void
{
    $rows = install_requirements();
    $allOk = install_all_pass($rows);
    $token = csrf_token();
    $oldEsc = function (string $k) use ($old): string {
        return e((string) ($old[$k] ?? ''));
    };
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装 NAT Cloud</title>
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
<link rel="stylesheet" href="/assets/css/main.css">
<style>
  .ins-wrap { max-width: 760px; margin: 48px auto; padding: 0 16px; }
  .ins-card { background: var(--card); border: 1px solid var(--border-soft); border-radius: 14px; padding: 28px; }
  .ins-logo { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 20px; margin-bottom: 4px; }
  .ins-sub { color: var(--text-dim); font-size: 13.5px; margin-bottom: 22px; }
  .ins-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 14px; border: 1px solid var(--border-soft); border-radius: 10px; margin-bottom: 8px; font-size: 14px; }
  .ins-row .st { flex: none; font-size: 12.5px; font-weight: 600; padding: 2px 10px; border-radius: 999px; }
  .st-ok { color: #0a7d33; background: rgba(16, 163, 74, .12); }
  .st-fail { color: #b3261e; background: rgba(179, 38, 30, .10); }
  .ins-hint { font-size: 12.5px; color: var(--text-faint); margin: -4px 0 10px; padding: 0 4px; }
  .ins-err { border: 1px solid rgba(179,38,30,.4); background: rgba(179,38,30,.08); color: #b3261e; border-radius: 10px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 16px; }
  .field label { font-size: 13px; color: var(--text-dim); display: block; margin: 0 0 6px 2px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .grid2 .field.full { grid-column: 1 / -1; }
  @media (max-width: 620px) { .grid2 { grid-template-columns: 1fr; } }
  .ins-note { font-size: 12.5px; color: var(--text-faint); line-height: 1.8; margin-top: 18px; border-top: 1px dashed var(--border-soft); padding-top: 14px; }
  pre.ins-code { background: var(--bg-soft, #f5f6f8); border: 1px solid var(--border-soft); border-radius: 8px; padding: 10px 12px; font-size: 12px; overflow-x: auto; white-space: pre; }
</style>
</head>
<body>
<div class="ins-wrap">
  <div class="ins-card">
    <div class="ins-logo">☁ NAT Cloud · 安装向导</div>
    <div class="ins-sub">第 1 步 / 共 2 步 —— 环境与权限自检（失败项已尝试自动设置，仍失败的按提示处理后刷新本页）</div>

    <?php if ($err !== ''): ?><div class="ins-err"><?= e($err) ?></div><?php endif; ?>

    <h3 style="font-size:15px;margin:0 0 12px;">服务器环境</h3>
    <?php foreach ($rows as $r): ?>
      <div class="ins-row"><span><?= e($r[0]) ?></span><span class="st <?= $r[1] ? 'st-ok' : 'st-fail' ?>"><?= $r[1] ? '通过' : '未通过' ?></span></div>
      <?php if (!$r[1]): ?><div class="ins-hint">处理建议：<?= e($r[2]) ?></div><?php endif; ?>
    <?php endforeach; ?>

    <h3 style="font-size:15px;margin:20px 0 12px;">Web 配置探测</h3>
    <div class="ins-row"><span>伪静态路由（try_files）</span><span class="st" id="stRewrite">检测中…</span></div>
    <div class="ins-hint" id="hintRewrite" style="display:none;">检测失败：请在宝塔「网站 → 设置 → 伪静态」中添加以下配置后刷新本页——<pre class="ins-code">location / { try_files $uri $uri/ /index.php?$query_string; }</pre></div>
    <div class="ins-row"><span>运行目录 /public（网站目录）</span><span class="st" id="stRun">检测中…</span></div>
    <div class="ins-hint" id="hintRun" style="display:none;">检测失败：请在宝塔「网站 → 设置 → 网站目录」把运行目录设置为 <b>/public</b> 并保存后刷新本页。</div>

    <h3 style="font-size:15px;margin:20px 0 12px;">数据库与管理员</h3>
    <div class="ins-hint" style="margin-bottom:12px;">请先在宝塔「数据库」页面创建数据库（或使用已有数据库），再填写下方信息。</div>
    <form method="post" action="/install" id="insForm">
      <input type="hidden" name="_csrf" value="<?= $token ?>">
      <div class="grid2">
        <div class="field"><label>数据库地址</label><input class="input" type="text" name="db_host" value="<?= $oldEsc('db_host') ?: '127.0.0.1' ?>" required></div>
        <div class="field"><label>数据库端口</label><input class="input" type="text" name="db_port" value="<?= $oldEsc('db_port') ?: '3306' ?>" required></div>
        <div class="field"><label>数据库名</label><input class="input" type="text" name="db_name" value="<?= $oldEsc('db_name') ?>" placeholder="仅字母/数字/下划线" required></div>
        <div class="field"><label>数据库用户名</label><input class="input" type="text" name="db_user" value="<?= $oldEsc('db_user') ?>" placeholder="宝塔默认与库名同名" required></div>
        <div class="field full"><label>数据库密码</label><input class="input" type="password" name="db_pass" value="" placeholder="留空表示无密码"></div>
        <div class="field"><label>管理员用户名</label><input class="input" type="text" name="admin_user" value="<?= $oldEsc('admin_user') ?>" placeholder="3-32 位字母/数字/下划线" required></div>
        <div class="field"><label>站点名称（可选）</label><input class="input" type="text" name="site_name" value="<?= $oldEsc('site_name') ?: 'NAT Cloud' ?>" maxlength="60"></div>
        <div class="field"><label>管理员密码</label><input class="input" type="password" name="admin_pass" minlength="8" maxlength="72" required></div>
        <div class="field"><label>确认管理员密码</label><input class="input" type="password" name="admin_pass2" minlength="8" maxlength="72" required></div>
      </div>
      <div style="margin-top:20px;display:flex;align-items:center;gap:12px;">
        <button class="btn btn-primary btn-lg" type="submit" id="insBtn"<?= $allOk ? '' : ' disabled' ?>>开始安装</button>
        <span style="font-size:12.5px;color:var(--text-faint);">安装耗时约 2-5 秒；完成后将展示随机生成的管理后台地址。</span>
      </div>
    </form>

    <div class="ins-note">
      安全说明：安装完成后系统自动在站点根目录生成 <b>install.lock</b>，本向导即永久关闭；<br>
      配置文件 .env 权限将设为 600，仅站点进程可读；管理后台地址为随机生成，请安装完成后妥善保存。
    </div>
  </div>
</div>
<script>
(function () {
  function probe(url, okText, stId, hintId, next) {
    fetch(url, { cache: 'no-store' }).then(function (r) { return r.text().then(function (t) { return { s: r.status, t: t }; }); }).then(function (r) {
      var ok = r.s === 200 && (!okText || r.t.indexOf(okText) !== -1);
      var st = document.getElementById(stId);
      st.textContent = ok ? '通过' : '未通过';
      st.className = 'st ' + (ok ? 'st-ok' : 'st-fail');
      if (!ok) document.getElementById(hintId).style.display = '';
      next(ok);
    }).catch(function () {
      var st = document.getElementById(stId);
      st.textContent = '未通过'; st.className = 'st st-fail';
      document.getElementById(hintId).style.display = '';
      next(false);
    });
  }
  var rw = false, run = false;
  function gate() {
    var allOk = <?= $allOk ? 'true' : 'false' ?>;
    document.getElementById('insBtn').disabled = !(allOk && rw && run);
  }
  probe('/nc-rewrite-check', 'REWRITE_OK', 'stRewrite', 'hintRewrite', function (ok) { rw = ok; gate(); });
  probe('/assets/css/main.css', null, 'stRun', 'hintRun', function (ok) { run = ok; gate(); });
  document.getElementById('insForm').addEventListener('submit', function () {
    var b = document.getElementById('insBtn');
    b.disabled = true; b.textContent = '正在安装…';
  });
})();
</script>
</body>
</html><?php
}

/* ---------- step 2: run the install ---------- */
function install_run(): void
{
    csrf_check();
    $rows = install_requirements();
    if (!install_all_pass($rows)) {
        install_render_check('环境自检存在未通过项，请处理后重试。', []);
        return;
    }

    $in = [
        'db_host' => trim((string) ($_POST['db_host'] ?? '')),
        'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? '')),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'admin_user' => trim((string) ($_POST['admin_user'] ?? '')),
        'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
        'admin_pass2' => (string) ($_POST['admin_pass2'] ?? ''),
        'site_name' => trim((string) ($_POST['site_name'] ?? '')),
    ];

    $err = '';
    if (!preg_match('~^[A-Za-z0-9.\-_]{1,120}$~', $in['db_host'])) {
        $err = '数据库地址格式不正确';
    } elseif (!preg_match('~^[0-9]{1,5}$~', $in['db_port']) || (int) $in['db_port'] < 1 || (int) $in['db_port'] > 65535) {
        $err = '数据库端口格式不正确';
    } elseif (!preg_match('~^[A-Za-z0-9_]{1,64}$~', $in['db_name'])) {
        $err = '数据库名仅允许字母、数字与下划线';
    } elseif (!preg_match('~^[A-Za-z0-9._\-]{1,64}$~', $in['db_user'])) {
        $err = '数据库用户名不合法';
    } elseif (!preg_match('~^[^\r\n\x00]{0,128}$~', $in['db_pass'])) {
        $err = '数据库密码不合法';
    } elseif (!preg_match('~^[A-Za-z0-9_]{3,32}$~', $in['admin_user'])) {
        $err = '管理员用户名需为 3-32 位字母、数字或下划线';
    } elseif (strlen($in['admin_pass']) < 8 || strlen($in['admin_pass']) > 72) {
        $err = '管理员密码长度需在 8-72 位之间';
    } elseif ($in['admin_pass'] !== $in['admin_pass2']) {
        $err = '两次输入的管理员密码不一致';
    } elseif (mb_strlen($in['site_name']) > 60) {
        $err = '站点名称过长';
    }

    /* 先用 PDO 做连接测试（错误信息更友好），通过后再交给 mysqli 导入器建表 */
    $pdo = null;
    if ($err === '') {
        try {
            $pdo = install_connect($in);
        } catch (PDOException $e) {
            $msg = install_pdo_msg($e);
            if (strpos($msg, '1049') !== false || strpos(strtolower($msg), 'unknown database') !== false) {
                $err = '数据库 ' . $in['db_name'] . ' 不存在：请先在宝塔「数据库」页面创建它（用户名可勾选“为数据库同步创建”），再回到本页安装。';
            } elseif (strpos($msg, '1045') !== false || strpos(strtolower($msg), 'access denied') !== false) {
                $err = '数据库账号或密码不正确（Access denied），请核对后重试。';
            } else {
                $err = '数据库连接失败：' . $msg;
            }
        }
    }

    if ($err === '') {
        require NC_ROOT . '/app/install_import.php';
        try {
            install_import_sql(
                install_sv($in['db_host'], '~^[A-Za-z0-9.\-]{0,120}$~'),
                install_sv($in['db_port'], '~^[0-9]{0,5}$~'),
                install_sv($in['db_user'], '~^[A-Za-z0-9._\-]{0,64}$~'),
                install_sv($in['db_pass'], '~^[^\x00]{0,128}$~'),
                install_dbident($in['db_name']),
                [NC_ROOT . '/install/schema.sql', NC_ROOT . '/install/seed.sql']
            );
        } catch (Throwable $e) {
            $err = '数据库初始化失败：' . $e->getMessage();
        }
    }

    if ($err === '') {
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$in['admin_user'], password_hash($in['admin_pass'], PASSWORD_DEFAULT)]);
            if ($in['site_name'] !== '') {
                $upd = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
                $upd->execute([$in['site_name'], 'site_name']);
            }
        } catch (PDOException $e) {
            /* re-running install with the same admin is treated as idempotent */
            if (strpos((string) $e->getCode(), '23') !== 0 && strpos(strtolower($e->getMessage()), 'duplicate') === false) {
                $err = '管理员创建失败：' . install_pdo_msg($e);
            }
        }
    }

    if ($err !== '') {
        $in['admin_pass'] = '';
        $in['admin_pass2'] = '';
        install_render_check($err, $in);
        return;
    }

    /* random admin path + APP_KEY, then write .env (whitelist-checked values) and lock */
    $adminPath = '/admin-' . bin2hex(random_bytes(6));
    $lines = [
        'DB_HOST' => install_sv($in['db_host'], '~^[A-Za-z0-9.\-]{0,120}$~'),
        'DB_PORT' => install_sv($in['db_port'], '~^[0-9]{0,5}$~'),
        'DB_NAME' => install_dbident($in['db_name']),
        'DB_USER' => install_sv($in['db_user'], '~^[A-Za-z0-9._\-]{0,64}$~'),
        'DB_PASS' => install_sv($in['db_pass'], '~^[^\r\n\x00]{0,128}$~'),
        'APP_KEY' => bin2hex(random_bytes(32)),
        'ADMIN_PATH' => install_sv($adminPath, '~^/admin-[0-9a-f]{12}$~'),
    ];
    $pairs = [];
    foreach ($lines as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    file_put_contents(NC_ROOT . '/.env', implode("\n", $pairs) . "\n");
    @chmod(NC_ROOT . '/.env', 0600);
    file_put_contents(NC_ROOT . '/install.lock', json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '1.0',
    ]));
    @chmod(NC_ROOT . '/install.lock', 0644);

    install_render_done($lines['ADMIN_PATH'], $in['admin_user']);
}

function install_pdo_msg(PDOException $e): string
{
    $m = $e->getMessage();
    $m = preg_replace('~SQLSTATE\[[^\]]+\]\s*\[\d+\]\s*~', '', $m);
    return trim((string) explode(' in ', $m)[0]);
}

/* ---------- done page ---------- */
function install_render_done(string $adminPath, string $adminUser): void
{
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $base = ($https ? 'https://' : 'http://') . $host;
    $adminUrl = $base . $adminPath;
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装完成 · NAT Cloud</title>
<link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
<link rel="stylesheet" href="/assets/css/main.css">
<style>
  .ins-wrap { max-width: 720px; margin: 48px auto; padding: 0 16px; }
  .ins-card { background: var(--card); border: 1px solid var(--border-soft); border-radius: 14px; padding: 28px; }
  .done-ico { width: 52px; height: 52px; border-radius: 50%; background: rgba(16,163,74,.12); color: #0a7d33; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; margin-bottom: 14px; }
  .url-box { border: 1px solid var(--border-soft); border-radius: 10px; padding: 14px 16px; margin: 10px 0 16px; }
  .url-box .k { font-size: 12.5px; color: var(--text-faint); margin-bottom: 4px; }
  .url-box .v { font-size: 16px; font-weight: 700; word-break: break-all; }
  .url-box.warn { border-color: rgba(176,124,0,.5); background: rgba(240,180,0,.08); }
  ol.ins-next { margin: 8px 0 0; padding-left: 20px; font-size: 13.5px; line-height: 2; color: var(--text-dim); }
  pre.ins-code { background: var(--bg-soft, #f5f6f8); border: 1px solid var(--border-soft); border-radius: 8px; padding: 10px 12px; font-size: 12px; overflow-x: auto; white-space: pre; }
</style>
</head>
<body>
<div class="ins-wrap">
  <div class="ins-card">
    <div class="done-ico">✓</div>
    <h1 style="font-size:22px;margin:0 0 6px;">安装完成！</h1>
    <p style="font-size:13.5px;color:var(--text-dim);margin:0 0 18px;">数据库已初始化，管理员已创建，配置已写入 .env（权限 600），install.lock 已生成并向导永久关闭。</p>

    <div class="url-box warn">
      <div class="k">🔑 管理后台地址（随机生成，请立即保存，仅此一次展示）</div>
      <div class="v"><?= e($adminUrl) ?></div>
      <div class="k" style="margin-top:8px;">管理员用户名：<b><?= e($adminUser) ?></b>（密码为你刚才设置的密码）</div>
    </div>
    <div class="url-box">
      <div class="k">🌐 网站前台</div>
      <div class="v"><?= e($base) ?>/</div>
    </div>

    <h3 style="font-size:15px;margin:20px 0 8px;">接下来建议完成</h3>
    <ol class="ins-next">
      <li>立即打开管理后台地址并用管理员账号登录（建议再改一次密码）；</li>
      <li>在宝塔「计划任务」中添加 Shell 脚本任务（按小时计费与流量重置依赖它们）：
        <pre class="ins-code">* * * * * /www/server/php/74/bin/php 站点根目录/cron/bill.php
5 0 * * * /www/server/php/74/bin/php 站点根目录/cron/traffic_reset.php</pre>
        （把「站点根目录」替换为实际路径，如 /www/wwwroot/你的域名）
      </li>
      <li>在「系统设置」配置支付渠道与 SMTP 邮件；</li>
      <li>在「节点管理」添加节点，执行生成的一键安装命令完成节点对接；</li>
      <li>编辑或删除演示套餐，绑定真实节点后即可开售。</li>
    </ol>
    <div style="margin-top:22px;display:flex;gap:10px;">
      <a class="btn btn-primary" href="<?= e($adminPath) ?>/login">进入管理后台</a>
      <a class="btn btn-ghost" href="/">访问网站前台</a>
    </div>
  </div>
</div>
</body>
</html><?php
}
