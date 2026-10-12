<?php
declare(strict_types=1);

/* NAT Cloud - 极简路由：精确/模式匹配分发 */

function route_dispatch(string $uri, string $method): void
{
    $path = rtrim($uri, '/');
    if ($path === '') {
        $path = '/';
    }

    /* 静态资源直出（nginx 未接管时的兜底） */
    if (preg_match('~^/assets/~', $path) || $path === '/favicon.ico' || $path === '/robots.txt') {
        respond('', 404);
    }

    /* 未安装：安装向导接管一切请求 */
    if (!NC_INSTALLED) {
        require NC_ROOT . '/app/pages/install.php';
        install_handle($path, $method);
        return;
    }

    /* 已安装：向导与探测端点永久关闭 */
    if ($path === '/install' || strpos($path, '/install/') === 0 || $path === '/nc-rewrite-check') {
        respond(view('errors/404', ['title' => '页面不存在']), 404);
    }

    $page = null;
    $params = [];

    if ($path === '/') {
        require NC_ROOT . '/app/pages/public.php';
        page_home();
    }

    /* 图形验证码（挂在 /auth 下，避免被前置网关拦截） */
    if ($path === '/auth/captcha') {
        require NC_ROOT . '/app/captcha.php';
        exit;
    }

    if (strpos($path, '/auth') === 0) {
        require NC_ROOT . '/app/pages/auth.php';
        auth_handle($path, $method);
    }

    if (strpos($path, '/dashboard') === 0) {
        require NC_ROOT . '/app/pages/dash.php';
        dash_handle($path, $method);
    }

    if (strpos($path, NC_ADMIN_PATH) === 0) {
        require NC_ROOT . '/app/pages/admin.php';
        admin_handle('/admin' . substr($path, strlen(NC_ADMIN_PATH)), $method);
    }

    if (strpos($path, '/news') === 0) {
        require NC_ROOT . '/app/pages/public.php';
        news_handle($path, $method);
    }

    if ($path === '/api/price') {
        require NC_ROOT . '/app/pages/api.php';
        api_price();
    }

    if ($path === '/ticket-file') {
        require NC_ROOT . '/app/pages/dash.php';
        ticket_file();
    }

    if (strpos($path, '/pay') === 0) {
        require NC_ROOT . '/app/pages/pay.php';
        pay_handle($path, $method);
    }

    if ($path === '/captcha') {
        require NC_ROOT . '/app/captcha.php';
        exit;
    }

    if ($path === '/api/api') {
        require NC_ROOT . '/app/pages/api.php';
        api_node();
    }

    respond(view('errors/404', ['title' => '页面不存在']), 404);
}
