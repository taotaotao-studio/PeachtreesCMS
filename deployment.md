---
title: PeachtreesCMS 部署方案
layout: default
---

# 部署方案

PeachtreesCMS 提供两种部署方式，区别仅在于 PHP 后端和前端文件是否放在同一个 Web 根目录下。

---

## 方案一：前后端同目录（推荐）

所有文件部署在 Nginx/Apache 的同一个 `root` 目录下，`theme/`、`pattern/`、`upload/`、`languages/`、`imgs/` 与 `index.html` 同级，无需额外配置。

### 目录结构

```
/var/www/html/blog/           ← Nginx root + 所有文件
├── index.html                ← 前端入口
├── admin.html                ← 后台入口
├── assets/                   ← Vite 构建产物
├── pt_api/                   ← PHP 后端
├── upload/                   ← 用户上传文件
├── theme/                    ← 主题
├── pattern/                  ← 页面风格
├── languages/                ← 语言包
└── imgs/                     ← 图片资源
```

### 前端构建配置

```js
// vite.config.js
base: '/blog/',               // 改为实际部署路径
```

```
// pt_frontend/.env
VITE_API_BASE_URL=/blog/pt_api/
```

### Nginx 配置

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.html;
    server_name your-domain.com;

    # 拒绝访问敏感文件
    location ~ /\.env     { deny all; return 404; }
    location ~ /\.installed { deny all; return 404; }

    # PHP 处理
    location ~ ^/blog/pt_api/(.+\.php)$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/blog/pt_api/$1;
        include fastcgi_params;
    }

    # 前端静态资源
    location /blog/ {
        try_files $uri $uri/ =404;
        add_header X-Content-Type-Options nosniff;
        add_header X-Frame-Options DENY;
        add_header X-XSS-Protection "1; mode=block";
    }
}
```

### PHP 配置

`.env` 中无需设置路径相关变量，config.php 会自动探测：

| 变量 | 自动探测结果 |
|---|---|
| `UPLOAD_DIR` | `/var/www/html/blog/upload/` |
| `THEME_DIR` | `/var/www/html/blog/theme` |
| `STYLE_DIR` | `/var/www/html/blog/pattern` |
| `UPLOAD_URL` | `/blog/upload/` |

### 部署命令

```bash
# 1. 构建前端
cd pt_frontend && npm run build

# 2. 创建目标目录
mkdir -p /var/www/html/blog

# 3. 复制前端构建产物
cp -r pt_frontend/dist/* /var/www/html/blog/

# 4. 复制 PHP 后端
cp -r pt_api /var/www/html/blog/pt_api/

# 5. 复制主题/样式/语言/图片
cp -r theme pattern languages imgs /var/www/html/blog/

# 6. 创建上传目录并设置权限
mkdir -p /var/www/html/blog/upload
chmod 777 /var/www/html/blog/upload

# 7. 配置 .env
cp pt_api/.env.example /var/www/html/blog/pt_api/.env
# 编辑 .env 填入数据库密码和 JWT_SECRET
```

---

## 方案二：后端分离部署

PHP 后端独立放在 Web 根目录之外，适用于安全要求较高的环境。

### 目录结构

```
/var/www/html/blog/           ← 前端 + 静态资源
├── index.html
├── admin.html
├── assets/
├── upload/
├── theme/
├── pattern/
├── languages/
└── imgs/

/var/www/php/pt_api/          ← PHP 后端（Web 根目录外）
```

### 前端构建配置

与方案一相同：

```js
// vite.config.js
base: '/blog/',
```

```
// pt_frontend/.env
VITE_API_BASE_URL=/blog/pt_api/
```

### Nginx 配置

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.html;
    server_name your-domain.com;

    # 拒绝访问敏感文件
    location ~ /\.env     { deny all; return 404; }
    location ~ /\.installed { deny all; return 404; }

    # PHP 处理 — PHP 文件在 Web 根目录外
    location ~ ^/blog/pt_api/(.+\.php)$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/php/pt_api/$1;
        include fastcgi_params;
    }

    # 前端静态资源
    location /blog/ {
        try_files $uri $uri/ =404;
        add_header X-Content-Type-Options nosniff;
        add_header X-Frame-Options DENY;
        add_header X-XSS-Protection "1; mode=block";
    }
}
```

### PHP 配置 ⚠️

由于 PHP 代码不在 Web 根目录下，**必须**在 `.env` 中手动配置路径：

```ini
# .env 路径覆盖（取消注释并填入实际路径）
UPLOAD_DIR=/var/www/html/blog/upload
THEME_DIR=/var/www/html/blog/theme
STYLE_DIR=/var/www/html/blog/pattern
UPLOAD_URL_BASE=/blog/upload
```

同时确保 PHP-FPM 的 `open_basedir` 允许 PHP 写入 `/var/www/html/blog/upload/` 目录。

---

## 共享主机环境（Apache）

如果使用共享主机（无法配置 Nginx），PeachtreesCMS 提供以下安全机制：

### `.htaccess` 自动保护

`pt_api/.htaccess` 文件会自动拒绝 `.env` 和 `.installed` 文件的外部访问：

```apache
<FilesMatch "\.env">
    Require all denied
</FilesMatch>
<FilesMatch "\.installed$">
    Require all denied
</FilesMatch>
```

### 将 `.env` 放在 Web 根目录外

config.php 会自动向上搜索 3 层父目录查找 `.env`：

```
/home/your_user/              ← 推荐放这里
├── .env
└── public_html/
    └── blog/
        ├── index.html
        └── pt_api/
```

---

## 部署到域名根目录

如果要将 PeachtreesCMS 部署到 `domain.com` 而非 `domain.com/blog/`，只需修改以下配置：

```diff
// vite.config.js
- base: '/blog/',
+ base: '/',

// pt_frontend/.env
- VITE_API_BASE_URL=/blog/pt_api/
+ VITE_API_BASE_URL=/pt_api/
```

```diff
# Nginx
- location ~ ^/blog/pt_api/(.+\.php)$ {
+ location ~ ^/pt_api/(.+\.php)$ {
-     fastcgi_param SCRIPT_FILENAME $document_root/blog/pt_api/$1;
+     fastcgi_param SCRIPT_FILENAME $document_root/pt_api/$1;
  }

- location /blog/ { try_files $uri $uri/ =404; }
+ location / { try_files $uri $uri/ =404; }
```

PHP 端和前端代码无需任何更改，路径全部自动探测。

---

## 两种方案对比

| | 同目录（推荐） | 后端分离 |
|---|---|---|
| 配置复杂度 | 低，零额外配置 | 中，需手动设置路径 |
| 安全隔离 | PHP 在 Web 根目录下 | PHP 在 Web 根目录外 |
| `.env` 配置 | 无需路径相关变量 | 需设置 `UPLOAD_DIR` 等 |
| 适用场景 | 大多数环境 | 安全要求较高的生产环境 |
