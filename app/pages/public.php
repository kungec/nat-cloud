<?php
declare(strict_types=1);

/* NAT Cloud - 公开页面：首页 / 新闻公告 */

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
