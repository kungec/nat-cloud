<?php
declare(strict_types=1);

/* NAT Cloud - SMTP 邮件发送（原生 socket，免依赖）+ 注册邮箱验证 */

/** SMTP 是否已配置到可用状态 */
function smtp_ready(): bool
{
    return trim(setting('smtp_host', '')) !== ''
        && trim(setting('smtp_from', '') !== '' ? setting('smtp_from', '') : setting('smtp_user', '')) !== '';
}

function site_base_url(): string
{
    return codepay_site_base();
}

/** 站点是否能执行图片验证码（GD 可用） */
function captcha_available(): bool
{
    static $gd = null;
    if ($gd === null) {
        $gd = function_exists('imagecreatetruecolor');
    }
    return $gd;
}

/** 校验图形验证码（一次性；GD 不可用时自动降级放行） */
function captcha_check(): bool
{
    if (!captcha_available()) {
        return true;
    }
    $got = post_str('captcha', 8);
    $expect = (string) ($_SESSION['captcha'] ?? '');
    unset($_SESSION['captcha']);
    return $expect !== '' && $got !== '' && strcasecmp($got, $expect) === 0;
}

function mail_ensure_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db_exec("CREATE TABLE IF NOT EXISTS email_verifies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      uid INT UNSIGNED NOT NULL,
      email VARCHAR(120) NOT NULL DEFAULT '',
      token VARCHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_ev_token (token),
      KEY idx_ev_uid (uid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_exec("CREATE TABLE IF NOT EXISTS password_resets (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      uid INT UNSIGNED NOT NULL,
      email VARCHAR(120) NOT NULL DEFAULT '',
      token_hash CHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      used TINYINT UNSIGNED NOT NULL DEFAULT 0,
      KEY idx_pr_token (token_hash),
      KEY idx_pr_uid (uid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** 编码邮件头里的非 ASCII 部分 */
function mail_encode_header(string $s): string
{
    if (preg_match('~[\x80-\xff]~', $s)) {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
    return $s;
}

/** 从 socket 读一行 SMTP 响应（支持多行 250-xxx） */
function smtp_read_line($fp): string
{
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_expect($fp, string $prefix): bool
{
    $resp = smtp_read_line($fp);
    return strpos($resp, $prefix) === 0;
}

/**
 * 发送 HTML 邮件（SMTP）。
 * 返回 [bool 是否成功, string 失败原因]。
 */
function mail_send(string $to, string $subject, string $html): array
{
    $host = trim(setting('smtp_host', ''));
    $port = (int) setting('smtp_port', '465');
    if ($port <= 0) {
        $port = 465;
    }
    $user = trim(setting('smtp_user', ''));
    $pass = secret_decrypt(trim(setting('smtp_pass', '')));
    $from = trim(setting('smtp_from', '') !== '' ? setting('smtp_from', '') : $user);
    $fromName = setting('smtp_fromname', '') !== '' ? setting('smtp_fromname', '') : setting('site_name', 'NAT Cloud');
    $enc = strtolower(setting('smtp_encrypt', 'ssl'));

    if ($host === '' || $from === '') {
        return [false, 'SMTP 未配置'];
    }
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 12);
    if (!$fp) {
        return [false, '连接 SMTP 服务器失败：' . $errstr];
    }
    stream_set_timeout($fp, 12);

    $greet = smtp_read_line($fp);
    if (strpos($greet, '220') !== 0) {
        fclose($fp);
        return [false, 'SMTP 问候异常：' . trim($greet)];
    }
    fwrite($fp, "EHLO " . (parse_url(codepay_site_base(), PHP_URL_HOST) ?: 'localhost') . "\r\n");
    $ehlo = smtp_read_line($fp);
    if (strpos($ehlo, '250') !== 0) {
        fwrite($fp, "HELO localhost\r\n");
        smtp_read_line($fp);
    }
    if ($enc === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        if (!smtp_expect($fp, '220')) {
            fclose($fp);
            return [false, 'STARTTLS 失败'];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return [false, 'TLS 加密握手失败'];
        }
        fwrite($fp, "EHLO localhost\r\n");
        smtp_read_line($fp);
    }
    if ($user !== '') {
        fwrite($fp, "AUTH LOGIN\r\n");
        if (!smtp_expect($fp, '334')) {
            fclose($fp);
            return [false, 'SMTP 不支持 AUTH LOGIN'];
        }
        fwrite($fp, base64_encode($user) . "\r\n");
        if (!smtp_expect($fp, '334')) {
            fclose($fp);
            return [false, 'SMTP 账号被拒绝'];
        }
        fwrite($fp, base64_encode($pass) . "\r\n");
        if (!smtp_expect($fp, '235')) {
            fclose($fp);
            return [false, 'SMTP 认证失败（账号或密码/授权码不正确）'];
        }
    }
    fwrite($fp, 'MAIL FROM:<' . $from . ">\r\n");
    if (!smtp_expect($fp, '250')) {
        fclose($fp);
        return [false, '发件人被拒绝'];
    }
    fwrite($fp, 'RCPT TO:<' . $to . ">\r\n");
    $rcpt = smtp_read_line($fp);
    if (strpos($rcpt, '250') !== 0 && strpos($rcpt, '251') !== 0) {
        fclose($fp);
        return [false, '收件人被拒绝：' . trim($rcpt)];
    }
    fwrite($fp, "DATA\r\n");
    if (!smtp_expect($fp, '354')) {
        fclose($fp);
        return [false, 'DATA 被拒绝'];
    }
    $headers = 'From: ' . mail_encode_header($fromName) . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . mail_encode_header($subject) . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n";
    $body = chunk_split(base64_encode($html));
    $data = $headers . "\r\n" . $body . "\r\n.";
    fwrite($fp, $data . "\r\n");
    $done = smtp_read_line($fp);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    if (strpos($done, '250') !== 0) {
        return [false, '发送被拒绝：' . trim($done)];
    }
    return [true, ''];
}

/** 注册验证邮件（含验证链接） */
function mail_send_verify(string $to, string $username, string $link): array
{
    $site = setting('site_name', 'NAT Cloud');
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
        . '<h2 style="color:#111;">注册验证</h2>'
        . '<p>你好 <b>' . e($username) . '</b>，感谢注册 ' . e($site) . '。</p>'
        . '<p>请点击下方链接完成邮箱验证（48 小时内有效）：</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p style="color:#888;font-size:12px;">如非本人操作请忽略本邮件。该链接验证通过后账号即正式启用。</p>'
        . '</div>';
    return mail_send($to, $site . ' 注册验证（48 小时内有效）', $html);
}

/** 找回密码邮件（含一次性重置链接，30 分钟有效） */
function mail_send_reset(string $to, string $username, string $link): array
{
    $site = setting('site_name', 'NAT Cloud');
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
        . '<h2 style="color:#111;">找回密码</h2>'
        . '<p>你好 <b>' . e($username) . '</b>，我们收到了你重置 ' . e($site) . ' 账号密码的请求。</p>'
        . '<p>请点击下方链接设置新密码（30 分钟内有效）：</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p style="color:#888;font-size:12px;">如非本人操作请忽略本邮件，账号密码不会有任何变化。重置成功后所有已登录设备将被强制下线。</p>'
        . '</div>';
    return mail_send($to, $site . ' 密码重置（30 分钟内有效）', $html);
}

/** 实例到期提醒邮件（到期前 7/3/1 天） */
function mail_send_expiry(string $to, string $username, string $instName, string $expire, int $days, string $link): array
{
    $site = setting('site_name', 'NAT Cloud');
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
        . '<h2 style="color:#111;">实例到期提醒</h2>'
        . '<p>你好 <b>' . e($username) . '</b>，你的实例 <b>' . e($instName) . '</b> 将于 <b>' . e($expire) . '</b> 到期（剩余约 ' . (int) $days . ' 天）。</p>'
        . '<p>到期后实例将停机，超过宽限期未续费将被释放回收，数据无法找回。可登录控制台续费：</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p style="color:#888;font-size:12px;">开启余额自动续费后，只要账户余额充足即可自动续期，无需手动操作。</p>'
        . '</div>';
    return mail_send($to, $site . ' 实例到期提醒（剩余 ' . (int) $days . ' 天）', $html);
}

/** 余额自动续费成功回执 */
function mail_send_autorenew(string $to, string $username, string $instName, float $charge, string $resultMsg): array
{
    $site = setting('site_name', 'NAT Cloud');
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
        . '<h2 style="color:#111;">自动续费成功</h2>'
        . '<p>你好 <b>' . e($username) . '</b>，你的实例 <b>' . e($instName) . '</b> 已自动续费。</p>'
        . '<p>本次扣费 <b>¥' . e(money($charge)) . '</b>（从账户余额代扣），' . e($resultMsg) . '。实例保持正常运行，无需任何操作。</p>'
        . '<p><a href="' . codepay_site_base() . '/dashboard/services">在控制台查看实例</a></p>'
        . '<p style="color:#888;font-size:12px;">本邮件由系统自动发送。如需关闭自动续费请联系管理员。</p>'
        . '</div>';
    return mail_send($to, $site . ' 实例自动续费成功', $html);
}

/** 工单新回复通知（管理员回复后发给工单所有者） */
function mail_send_ticket_reply(string $to, string $username, int $ticketId, string $ticketTitle): array
{
    $site = setting('site_name', 'NAT Cloud');
    $link = codepay_site_base() . '/dashboard/tickets/' . (int) $ticketId;
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;">'
        . '<h2 style="color:#111;">工单新回复</h2>'
        . '<p>你好 <b>' . e($username) . '</b>，你的工单 <b>#' . (int) $ticketId . ' ' . e($ticketTitle) . '</b> 有新的客服回复。</p>'
        . '<p>请登录控制台查看详情并继续沟通：</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p style="color:#888;font-size:12px;">本邮件由系统自动发送，请勿直接回复。</p>'
        . '</div>';
    return mail_send($to, $site . ' 工单 #' . (int) $ticketId . ' 有新回复', $html);
}
