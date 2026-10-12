<?php
declare(strict_types=1);

/* NAT Cloud - 公共助手：转义、URL、CSRF、闪存、视图渲染
 * 约定：所有跳转都在各控制器内使用固定字面量路径完成，
 * 本文件不提供通用跳转函数，从根源上排除开放重定向。
 */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

/* ---------- 多语言（zh/en，渲染后整页词典替换） ---------- */
function lang(): string
{
    $c = $_COOKIE['nc_lang'] ?? 'zh';
    return $c === 'en' ? 'en' : 'zh';
}

function lang_replace(string $html): string
{
    if (lang() === 'zh' || !empty($GLOBALS['NC_NO_LANG'])) {
        return $html;
    }
    static $map = null;
    if ($map === null) {
        $map = include NC_ROOT . '/app/lang_en.php';
        uksort($map, function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });
    }
    foreach ($map as $zh => $en) {
        if ($zh !== '' && $zh !== $en) {
            $html = str_replace($zh, $en, $html);
        }
    }
    return $html;
}

/* ---------- 线性 SVG 图标（stroke: currentColor） ---------- */
function nc_icon(string $name, int $size = 18): string
{
    static $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'layers' => '<path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'receipt' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'headset' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm18 0h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-5Z"/><path d="M3 14v-3a9 9 0 0 1 18 0v3"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1Z"/><path d="m9 12 2 2 4-4"/>',
        'gear' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3Z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'lang' => '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
        'tray' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'menu' => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'warn' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'pencil' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'sliders' => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'cpu2' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/>',
        'ram2' => '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 17v3M12 17v3M17 17v3"/>',
        'disk2' => '<rect x="3" y="8" width="18" height="8" rx="2"/><path d="M7 12h.01M11 12h.01"/>',
        'net2' => '<rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><path d="M8 12h8"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'terminal' => '<path d="m4 17 6-6-6-6"/><path d="M12 19h8"/>',
        'server' => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01"/><path d="M6 17.5h.01"/>',
    ];
    $d = $paths[$name] ?? $paths['grid'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(20));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        echo '页面已过期，请刷新后重试。(CSRF token invalid)';
        exit;
    }
}

/* ---------- 闪存 ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- 视图 ---------- */
function view(string $template, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include NC_ROOT . '/app/views/' . $template . '.php';
    if (strpos($template, 'admin/') === 0) {
        $GLOBALS['NC_NO_LANG'] = true; /* 管理后台不参与前台多语言替换 */
    }
    return (string) ob_get_clean();
}

function respond(string $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo lang_replace($body);
    exit;
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function post_str(string $key, int $maxLen = 500): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim($v);
    if (mb_strlen($v) > $maxLen) {
        $v = mb_substr($v, 0, $maxLen);
    }
    return $v;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    if (!is_string($v) && !is_int($v)) {
        return $default;
    }
    return (int) $v;
}

function query_str(string $key, int $maxLen = 200): string
{
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim($v);
    return mb_strlen($v) > $maxLen ? mb_substr($v, 0, $maxLen) : $v;
}

/* ---------- 格式化 ---------- */
function money($amount): string
{
    return number_format((float) $amount, 2, '.', ',');
}

function money4($amount): string
{
    return number_format((float) $amount, 4, '.', '');
}

/* 镜像 slug 转展示名：debian11 → Debian 11，ubuntu-22-04 → Ubuntu 22-04 */
function os_label(string $key): string
{
    $key = strtolower(trim($key));
    static $names = [
        'debian' => 'Debian', 'ubuntu' => 'Ubuntu', 'centos' => 'CentOS', 'almalinux' => 'AlmaLinux',
        'alma' => 'AlmaLinux', 'rocky' => 'Rocky Linux', 'rhel' => 'RHEL', 'fedora' => 'Fedora',
        'opensuse' => 'openSUSE', 'suse' => 'SUSE', 'arch' => 'Arch Linux', 'alpine' => 'Alpine',
        'windows' => 'Windows', 'gentoo' => 'Gentoo', 'nixos' => 'NixOS', 'void' => 'Void Linux',
        'kali' => 'Kali', 'manjaro' => 'Manjaro', 'oracle' => 'Oracle Linux',
    ];
    if (preg_match('~^([a-z]+)[\s_-]?(\d.*)?$~', $key, $m)) {
        $name = $names[$m[1]] ?? ucfirst($m[1]);
        return isset($m[2]) ? $name . ' ' . $m[2] : $name;
    }
    return $key;
}

