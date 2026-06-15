---
title: PeachtreesCMS 简介
layout: default
---

# PeachtreesCMS

一个轻量级的内容管理系统（CMS），基于 **PHP + React** 构建。

## 特性

- **前后端分离** — PHP RESTful API + React SPA（HashRouter）
- **双端入口** — 前台 `index.html` + 后台 `admin.html`
- **大片模式** — 支持封面媒体（图片/视频）的大图展示文章
- **主题系统** — 可替换的 `theme/` 样式包
- **页面风格** — 每篇文章可独立选择 `pattern/` 样式
- **邮件发布** — 通过邮件发送文章（需配置插件）
- **RSS 订阅** — 内置 RSS Feed
- **导入导出** — 兼容 WordPress WXR 格式

## 技术栈

| 层级 | 技术 |
|---|---|
| 前端 | React 19, React Router 7 (HashRouter), Vite, Tiptap Editor, Swiper |
| 后端 | PHP 8.x (PDO), MySQL, Session 认证 |
| 部署 | Nginx / Apache, PHP-FPM |

## 目录结构

```
PeachtreesCMS/
├── pt_api/              ← PHP REST API
├── pt_frontend/         ← React 前端源码
├── nginx.conf.example   ← Nginx 配置（同目录部署）
├── nginx.backend-separate.conf.example  ← Nginx 配置（后端分离）
└── data-init.sql        ← 数据库初始化脚本
```

### 部署后的运行目录

```
/var/www/html/blog/      ← 部署根目录（示例）
├── index.html           ← 前台入口
├── admin.html           ← 后台入口
├── assets/              ← Vite 构建产物
├── pt_api/              ← PHP 后端
├── upload/              ← 用户上传文件
├── theme/               ← 主题
├── pattern/             ← 页面风格
├── languages/           ← 语言包
└── imgs/                ← 图片资源
```
