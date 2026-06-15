---
title: PeachtreesCMS 安装指南
layout: default
---

# 安装指南

## 环境要求

| 组件 | 版本要求 |
|---|---|
| PHP | 8.0+（需启用 `pdo_mysql`、`fileinfo`、`mbstring`、`json`） |
| MySQL | 5.7+ 或 MariaDB 10.3+ |
| Web 服务器 | Nginx 或 Apache（推荐 Nginx） |
| Node.js | 18+（仅构建前端时需要） |

## 安装步骤

### 1. 克隆代码

```bash
git clone https://github.com/taotaotao-studio/PeachtreesCMS.git
cd PeachtreesCMS
```

### 2. 构建前端

```bash
cd pt_frontend
npm install
npm run build
cd ..
```

构建产物在 `pt_frontend/dist/` 目录下。

### 3. 创建数据库

登录 MySQL 并创建数据库：

```sql
CREATE DATABASE peachtrees DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. 配置环境

```bash
# 从模板创建配置文件
cp pt_api/.env.example pt_api/.env

# 编辑 .env，填入实际的数据库连接信息
# DB_HOST=localhost
# DB_NAME=peachtrees
# DB_USER=your_user
# DB_PASS=your_password
# JWT_SECRET=<随机字符串>
```

> **注意**：`JWT_SECRET` 务必改为一个足够长的随机字符串，用于 API Token 签名。

### 5. 部署文件

将构建产物和 PHP 后端部署到 Web 服务器的目标目录（详见[部署方案](deployment)）。

### 6. 运行安装向导

访问 `http://your-domain.com/blog/install.php`（路径取决于部署方式），填写数据库信息，点击安装。

安装向导会自动：
- 创建数据表
- 写入 `.env` 配置文件
- 生成安装锁文件 `.installed`

安装完成后，**务必删除 `install.php`**。

### 7. 完成

- 前台首页：`http://your-domain.com/blog/`
- 后台登录：`http://your-domain.com/blog/admin.html#/admin/login`

默认管理员账号在安装时自动创建，请通过安装向导设置。
