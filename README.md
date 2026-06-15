# PeachtreesCMS

一个基于前后端分离架构的内容管理系统。

> 📖 在线文档：[https://taotaotao-studio.github.io/PeachtreesCMS/](https://taotaotao-studio.github.io/PeachtreesCMS/)

## 技术栈

### 后端
- PHP 8.1+ (PDO)
- MySQL 5.7+
- RESTful API
- JWT 认证

### 前端
- Vite 7.x
- React 19.x
- React Router 7.x (HashRouter)
- Bootstrap 5.x
- Bootstrap Icons
- Axios
- TipTap (富文本编辑器)
- Swiper (轮播组件)


## 安装要求

- PHP >= 8.1
  - 必需扩展：`pdo`, `pdo_mysql`, `fileinfo`（文件上传必需）
- MySQL >= 5.7
- Node.js >= 16.0
- pnpm >= 8.0
- Nginx (推荐) 或 Apache


## 功能特性

- 数据导入导出（支持 WordPress WXR 格式）
- RSS 订阅
- 评论管理（支持审核和白名单）
- 多语言支持 (中文/英文)
- 主题管理（支持自定义布局）

## 安装

### 1. 上传
- pt_api 目录
- dist（需要进入 pt_frontend 然后运行 pnpm build 生成）下所有文件，保持和 api 在同一级目录内

```markdown
/var/www/html/                    # Nginx 根目录
├── pt_api/                       # PHP api 目录
├── index.html                    # 前端入口
├── admin.html                    # 后台入口
├── assets/                       # 静态资源
├── imgs/                         # 静态资源，包含用户头像
├── languages/                    # 语言文件
├── theme/                        # 主题文件
├── pattern/                      # 风格文件
└── upload/                       # 上传文件目录
```

#### 2. 运行安装程序

- 运行 http://your-domain.com/pt_api/install.php


## 开发指南

### 1. 克隆项目并安装依赖

```bash
git clone <repository-url>
cd PeachtreesCMS
pnpm i # 安装依赖
pnpm dev # 启动开发服务器
```

### 2. 开发环境访问地址：
- 前台：`http://localhost:5173`
- 后台：`http://localhost:5173/admin.html`

### 3. 主题文件夹 `public\theme`
- default 为内置一栏主题
- peachtrees-two-column 为内置两栏主题

### 4. 风格文件夹 `public\pattern`
- 分类和每篇文章都能选择风格，分类风格优先于文章风格
- 简单修改页面背景、定制页面配色等


---
This project uses a custom non-commercial license. See LICENSE file for details.

本项目使用了非商业性授权协议。请查看 LICENSE 文件以获得更多信息。