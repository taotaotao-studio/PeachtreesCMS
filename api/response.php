<?php
/**
 * PeachtreesCMS API - 统一响应处理
 */

/**
 * 返回 JSON 响应
 * @param mixed $data 响应数据
 * @param int $status HTTP 状态码
 */
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 成功响应
 * @param mixed $data 数据
 * @param string $message 消息
 */
function success(mixed $data = null, string $message = 'Success'): never {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * 错误响应
 * @param string $message 错误消息
 * @param int $status HTTP 状态码
 */
function error(string $message = 'Error', int $status = 400): never {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'data' => null
    ], $status);
}

/**
 * 未授权响应
 * @param string $message 消息
 */
function unauthorized(string $message = 'Unauthorized'): never {
    error($message, 401);
}

/**
 * 禁止访问响应
 * @param string $message 消息
 */
function forbidden(string $message = 'Forbidden'): never {
    error($message, 403);
}

/**
 * 未找到响应
 * @param string $message 消息
 */
function notFound(string $message = 'Not Found'): never {
    error($message, 404);
}

/**
 * 服务器错误响应
 * @param string $message 消息
 */
function serverError(string $message = 'Internal Server Error'): never {
    error($message, 500);
}

/**
 * 获取 JSON 请求体
 * @return array|null
 */
function getJsonInput(): ?array {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * 获取请求参数 (支持 JSON body 和 GET/POST)
 * @param string $key 参数名
 * @param mixed $default 默认值
 * @return mixed
 */
function getParam(string $key, mixed $default = null): mixed {
    // 优先从 JSON body 获取
    $json = getJsonInput();
    if (isset($json[$key])) {
        return $json[$key];
    }
    // 其次从 GET 获取
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    // 最后从 POST 获取
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    return $default;
}