/* 节点可装镜像列表（natnode images_json 每分钟上报到 nodes.images），无数据返回空数组 */
function node_images(int $nodeId): array
{
    if ($nodeId <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare('SELECT images FROM nodes WHERE id = ? LIMIT 1');
        $stmt->execute([$nodeId]);
        $raw = (string) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $list = array_values(array_filter($decoded, function ($v) {
        return is_string($v) && preg_match('~^[a-z0-9][a-z0-9-]{0,31}$~', $v);
    }));
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

/* ---------- 实名认证（KYC）---------- */
/* kyc 表与 plans.kyc_required 列自愈（三态：0=跟随全局 1=需要 2=不需要） */
function kyc_ensure_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db_val('SELECT certify_id FROM kyc LIMIT 1');
    } catch (Throwable $e) {
        db_exec("ALTER TABLE kyc ADD COLUMN certify_id VARCHAR(64) NULL, ADD COLUMN provider VARCHAR(20) NOT NULL DEFAULT '', ADD COLUMN cert_url VARCHAR(500) NULL, ADD COLUMN passed_at DATETIME NULL, ADD KEY idx_kyc_certify (certify_id)");
    }
    try {
        db_val('SELECT kyc_required FROM plans LIMIT 1');
    } catch (Throwable $e) {
        db_exec('ALTER TABLE plans ADD COLUMN kyc_required TINYINT NOT NULL DEFAULT 0');
    }
}

/* 套餐实名要求判定：套餐显式设置优先，否则跟随全局开关 */
function kyc_required_for_plan(array $plan): bool
{
    $mode = (int) ($plan['kyc_required'] ?? 0);
    if ($mode === 1) {
        return true;
    }
    if ($mode === 2) {
        return false;
    }
    return setting('kyc_global_required', '0') === '1';
}

/* 系统镜像线性图标：按镜像名识别发行版（与 nc_icon 同风格，stroke currentColor），未识别用服务器图标 */
function os_icon(string $os, int $size = 16): string
{
    $o = strtolower($os);
    static $paths = [
        'debian' => '<path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7.5a4.5 4.5 0 1 0 4.5 4.5"/><path d="M12 10.8a1.2 1.2 0 1 0 .01 0"/>',
        'ubuntu' => '<circle cx="12" cy="5.6" r="2.2"/><circle cx="5.9" cy="17.4" r="2.2"/><circle cx="18.1" cy="17.4" r="2.2"/><path d="M10.9 7.6 7.1 15.5M13.1 7.6l3.8 7.9M8.1 17.4h7.8"/>',
        'centos' => '<path d="M18.6 6.4A8.6 8.6 0 1 0 18.6 17.6"/><path d="M12 8.3v7.4"/>',
        'fedora' => '<path d="M12 3a9 9 0 1 0 9 9c0-3.4-2.4-5.4-5.3-5.4-2.9 0-4.7 1.9-4.7 4.2s1.7 3.7 3.8 3.7"/>',
        'suse' => '<circle cx="12" cy="12" r="8.6"/><path d="M8.2 10.2h.01M15.8 10.2h.01M8.5 14.5c2.2 1.6 4.8 1.6 7 0"/>',
        'arch' => '<path d="M12 3 4.6 20.4h4.2L12 13.2l3.2 7.2h4.2z"/><path d="M12 8.5v1.5"/>',
        'alpine' => '<path d="m3 18 5.8-9.8 3.3 5.1 2.9-5.1L21 18z"/>',
        'windows' => '<path d="M3 5.5 10.5 4.4v7.1H3zM12.5 4.2 21 3v8.5h-8.5zM3 13.5h7.5v7.1L3 19.5zM12.5 13.5H21V21l-8.5-1.2z"/>',
        'server' => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01"/><path d="M6 17.5h.01"/>',
    ];
    $d = $paths['server'];
    $match = [
        'debian' => ['debian', 'kali', 'mx', 'raspbian'],
        'ubuntu' => ['ubuntu', 'mint', 'pop'],
        'centos' => ['centos', 'alma', 'rocky', 'rhel', 'oracle'],
        'fedora' => ['fedora'],
        'suse' => ['suse'],
        'arch' => ['arch', 'manjaro', 'endeavour'],
        'alpine' => ['alpine', 'void', 'nixos', 'gentoo'],
        'windows' => ['windows', 'win'],
    ];
    foreach ($match as $key => $words) {
        foreach ($words as $w) {
            if (strpos($o, $w) !== false) {
                $d = $paths[$key];
                break 2;
            }
        }
    }
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-3px;opacity:.8">' . $d . '</svg>';
}

