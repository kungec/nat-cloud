<?php
declare(strict_types=1);

// NAT Cloud - front controller
define('NC_ROOT', dirname(__DIR__));

require NC_ROOT . '/app/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// 自定义管理后台路径时，把页面里的 /admin 链接整体重写为随机路径
if (NC_INSTALLED && NC_ADMIN_PATH !== '/admin') {
    ob_start(static function (string $html): string {
        $ct = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $ct = $h;
                break;
            }
        }
        if ($ct !== '' && stripos($ct, 'text/html') === false && stripos($ct, 'application/json') === false && stripos($ct, 'text/plain') === false) {
            return $html;
        }
        $p = NC_ADMIN_PATH;
        /* 顺序：HTML 双引号形态 → JS 单引号形态 → 精确属性 → JSON 转义形态 */
        $html = str_replace(
            ['"/admin/', "'/admin/", '="/admin"', '\/admin\/'],
            ['"' . $p . '/', "'" . $p . "/", '="' . $p . '"', '\\' . $p . '\\/'],
            $html
        );
        return $html;
    });
}

// 静态资源与站点文件由 nginx 直接服务；这里只处理应用路由
route_dispatch($uri, $method);
