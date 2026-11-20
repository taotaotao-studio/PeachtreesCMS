# 路径体系架构（theme / patterns / uploads / content）

> 前后端路径约定与开发代理原理。改造后的统一模型：**所有用户可变内容收敛在
> 一个 `content/` 目录**（类似 WordPress 的 `wp-content`）。

## 1. 目录约定（唯一事实来源）

| 内容 | 物理目录 | URL（站点相对） | 后端返回的相对路径 |
|---|---|---|---|
| 附件上传 | `content/uploads/<Y>/<m>/` | `content/uploads/...` | `content/uploads/...` |
| 主题包 | `content/themes/<slug>/` | `content/themes/...` | `content/themes/...` |
| 页面风格 | `content/patterns/<slug>/` | `content/patterns/...` | `content/patterns/...` |
| 前端语言包 | `languages/`（代码，随版本发布） | `languages/<code>.json` | — |
| 静态图片 | `imgs/`（代码） | `imgs/...` | — |

- 前端唯一来源：`pt_frontend/src/constants.js`（`STATIC_DIRS`）
- 后端唯一来源：`pt_api/config.php`（`UPLOAD_REL_PREFIX` 等常量 + `CONTENT_DIR`）
- 修改目录名时只需改这两处 + `vite.config.js` 无需动（自动消费 `STATIC_DIRS`）。

## 2. 路径契约

1. 后端 API 一律返回**站点相对路径**（如 `content/uploads/2026/04/x.jpg`），
   禁止返回带站点目录前缀的根绝对路径。
2. 前端组件只在**渲染时**调用 `src/utils/path.js` 的 `publicUrl()` /
   `uploadUrl()` / `themeUrl()` / `patternUrl()` 换算成可访问 URL，
   不在组件里手拼前缀。
3. 编辑器入库内容保存相对路径（`content/uploads/...`），浏览器按当前页面
   目录解析。
4. 1.0 之前的存量数据使用旧目录名（`upload/`、`theme/`、`pattern/`），
   `publicUrl()` 内置兼容垫片自动重映射到 `content/` 下；确认无存量数据后
   可移除该垫片。

## 3. 生产环境（构建一次、到处运行）

Vite `base: './'`——所有 URL 统一拼相对 base，支持主域名根目录或任意
子目录（如 `/blog/`）部署，无需任何构建参数。

## 4. 开发环境（pnpm dev）

- `VITE_API_BASE_URL`（默认 `/PeachtreesCMS/pt_api/`）推导站点前缀；
- `content/uploads|themes|patterns` 请求拼前缀后由 `vite.config.js` 反向
  代理到本地 PHP 服务（`STATIC_DIRS` 自动生成代理规则）；
- 本地 PHP 的数据目录通过 `pt_api/.env` 的路径覆盖指向
  `pt_frontend/public/content/*`，实现"上传即热更新可见"。

## 5. 升级为什么安全

`content/` 与代码物理分离：更新（后台一键 / Docker pull / 手动覆盖）只替换
程序文件，`content/` 里的附件、自装主题、风格与 `pt_api/.env`、`.installed`、
sessions 永不被触碰——这正是 WordPress `wp-content` 的核心思想。
