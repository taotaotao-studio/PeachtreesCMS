<?php
/**
 * PeachtreesCMS API - Captcha Interface
 * GET /api/captcha.php
 */

require_once __DIR__ . '/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set response headers
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Generate a 5-character captcha from a confusable-free alphanumeric charset
// (no 0/O/1/l/I). Cryptographically secure selection; consumers compare case-
// insensitively, so mixed case widens the space cheaply.
$chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
$length = 5;
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

// Save to session with timestamp for expiry check
$_SESSION['captcha'] = $code;
$_SESSION['captcha_time'] = time();

// Image dimensions
$width = 120;
$height = 40;

// Create canvas
$image = imagecreatetruecolor($width, $height);

// Set background color (random light color)
$bgColor = imagecolorallocate($image, mt_rand(220, 240), mt_rand(220, 240), mt_rand(220, 240));
imagefill($image, 0, 0, $bgColor);

// Add interference lines
for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
}

// Add interference dots
for ($i = 0; $i < 50; $i++) {
    $dotColor = imagecolorallocate($image, mt_rand(150, 200), mt_rand(150, 200), mt_rand(150, 200));
    imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
}

// Draw captcha (using built-in font)
$fontSize = 5;
$fontWidth = imagefontwidth($fontSize);

// Calculate position for each character
$charWidth = $width / $length;

for ($i = 0; $i < $length; $i++) {
    // Random color
    $textColor = imagecolorallocate($image, mt_rand(50, 120), mt_rand(50, 120), mt_rand(50, 120));
    
    // Calculate character position (ensure integer)
    $x = intval($i * $charWidth + ($charWidth - $fontWidth) / 2);
    $y = mt_rand(10, 20);
    
    // Draw character
    imagestring($image, $fontSize, $x, $y, $code[$i], $textColor);
}

// Output image
imagepng($image);

// Free memory
imagedestroy($image);
exit;
