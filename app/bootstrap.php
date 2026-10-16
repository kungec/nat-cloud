<?php
declare(strict_types=1);

/* NAT Cloud - 应用引导：配置加载、数据库连接、会话 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

if (!defined('NC_ROOT')) {
    define('NC_ROOT', dirname(__DIR__));
}

date_default_timezone_set('Asia/Shanghai');

/* ---------- .env ---------- */
$GLOBALS['NC_ENV'] = [];
$envFile = NC_ROOT . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $GLOBALS['NC_ENV'][trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
    }
}

function env(string $key, string $default = ''): string
{
    $v = $GLOBALS['NC_ENV'][$key] ?? '';
    return $v !== '' ? $v : $default;
}

/* ---------- 安装状态与管理后台路径 ---------- */
define('NC_INSTALLED', is_file(NC_ROOT . '/install.lock'));

$ncAdminPath = env('ADMIN_PATH', '');
if (!preg_match('~^/[a-z0-9\-]{4,40}$~', $ncAdminPath) || $ncAdminPath === '/install' || $ncAdminPath === '/dashboard') {
    $ncAdminPath = '/admin';
} else {
    /* 禁止与既有路由前缀冲突（如 /auth-x 会被 /auth 分支截胡） */
    foreach (['/auth', '/dashboard', '/news', '/pay', '/api', '/captcha', '/ticket-file', '/install', '/assets', '/cron'] as $ncReserved) {
        if (strpos($ncAdminPath, $ncReserved) === 0) {
            $ncAdminPath = '/admin';
            break;
        }
    }
}
define('NC_ADMIN_PATH', $ncAdminPath);

/* ---------- 数据库连接（PDO） ----------
 * 约定：SQL 一律以字面量写在各调用点的 prepare() 里并使用 ? 占位符，
 * 任何外部输入都只能通过 execute() 的绑定数组进入查询。
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1')
            . ';port=' . env('DB_PORT', '3306')
            . ';dbname=' . env('DB_NAME')
            . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/* ---------- 会话 ---------- */
$https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.cookie_httponly', '1');
if ($https) {
    ini_set('session.cookie_secure', '1');
}
session_name('NATCLOUDSID');
session_start();

/* ---------- 安全响应头（PHP 端统一输出，避免 nginx add_header 作用域丢失） ---------- */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ---------- 助手函数 ---------- */
require __DIR__ . '/dbh.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/codepay.php';
require __DIR__ . '/pay/AlipayOfficial.php';
require __DIR__ . '/kyc/KycProviders.php';
require __DIR__ . '/pay/WxOfficial.php';
require __DIR__ . '/usdt.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/coupons.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/referral.php';
require __DIR__ . '/billing.php';
require __DIR__ . '/openapi.php';
require __DIR__ . '/updater.php';
require __DIR__ . '/router.php';

/* 首次访问懒计费：只为当前登录用户结算，代价很小 */
if (NC_INSTALLED && function_exists('auth_user') && auth_user()) {
    try {
        billing_run_user((int) auth_user()['id']);
    } catch (Throwable $e) {
        /* 计费失败不阻塞页面 */
    }
}
