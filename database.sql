-- PeachtreesCMS Database Schema
-- 数据库结构文件
-- 创建日期: 2026-02-28

-- 创建数据库
CREATE DATABASE IF NOT EXISTS peachtrees DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE peachtrees;

-- --------------------------------------------------------
-- 用户表 users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认管理员账号 (密码: 111111)
INSERT INTO users (id, username, email, password_hash, created_at, last_login_at) VALUES
(1, 'admin', 'admin@peachtrees.com', '$2y$12$YBnqh4Wjt6sEqYZkaKb7LOqx.k460jVm5aasKSwAQdfSAOSylGPLm', NOW(), NOW());

-- --------------------------------------------------------
-- 标签分类表 tags
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tag VARCHAR(50) NOT NULL,
    display_name VARCHAR(50) NOT NULL,
    post_count INT(11) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认标签
INSERT INTO tags (id, tag, display_name, post_count) VALUES
(1, 'news', '新闻', 0),
(2, 'tech', '技术', 0),
(3, 'life', '生活', 0);

-- --------------------------------------------------------
-- 文章表 posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tag VARCHAR(50) DEFAULT NULL,
    post_type ENUM('normal', 'big-picture') NOT NULL DEFAULT 'normal' COMMENT '文章类型',
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(200) DEFAULT NULL COMMENT '自定义URL标识',
    summary TEXT DEFAULT NULL COMMENT '文章简介（大片文章展示在封面底部）',
    cover_media JSON DEFAULT NULL COMMENT '大图文章封面媒体列表（图片或mp4）',
    content TEXT,
    allow_comments TINYINT(1) DEFAULT 1 COMMENT '是否允许评论 0-不允许 1-允许',
    active TINYINT(1) DEFAULT 1 COMMENT '是否发布 0-下架 1-发布',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY tag (tag),
    CONSTRAINT posts_ibfk_1 FOREIGN KEY (tag) REFERENCES tags(tag) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入示例文章
INSERT INTO posts (id, tag, post_type, title, summary, cover_media, content, created_at, updated_at) VALUES
(1, 'news', 'normal', '欢迎使用 PeachtreesCMS', '', NULL, '<p>这是一个基于 PHP 的内容管理系统。</p><p>现已升级为前后端分离架构，使用 Vite + React + Bootstrap 构建。</p>', NOW(), NOW()),
(2, 'tech', 'normal', '系统功能介绍', '', NULL, '<p>系统支持以下功能：</p><ul><li>文章管理</li><li>分类标签</li><li>用户管理</li><li>RSS 订阅</li></ul>', NOW(), NOW());

-- 更新标签计数
UPDATE tags t SET post_count = (SELECT COUNT(*) FROM posts WHERE tag = t.tag);

-- --------------------------------------------------------
-- 主题表 themes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS themes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL COMMENT '主题目录名',
    name VARCHAR(100) NOT NULL COMMENT '主题名称',
    description VARCHAR(255) DEFAULT NULL COMMENT '主题描述',
    version VARCHAR(50) DEFAULT NULL COMMENT '版本号',
    author VARCHAR(100) DEFAULT NULL COMMENT '作者',
    entry_css VARCHAR(255) NOT NULL DEFAULT 'style.css' COMMENT '入口CSS文件',
    thumbnail VARCHAR(255) DEFAULT 'thumbnail.svg' COMMENT '主题缩略图文件名',
    is_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否当前激活',
    user_layout_config JSON DEFAULT NULL COMMENT '用户自定义布局配置',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    last_scanned_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_slug (slug),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 评论用户表 comment_users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS comment_users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 留言用户白名单表 commenter_whitelist
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS commenter_whitelist (
    id INT(11) NOT NULL AUTO_INCREMENT,
    comment_user_id INT(11) NOT NULL COMMENT '关联 comment_users.id',
    status ENUM('trusted', 'blocked') NOT NULL DEFAULT 'trusted' COMMENT 'trusted=可信自动通过, blocked=禁止留言',
    reason VARCHAR(255) DEFAULT NULL COMMENT '原因备注',
    expires_at DATETIME DEFAULT NULL COMMENT '到期时间，NULL 表示永久',
    created_by INT(11) DEFAULT NULL COMMENT '操作人 users.id',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_comment_user_id (comment_user_id),
    KEY idx_status_expires (status, expires_at),
    KEY idx_created_by (created_by),
    CONSTRAINT commenter_whitelist_ibfk_1 FOREIGN KEY (comment_user_id) REFERENCES comment_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT commenter_whitelist_ibfk_2 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 评论表 comments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    post_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    status TINYINT(1) DEFAULT 0 COMMENT '评论状态 0-待审核 1-已通过 2-已拒绝',
    parent_id INT(11) DEFAULT NULL COMMENT '父评论ID，用于回复评论',
    ip VARCHAR(45) DEFAULT NULL COMMENT '评论者IP地址',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY post_id (post_id),
    KEY user_id (user_id),
    KEY status (status),
    CONSTRAINT comments_ibfk_1 FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT comments_ibfk_2 FOREIGN KEY (user_id) REFERENCES comment_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- --------------------------------------------------------
-- 设置表 options
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS options (
    id INT(11) NOT NULL AUTO_INCREMENT,
    option_key VARCHAR(100) NOT NULL,
    option_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY option_key (option_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认设置
INSERT IGNORE INTO options (option_key, option_value) VALUES 
('site_title', 'PeachtreesCMS'),
('footer_text', '© 2026 PeachtreesCMS. All rights reserved.'),
('default_lang', 'zh-CN');
