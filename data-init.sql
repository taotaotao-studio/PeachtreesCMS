-- --------------------------------------------------------
-- 主机:                           127.0.0.1
-- 服务器版本:                        5.7.44-log - MySQL Community Server (GPL)
-- 服务器操作系统:                      Win64
-- HeidiSQL 版本:                  12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- 导出 peachtrees 的数据库结构
CREATE DATABASE IF NOT EXISTS `peachtrees` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `peachtrees`;

-- 导出  表 peachtrees.pt_commenter_whitelist 结构
CREATE TABLE IF NOT EXISTS `pt_commenter_whitelist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_user_id` int(11) NOT NULL COMMENT '关联 comment_users.id',
  `status` enum('trusted','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trusted' COMMENT 'trusted=可信自动通过, blocked=禁止留言',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原因备注',
  `expires_at` datetime DEFAULT NULL COMMENT '到期时间，NULL 表示永久',
  `created_by` int(11) DEFAULT NULL COMMENT '操作人 users.id',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_comment_user_id` (`comment_user_id`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `pt_commenter_whitelist_ibfk_1` FOREIGN KEY (`comment_user_id`) REFERENCES `pt_comment_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pt_commenter_whitelist_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `pt_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_commenter_whitelist 的数据：~0 rows (大约)

-- 导出  表 peachtrees.pt_comments 结构
CREATE TABLE IF NOT EXISTS `pt_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '0' COMMENT '评论状态 0-待审核 1-已通过 2-已拒绝',
  `parent_id` int(11) DEFAULT NULL COMMENT '父评论ID，用于回复评论',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '评论者IP地址',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `pt_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `pt_posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pt_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `pt_comment_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_comments 的数据：~0 rows (大约)

-- 导出  表 peachtrees.pt_comment_users 结构
CREATE TABLE IF NOT EXISTS `pt_comment_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_comment_users 的数据：~0 rows (大约)

-- 导出  表 peachtrees.pt_options 结构
CREATE TABLE IF NOT EXISTS `pt_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `option_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `option_key` (`option_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_options 的数据：~3 rows (大约)
INSERT IGNORE INTO `pt_options` (`id`, `option_key`, `option_value`, `created_at`, `updated_at`) VALUES
	(1, 'site_title', 'PeachtreesCMS', '2026-03-24 17:18:52', '2026-03-24 17:18:52'),
	(2, 'footer_text', '<p>© 2026 Powered by <a target="_blank" rel="noopener noreferrer" href="https://github.com/taotaotao-studio/PeachtreesCMS">PeachtreesCMS</a>.</p>', '2026-03-24 17:18:52', '2026-03-24 17:18:52'),
	(3, 'default_lang', 'en-US', '2026-03-24 17:18:52', '2026-03-24 17:18:52');

-- 导出  表 peachtrees.pt_posts 结构
CREATE TABLE IF NOT EXISTS `pt_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_type` enum('normal','big-picture') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '文章类型',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '自定义URL标识',
  `summary` text COLLATE utf8mb4_unicode_ci COMMENT '文章简介（大片文章展示在封面底部）',
  `cover_media` json DEFAULT NULL COMMENT '大图文章封面媒体列表（图片或mp4）',
  `content` text COLLATE utf8mb4_unicode_ci,
  `allow_comments` tinyint(1) DEFAULT '1' COMMENT '是否允许评论 0-不允许 1-允许',
  `active` tinyint(1) DEFAULT '1' COMMENT '是否发布 0-下架 1-发布',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `tag` (`tag`),
  CONSTRAINT `pt_posts_ibfk_1` FOREIGN KEY (`tag`) REFERENCES `pt_tags` (`tag`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_posts 的数据：~2 rows (大约)
INSERT IGNORE INTO `pt_posts` (`id`, `tag`, `post_type`, `title`, `slug`, `summary`, `cover_media`, `content`, `allow_comments`, `active`, `created_at`, `updated_at`) VALUES
	(1, 'news', 'normal', '欢迎使用 PeachtreesCMS', NULL, '', NULL, '<p>这是一个基于 PHP 的内容管理系统。</p><p>现已升级为前后端分离架构，使用 Vite + React + Bootstrap 构建。</p>', 1, 1, '2026-03-24 17:18:51', '2026-03-24 17:18:51'),
	(2, 'tech', 'normal', '系统功能介绍', NULL, '', NULL, '<p>系统支持以下功能：</p><ul><li>文章管理</li><li>分类标签</li><li>用户管理</li><li>RSS 订阅</li></ul>', 1, 1, '2026-03-24 17:18:51', '2026-03-24 17:18:51');

-- 导出  表 peachtrees.pt_tags 结构
CREATE TABLE IF NOT EXISTS `pt_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `post_count` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_tags 的数据：~3 rows (大约)
INSERT IGNORE INTO `pt_tags` (`id`, `tag`, `display_name`, `post_count`) VALUES
	(1, 'news', '新闻', 1),
	(2, 'tech', '技术', 1),
	(3, 'life', '生活', 0);

-- 导出  表 peachtrees.pt_themes 结构
CREATE TABLE IF NOT EXISTS `pt_themes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主题目录名',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主题名称',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '主题描述',
  `version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '版本号',
  `author` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '作者',
  `entry_css` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'style.css' COMMENT '入口CSS文件',
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'thumbnail.svg' COMMENT '主题缩略图文件名',
  `is_active` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否当前激活',
  `user_layout_config` json DEFAULT NULL COMMENT '用户自定义布局配置',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_scanned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_themes 的数据：~0 rows (大约)

-- 导出  表 peachtrees.pt_users 结构
CREATE TABLE IF NOT EXISTS `pt_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在导出表  peachtrees.pt_users 的数据：~0 rows (大约)
INSERT IGNORE INTO `pt_users` (`id`, `username`, `email`, `password_hash`, `created_at`, `last_login_at`) VALUES
	(1, 'admin', 'admin@peachtrees.com', '$2y$12$YBnqh4Wjt6sEqYZkaKb7LOqx.k460jVm5aasKSwAQdfSAOSylGPLm', '2026-03-24 17:18:51', '2026-03-24 17:18:51');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
