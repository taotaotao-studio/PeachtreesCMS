<?php
/**
 * PeachtreesCMS API - 获取网站设置
 * GET /api/options/index.php
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error('Method not allowed', 405);
}

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT option_key, option_value FROM options");
    $rows = $stmt->fetchAll();
    
    $options = [];
    foreach ($rows as $row) {
        $options[$row['option_key']] = $row['option_value'];
    }
    
    success($options);
} catch (PDOException $e) {
    serverError('获取设置失败: ' . $e->getMessage());
}
