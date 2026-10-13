<?php
declare(strict_types=1);

/* NAT Cloud - Telegram 通知（bot 私聊）
 * 用户在控制台获取绑定码，向 bot 发送 /start 绑定码 完成绑定；
 * cron 轮询 getUpdates 匹配绑定码；事件（开通/续费/到期提醒/工单回复）推送到绑定会话。 */

function tg_ready(): bool
{
    return setting('tg_enabled', '0') === '1' && trim(setting('tg_bot_token', '')) !== '';
}

function tg_bot_token(): string
{
    return secret_decrypt(trim(setting('tg_bot_token', '')));
}

function tg_ensure_columns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach ([
        'SELECT tg_chat_id FROM users LIMIT 1' => 'ALTER TABLE users ADD COLUMN tg_chat_id VARCHAR(32) NULL',
        'SELECT tg_bind_code FROM users LIMIT 1' => 'ALTER TABLE users ADD COLUMN tg_bind_code VARCHAR(16) NULL',
    ] as $probe => $alter) {
        try {
            db_val($probe);
        } catch (Throwable $e) {
            db_exec($alter);
        }
    }
}

/** 调用 Bot API（GET 查询或 POST JSON） */
function tg_api(string $method, array $params = []): ?array
{
    $token = tg_bot_token();
    if ($token === '') {
        return null;
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);
    if ($params) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp)) {
        return null;
    }
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

/** 给指定 chat 发文本 */
function tg_send(string $chatId, string $text): bool
{
    if ($chatId === '' || !tg_ready()) {
        return false;
    }
    $r = tg_api('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => true]);
    return is_array($r) && !empty($r['ok']);
}

/** 给用户发通知（未绑定则静默跳过） */
function tg_notify_user(int $uid, string $text): void
{
    if (!tg_ready()) {
        return;
    }
    tg_ensure_columns();
    $chatId = (string) db_val('SELECT tg_chat_id FROM users WHERE id = ? LIMIT 1', [$uid]);
    if ($chatId !== '') {
        tg_send($chatId, $text);
    }
}

/** 生成（或复用）当前用户的绑定码 */
function tg_bind_code(int $uid): string
{
    tg_ensure_columns();
    $code = (string) db_val('SELECT tg_bind_code FROM users WHERE id = ? LIMIT 1', [$uid]);
    if ($code === '') {
        $code = 'NC' . strtoupper(bin2hex(random_bytes(4)));
        $upd = db()->prepare('UPDATE users SET tg_bind_code = ? WHERE id = ?');
        $upd->execute([$code, $uid]);
    }
    return $code;
}

/** 轮询 getUpdates 匹配 /start 绑定码（cron 每 5 分钟调用） */
function tg_poll_bindings(): int
{
    if (!tg_ready()) {
        return 0;
    }
    tg_ensure_columns();
    $offset = (int) setting('tg_update_offset', '0');
    $r = tg_api('getUpdates', []);
    if (!is_array($r) || empty($r['ok']) || !is_array($r['result'])) {
        return 0;
    }
    $maxId = $offset;
    $bound = 0;
    foreach ($r['result'] as $upd) {
        $updateId = (int) ($upd['update_id'] ?? 0);
        if ($updateId >= $maxId) {
            $maxId = $updateId + 1;
        }
        $text = (string) ($upd['message']['text'] ?? '');
        $chatId = (string) ($upd['message']['chat']['id'] ?? '');
        if ($chatId === '' || strpos($text, '/start') !== 0) {
            continue;
        }
        $code = strtoupper(trim(substr($text, 6)));
        if ($code === '' || !preg_match('~^NC[A-F0-9]{8}$~', $code)) {
            tg_send($chatId, '绑定码格式不正确。请在控制台「Telegram 绑定」中复制完整绑定码后，发送 /start 绑定码');
            continue;
        }
        $stmt = db()->prepare('SELECT id, username FROM users WHERE tg_bind_code = ? AND (tg_chat_id IS NULL OR tg_chat_id = ?) LIMIT 1');
        $stmt->execute([$code, $chatId]);
        $u = $stmt->fetch();
        if ($u) {
            $upd2 = db()->prepare('UPDATE users SET tg_chat_id = ? WHERE id = ?');
            $upd2->execute([$chatId, (int) $u['id']]);
            tg_send($chatId, '✅ 绑定成功，' . $u['username'] . '。之后实例开通、续费、到期提醒与工单回复都会在这里通知你。');
            $bound++;
        } else {
            tg_send($chatId, '绑定码无效或已被绑定。请到控制台重新获取。');
        }
    }
    if ($maxId > $offset) {
        setting_save('tg_update_offset', (string) $maxId);
    }
    return $bound;
}
