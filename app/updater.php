<?php
declare(strict_types=1);

/* NAT Cloud - 后台一键更新（从 GitHub 主分支同步最新版本）
 *
 * 流程：检查最新提交（10 分钟缓存）→ 下载官方 tarball → 解压校验 →
 *       旧程序文件整体挪入备份目录 → 新文件原子换入 → 记录版本号 → 刷新 OPcache
 * 红线：绝不读取/覆盖 .env、install.lock 与 storage/（配置与用户数据）。
 * 更新源固定为 GitHub 官方仓库；可选「下载加速前缀」仅允许 http(s) 公网主机。
 */

const NC_UPDATE_REPO = 'kungec/nat-cloud';
/* 允许换入的顶层条目白名单：程序目录与根文件（docs/ 等 运行非必需不更新） */
const NC_UPDATE_TOP_ENTRIES = ['app', 'cron', 'install', 'public', 'web.config', 'README.md', 'README_INSTALL.md', 'LICENSE'];

function updater_version_file(): string
{
    return NC_ROOT . '/storage/version.json';
}

/** 当前安装版本（storage/version.json；发行版安装无此文件返回空标识） */
function updater_current_version(): array
{
    $raw = @file_get_contents(updater_version_file());
    $j = $raw !== false ? json_decode((string) $raw, true) : null;
    if (!is_array($j) || empty($j['sha'])) {
        return ['sha' => '', 'short' => '', 'time' => '', 'updated_at' => ''];
    }
    return [
        'sha' => (string) $j['sha'],
        'short' => substr((string) $j['sha'], 0, 7),
        'time' => (string) ($j['time'] ?? ''),
        'updated_at' => (string) ($j['updated_at'] ?? ''),
    ];
}

/** 下载加速前缀（后台可配，仅允许 http/https 公网主机，复用 codepay_host_allowed 校验） */
function updater_proxy_prefix(): string
{
    $p = rtrim(trim(setting('update_github_proxy', '')), '/');
    if ($p === '') {
        return '';
    }
    $parts = parse_url($p);
    if (!isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        || strpos($p, '@') !== false
        || !codepay_host_allowed(strtolower((string) $parts['host']))) {
        return '';
    }
    return $p;
}

function updater_http_get(string $url, int $timeout = 20): string
{
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header' => "User-Agent: nat-cloud-updater\r\nAccept: */*\r\n",
        'follow_location' => 1,
        'max_redirects' => 3,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('无法连接更新源（可尝试在下方配置下载加速前缀）');
    }
    return (string) $raw;
}

/** GitHub 最新提交（结果缓存 10 分钟，避免频繁打 API 触发限流） */
function updater_latest_commit(bool $force = false): array
{
    if (!$force) {
        $cacheTs = (int) setting('update_check_ts', '0');
        $cached = trim(setting('update_check_cache', ''));
        if ($cached !== '' && time() - $cacheTs < 600) {
            $j = json_decode($cached, true);
            if (is_array($j) && !empty($j['sha'])) {
                $j['cached'] = true;
                return $j;
            }
        }
    }
    $raw = updater_http_get('https://api.github.com/repos/' . NC_UPDATE_REPO . '/commits/main');
    $j = json_decode((string) $raw, true);
    if (!is_array($j) || empty($j['sha'])) {
        throw new RuntimeException('GitHub 接口返回异常');
    }
    $out = [
        'sha' => (string) $j['sha'],
        'short' => substr((string) $j['sha'], 0, 7),
        'message' => trim((string) explode("\n", (string) ($j['commit']['message'] ?? ''))[0]),
        'time' => substr((string) ($j['commit']['committer']['date'] ?? ''), 0, 19),
        'cached' => false,
    ];
    setting_save('update_check_cache', (string) json_encode($out, JSON_UNESCAPED_UNICODE));
    setting_save('update_check_ts', (string) time());
    return $out;
}

/** 最近提交记录（更新日志展示用） */
function updater_recent_commits(int $n = 5): array
{
    $raw = updater_http_get('https://api.github.com/repos/' . NC_UPDATE_REPO . '/commits?per_page=' . $n);
    $j = json_decode((string) $raw, true);
    $out = [];
    foreach ((array) $j as $c) {
        if (!is_array($c) || empty($c['sha'])) {
            continue;
        }
        $out[] = [
            'short' => substr((string) $c['sha'], 0, 7),
            'message' => trim((string) explode("\n", (string) ($c['commit']['message'] ?? ''))[0]),
            'time' => substr((string) ($c['commit']['committer']['date'] ?? ''), 0, 16),
        ];
    }
    return $out;
}

function updater_has_update(array $current, array $remote): bool
{
    return $current['sha'] === '' || ($remote['sha'] !== '' && $remote['sha'] !== $current['sha']);
}

/** 下载主分支 tarball（源站固定 codeload.github.com；可加加速前缀），返回文件大小 */
function updater_download_tarball(string $dest): int
{
    $url = 'https://codeload.github.com/' . NC_UPDATE_REPO . '/tar.gz/refs/heads/main';
    $proxy = updater_proxy_prefix();
    if ($proxy !== '') {
        $url = $proxy . '/' . $url;
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => 180,
        'header' => "User-Agent: nat-cloud-updater\r\n",
        'follow_location' => 1,
        'max_redirects' => 3,
    ]]);
    $in = @fopen($url, 'rb', false, $ctx);
    if (!$in) {
        throw new RuntimeException('下载失败：无法连接更新源');
    }
    $out = @fopen($dest, 'wb');
    if (!$out) {
        fclose($in);
        throw new RuntimeException('无法写入临时文件');
    }
    try {
        while (!feof($in)) {
            $chunk = fread($in, 65536);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }
    } finally {
        fclose($in);
        fclose($out);
    }
    clearstatcache(true, $dest);
    $size = (int) filesize($dest);
    if ($size < 1024) {
        throw new RuntimeException('下载内容异常（' . $size . ' 字节）');
    }
    return $size;
}

