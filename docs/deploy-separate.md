# 高级配置：后端分离部署

> 这是**同一个产品**的一种高级部署拓扑，不是独立版本。代码与 `release.zip`
> 与合体部署完全一致，差别只是：PHP 后端放在 web root 之外，通过一个
> `CONTENT_DIR` 环境变量指回前端目录下的用户内容。

## 1. 什么时候用

| 场景 | 建议 |
|---|---|
| 虚拟主机 / 宝塔 / 常规 Apache / Nginx | **合体部署**（release.zip 解压即用），不要分离 |
| 想让 PHP 代码不暴露在 web root（安全洁癖） | 分离部署 |
| 需要独立扩缩 PHP 与静态层 | 分离部署 |
| Docker | 直接用镜像（见 [docker/docker-compose.yml](../docker/docker-compose.yml)），无需手工分离 |

## 2. 步骤

1. 把 release.zip 中的 **前端部分**（`index.html`、`admin.html`、`assets/`、
   `imgs/`、`languages/`、`content/`、`.htaccess`）放到 web root，例如
   `/var/www/html/blog/`。
2. 把 `pt_api/` 放到 web root **之外**，例如 `/var/www/php/pt_api/`。
3. 在 `pt_api/.env` 中配置一个变量：
   ```ini
   CONTENT_DIR=/var/www/html/blog/content
   ```
   （或按子目录单独覆盖 `UPLOAD_DIR` / `THEME_DIR` / `STYLE_DIR`，见
   [../pt_api/.env.example](../pt_api/.env.example)。）
4. Nginx 参考完整示例：[nginx.backend-separate.conf.example](nginx.backend-separate.conf.example)。
   两条安全规则（dotfile 拒绝 + `pt_api/sessions` 拒绝）缺一不可。
5. 确保 php-fpm 的 `open_basedir` 允许读写 web root 下的 `content/`。

## 3. 升级

分离部署的升级与合体部署**完全相同**：新 release.zip 覆盖代码，保留
`content/` 与 `pt_api/.env`；或者直接在后台"系统更新"一键完成。

## 4. 前后端分离 ≠ 分离部署

- **开发时**的前后端分离（Vite 热更新 + 本地 PHP）是工程手段，与部署无关，
  见 [path-architecture.md](path-architecture.md)。
- **部署时**不建议把前端静态资源托管到独立域名（跨域会破坏 Session 认证
  的 cookie 行为）。如确有需要，需改造认证为 token 方案，属二次开发。