/* VNC 控制台：实例当前有效会话（未过期返回凭据，否则 null）。列不存在时按无会话处理。 */function vnc_active(array $inst): ?array
{
    if (empty($inst['vnc_token']) || empty($inst['vnc_expire_at'])) {
        return null;
    }
    if (strtotime((string) $inst['vnc_expire_at']) <= time()) {
        return null;
    }
    return [
        'url' => 'http://' . $inst['ip'] . ':' . (30000 + max(2, (int) $inst['ip_octet'])) . '/',
        'user' => 'nc',
        'token' => (string) $inst['vnc_token'],
        'expire_at' => (string) $inst['vnc_expire_at'],
    ];
}

function dt($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return date('Y-m-d H:i', strtotime((string) $value));
}

function mask_name(string $name): string
{
    $len = mb_strlen($name);
    if ($len <= 1) {
        return '*';
    }
    return mb_substr($name, 0, 1) . str_repeat('*', max(1, $len - 1));
}

function mask_id_card(string $id): string
{
    $len = strlen($id);
    if ($len < 8) {
        return str_repeat('*', $len);
    }
    return substr($id, 0, 4) . str_repeat('*', $len - 8) . substr($id, -4);
}

function mask_phone(string $phone): string
{
    $len = strlen($phone);
    if ($len < 7) {
        return str_repeat('*', $len);
    }
    return substr($phone, 0, 3) . str_repeat('*', $len - 7) . substr($phone, -4);
}

/* ---------- 敏感字段加密（实名信息） ---------- */
function secret_encrypt(string $plain): string
{
    $key = hash('sha256', env('APP_KEY', 'natcloud-fallback-key'), true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-128-gcm', substr($key, 0, 16), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function secret_decrypt(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $key = hash('sha256', env('APP_KEY', 'natcloud-fallback-key'), true);
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-128-gcm', substr($key, 0, 16), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

/* ---------- 随机单号 / 实例名 ---------- */
function random_instance_name(): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $suffix = '';
    for ($i = 0; $i < 8; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'nat-' . $suffix;
}

function random_order_no(): string
{
    return date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function user_agent(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return '未知设备';
    }
    if (strpos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'Safari') !== false) {
        $browser = 'Safari';
    } else {
        $browser = preg_match('~^\w[\w/.-]{0,30}~', $ua, $m) ? $m[0] : '未知设备';
    }
    $os = '其他系统';
    if (strpos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (strpos($ua, 'Mac OS') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    return $browser . ' · ' . $os;
}

/* ---------- 后台列表分页条（GET 参数翻页，保留既有查询串） ---------- */
function admin_render_pager(int $page, int $pages, int $total, array $keep = []): string
{
    if ($pages <= 1) {
        return '<div class="pager"><span class="pager-info">共 ' . $total . ' 条</span></div>';
    }
    $qs = function (int $p) use ($keep): string {
        $params = array_merge($keep, ['page' => $p]);
        return '?' . http_build_query($params);
    };
    $html = '<div class="pager"><span class="pager-info">共 ' . $total . ' 条 · 第 ' . $page . ' / ' . $pages . ' 页</span><span class="pager-links">';
    $html .= $page > 1 ? '<a class="pager-btn" href="' . $qs($page - 1) . '">‹ 上一页</a>' : '<span class="pager-btn off">‹ 上一页</span>';
    $start = max(1, $page - 3);
    $end = min($pages, $start + 6);
    $start = max(1, $end - 6);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $page
            ? '<span class="pager-btn on">' . $i . '</span>'
            : '<a class="pager-btn" href="' . $qs($i) . '">' . $i . '</a>';
    }
    $html .= $page < $pages ? '<a class="pager-btn" href="' . $qs($page + 1) . '">下一页 ›</a>' : '<span class="pager-btn off">下一页 ›</span>';
    $html .= '</span></div>';
    return $html;
}
