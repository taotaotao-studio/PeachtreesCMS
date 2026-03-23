<?php
/**
 * PeachtreesCMS API - 密码工具
 * 统一密码哈希与校验。
 */

/**
 * 生成密码哈希
 * @param string $password
 * @return string
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 校验密码
 * @param string $password 明文密码
 * @param string $storedHash 数据库存储的哈希
 * @return bool
 */
function verifyPassword(string $password, string $storedHash): bool {
    return password_verify($password, $storedHash);
}
