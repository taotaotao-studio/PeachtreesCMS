<?php
/**
 * PeachtreesCMS API - 验证码接口
 * GET /api/captcha.php
 */

require_once __DIR__ . '/config.php';

// 启动 session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置响应头
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 生成四位数字验证码
$code = '';
for ($i = 0; $i < 4; $i++) {
    $code .= mt_rand(0, 9);
}

// 保存到 session
$_SESSION['captcha'] = $code;

// 图片尺寸
$width = 120;
$height = 40;

// 创建画布
$image = imagecreatetruecolor($width, $height);

// 设置背景色（随机淡色）
$bgColor = imagecolorallocate($image, mt_rand(220, 240), mt_rand(220, 240), mt_rand(220, 240));
imagefill($image, 0, 0, $bgColor);

// 添加干扰线
for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
}

// 添加干扰点
for ($i = 0; $i < 50; $i++) {
    $dotColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
}

// 绘制验证码（使用内置字体）
$fontSize = 5;
$fontWidth = imagefontwidth($fontSize);

// 计算每个字符的位置
$charWidth = $width / 4;

for ($i = 0; $i < 4; $i++) {
    // 随机颜色
    $textColor = imagecolorallocate($image, mt_rand(50, 120), mt_rand(50, 120), mt_rand(50, 120));
    
    // 计算字符位置（确保是整数）
    $x = intval($i * $charWidth + ($charWidth - $fontWidth) / 2);
    $y = mt_rand(10, 20);
    
    // 绘制字符
    imagestring($image, $fontSize, $x, $y, $code[$i], $textColor);
}

// 输出图片
imagepng($image);

// 释放内存
imagedestroy($image);
exit;