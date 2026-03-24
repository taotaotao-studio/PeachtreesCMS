<?php
/**
 * PeachtreesCMS API - 更新网站设置
 * POST /api/options/update.php
 * 需要管理员权限
 */

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed', 405);
}

// 检查管理员权限
requireAdmin();

// 获取输入
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    badRequest('无效的设置数据');
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO pt_options (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = ?");
    
    foreach ($input as $key => $value) {
        $stmt->execute([$key, $value, $value]);
    }
    
    $pdo->commit();
    success(null, '设置保存成功');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    serverError('保存设置失败: ' . $e->getMessage());
}
