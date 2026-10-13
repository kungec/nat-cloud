<?php
declare(strict_types=1);

/* NAT Cloud - 公开页面：首页 / 新闻公告 / 服务状态 / 条款与隐私 */

function page_static_handle(string $path): void
{
    if ($path === '/status') {
        try {
            db_exec('ALTER TABLE nodes ADD COLUMN last_seen DATETIME NULL');
        } catch (Throwable $e) {
            /* 列已存在 */
        }
        $nodes = [];
        foreach (db_all('SELECT id, name, status, last_seen, metrics_json FROM nodes ORDER BY sort ASC, id ASC') as $n) {
            $seen = $n['last_seen'] ? strtotime((string) $n['last_seen']) : 0;
            $online = (int) $n['status'] === 1 && $seen > time() - 300;
            $mJson = (string) ($n['metrics_json'] ?? '');
            $m = $mJson !== '' ? json_decode($mJson, true) : null;
            $nodes[] = [
                'name' => (string) $n['name'],
                'enabled' => (int) $n['status'] === 1,
                'online' => $online,
                'last_seen' => $n['last_seen'] ?: '',
                'm' => is_array($m) ? $m : [],
            ];
        }
        $news = db_all('SELECT id, title, created_at FROM news WHERE status = 1 ORDER BY id DESC LIMIT 5');
        respond(view('status', ['title' => '服务状态 · ' . setting('site_name', 'NAT Cloud'), 'nodes' => $nodes, 'news' => $news]));
    }
    if ($path === '/terms' || $path === '/privacy') {
        $isTerms = $path === '/terms';
        $html = setting($isTerms ? 'tos_html' : 'privacy_html', '');
        $title = $isTerms ? '服务条款' : '隐私政策';
        respond(view('page_html', ['title' => $title . ' · ' . setting('site_name', 'NAT Cloud'), 'pageTitle' => $title, 'html' => $html]));
    }
}

function page_home(): void
{
    $plans = db_all('SELECT * FROM plans WHERE status = 1 ORDER BY sort ASC, id ASC LIMIT 12');
    foreach ($plans as &$p) {
        $p['stock_left'] = billing_plan_stock_left($p);
    }
    unset($p);
    $news = db_all('SELECT id, title, created_at FROM news WHERE status = 1 ORDER BY id DESC LIMIT 4');
    $stats = [
        ['label' => '平均开通时间', 'value' => '< 60 秒'],
        ['label' => '网络可用性', 'value' => '99.9%'],
        ['label' => '计费粒度', 'value' => '按小时'],
    ];
    respond(view('home', [
        'title' => setting('site_name', 'NAT Cloud') . ' · 轻量 NAT 云主机',
        'plans' => $plans,
        'news' => $news,
        'stats' => $stats,
    ]));
}

function news_handle(string $path, string $method): void
{
    if ($path === '/news') {
        $rows = db_all('SELECT * FROM news WHERE status = 1 ORDER BY id DESC LIMIT 50');
        respond(view('news', ['title' => '公告动态', 'rows' => $rows]));
    }
    if (preg_match('~^/news/(\d+)$~', $path, $m)) {
        $stmt = db()->prepare('SELECT * FROM news WHERE id = ? AND status = 1 LIMIT 1');
        $stmt->execute([(int) $m[1]]);
        $row = $stmt->fetch();
        if (!$row) {
            respond(view('errors/404', ['title' => '公告不存在']), 404);
        }
        respond(view('news_detail', ['title' => $row['title'], 'row' => $row]));
    }
    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
