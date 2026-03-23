<?php
/**
 * PeachtreesCMS API - CORS 跨域配置
 */

// 允许的来源 (生产环境应限制为具体域名)
$allowedOrigins = [
    'http://localhost:5173',  // Vite 开发服务器
    'http://localhost:3000',  // 备用端口
    'http://localhost',       // 本地生产环境
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, strict: true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // 开发环境允许所有来源，生产环境应注释掉此行
    header("Access-Control-Allow-Origin: *");
}

// 允许的 HTTP 方法
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// 允许的请求头
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 允许携带凭证 (cookies)
header("Access-Control-Allow-Credentials: true");

// 预检请求缓存时间 (秒)
header("Access-Control-Max-Age: 86400");

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