/** 递归删除目录（仅限 storage 下的临时/备份路径，调用方保证） */
function updater_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $p = $dir . '/' . $name;
        if (is_dir($p) && !is_link($p)) {
            updater_rrmdir($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}

/**
 * 解压 tarball 并剥掉 GitHub 顶层目录，返回解压根目录。
 */
function updater_extract(string $tarball, string $workDir): string
{
    if (!class_exists('PharData')) {
        throw new RuntimeException('PHP 缺少 Phar 扩展，无法自动解压（请手动下载覆盖）');
    }
    $srcDir = $workDir . '/src';
    if (!mkdir($srcDir, 0700, true) && !is_dir($srcDir)) {
        throw new RuntimeException('无法创建解压目录');
    }
    $phar = new PharData($tarball);
    $phar->extractTo($srcDir, null, true);
    /* GitHub tarball 顶层是 nat-cloud-<sha>/ 唯一目录，剥掉它 */
    $entries = scandir($srcDir) ?: [];
    $tops = array_values(array_filter($entries, function ($n) {
        return $n !== '.' && $n !== '..';
    }));
    if (count($tops) === 1 && is_dir($srcDir . '/' . $tops[0])) {
        return $srcDir . '/' . $tops[0];
    }
    return $srcDir;
}

/** 把单个顶层条目换入站点：旧条目整体挪入备份目录，新条目 rename 换入（同盘原子操作） */
function updater_swap(string $srcPath, string $sitePath, string $bakPath): void
{
    if (is_dir($sitePath) || is_file($sitePath)) {
        if (!@rename($sitePath, $bakPath)) {
            throw new RuntimeException('备份旧文件失败：' . basename($sitePath));
        }
    }
    if (!@rename($srcPath, $sitePath)) {
        /* 换入失败时把旧文件挪回去，保证站点可用 */
        if (is_dir($bakPath) || is_file($bakPath)) {
            @rename($bakPath, $sitePath);
        }
        throw new RuntimeException('换入新文件失败：' . basename($sitePath));
    }
}

/**
 * 执行更新：下载 → 解压 → 逐条目「旧挪备份、新换入」→ 记录版本 → 刷新 OPcache。
 * 返回摘要数组；任何一步失败都会抛异常（已完成条目保留在备份目录可手动恢复）。
 */
function updater_apply(string $remoteSha, string $remoteTime): array
{
    if (strtolower((string) ini_get('phar.readonly')) === '1') {
        /* phar.readonly 只限制创建 phar 归档，不影响读取 tar；这里仅作提示性检查 */
    }
    @set_time_limit(300);
    $stamp = date('YmdHis');
    $work = NC_ROOT . '/storage/update-' . $stamp;
    $bak = NC_ROOT . '/storage/backup-' . $stamp;
    if (!is_dir(NC_ROOT . '/storage')) {
        @mkdir(NC_ROOT . '/storage', 0700, true);
    }
    if (!mkdir($work, 0700, true) && !is_dir($work)) {
        throw new RuntimeException('无法创建更新工作目录');
    }

    $summary = ['entries' => [], 'files' => 0, 'backup' => 'storage/backup-' . $stamp];
    try {
        updater_download_tarball($work . '/main.tar.gz');
        $srcRoot = updater_extract($work . '/main.tar.gz', $work);
        $srcRoot = (string) realpath($srcRoot);
        $workReal = (string) realpath($work);
        if ($srcRoot === '' || $workReal === '' || strpos($srcRoot, $workReal) !== 0) {
            throw new RuntimeException('解压结果异常');
        }
        if (!is_dir($bak) && !mkdir($bak, 0700, true)) {
            throw new RuntimeException('无法创建备份目录');
        }

        foreach (NC_UPDATE_TOP_ENTRIES as $name) {
            $srcPath = $srcRoot . '/' . $name;
            if (!is_dir($srcPath) && !is_file($srcPath)) {
                continue; /* 上游没有该条目（如发行包根目录无 web.config） */
            }
            updater_swap($srcPath, NC_ROOT . '/' . $name, $bak . '/' . $name);
            $summary['entries'][] = $name;
        }
        if (in_array('app', $summary['entries'], true)) {
            $summary['files'] = updater_count_files(NC_ROOT . '/app');
        }

        /* 记录版本号 */
        $ver = [
            'sha' => $remoteSha,
            'short' => substr($remoteSha, 0, 7),
            'time' => $remoteTime,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        @file_put_contents(updater_version_file(), (string) json_encode($ver, JSON_UNESCAPED_UNICODE));

        /* 清理工作目录（保留备份） */
        updater_rrmdir($work);

        /* 刷新 OPcache：整池重置最干净（解决入口文件被钉死的问题），失败则逐文件失效 */
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        } elseif (function_exists('opcache_invalidate')) {
            foreach (['app/billing.php', 'app/pages/admin.php'] as $f) {
                @opcache_invalidate(NC_ROOT . '/' . $f, true);
            }
        }
        return $summary;
    } catch (Throwable $e) {
        updater_rrmdir($work);
        throw $e;
    }
}

function updater_count_files(string $dir): int
{
    $n = 0;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || $name === '.mimosa') {
            continue;
        }
        $p = $dir . '/' . $name;
        if (is_dir($p)) {
            $n += updater_count_files($p);
        } else {
            $n++;
        }
    }
    return $n;
}
