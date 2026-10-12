<?php
declare(strict_types=1);

/* NAT Cloud - 图形验证码输出（/captcha.png）
 * 4 位随机码写入会话，GD 绘制噪线 + 干扰点；GD 不可用时返回 1x1 占位图。 */

if (PHP_SAPI === 'cli') {
    exit("CLI only\n");
}

$pool = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 4; $i++) {
    $code .= $pool[random_int(0, strlen($pool) - 1)];
}
$_SESSION['captcha'] = $code;

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!function_exists('imagecreatetruecolor')) {
    /* GD 不可用：输出 1x1 透明占位（表单按 captcha_available() 降级放行） */
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
}

$w = 108;
$h = 36;
$im = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($im, 244, 245, 247);
imagefilledrectangle($im, 0, 0, $w, $h, $bg);

/* 干扰线 */
for ($i = 0; $i < 6; $i++) {
    $c = imagecolorallocate($im, random_int(140, 200), random_int(140, 200), random_int(150, 210));
    imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
}
/* 干扰点 */
for ($i = 0; $i < 160; $i++) {
    $c = imagecolorallocate($im, random_int(100, 210), random_int(100, 210), random_int(110, 220));
    imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $c);
}
/* 字符（逐个绘制 + 上下抖动） */
$x = 12;
for ($i = 0; $i < strlen($code); $i++) {
    $shade = random_int(30, 110);
    $c = imagecolorallocate($im, $shade, $shade + random_int(0, 15), $shade + random_int(0, 25));
    imagestring($im, 5, $x, random_int(4, 12), $code[$i], $c);
    $x += 22;
}
imagepng($im);
imagedestroy($im);
