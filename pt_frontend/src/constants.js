/**
 * PeachtreesCMS 用户内容目录约定（唯一来源）
 *
 * 所有“用户可变内容”（附件 / 主题 / 页面风格）统一收敛在 content/ 目录下，
 * 类似 WordPress 的 wp-content：升级只替换代码、永远不碰 content/。
 *
 * 该常量被两处共同引用，新增内容子目录时只需改这里：
 *   1. vite.config.js  —— 自动生成开发代理（带前缀 + 无前缀兼容重写）
 *   2. src/utils/path.js —— 开发环境前缀特判与 URL 换算
 *
 * 注意：与后端约定一致（后端 API 返回 content/uploads/…、content/themes/…、
 * content/patterns/… 站点相对路径，常量见 pt_api/config.php 的 *_REL_PREFIX）。
 */
export const CONTENT_BASE = 'content'
export const STATIC_SUBDIRS = ['uploads', 'themes', 'patterns']
export const STATIC_DIRS = STATIC_SUBDIRS.map((dir) => `${CONTENT_BASE}/${dir}`)
