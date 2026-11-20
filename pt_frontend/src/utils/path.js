import { STATIC_DIRS, CONTENT_BASE } from '../constants'

/**
 * PeachtreesCMS 前端路径工具（统一路径转换的唯一入口）
 *
 * ── 路径约定（请所有开发者遵守）───────────────────────────────────────
 * 1. 后端 API 一律返回【站点相对路径】，例如：
 *      content/uploads/2026/04/07-xxx.jpg   （附件）
 *      content/themes/default/style.css     （主题，来自 css_url）
 *      content/patterns/01/style.css        （页面风格，来自 css_url）
 *    禁止返回带站点目录前缀的根绝对路径（如 /blog/content/uploads/...）。
 * 2. 前端组件【只在渲染/展示时】调用本文件函数把相对路径转成可访问 URL；
 *    不要在组件里手动拼接 /content/... 前缀。
 * 3. 编辑器存储到数据库的内容里保存相对路径（content/uploads/...），浏览器渲染时
 *    天然按当前页面目录解析，开发环境由 vite.config.js 的代理兜底。
 * 4. 1.0 之前的存量数据使用旧目录名（upload/…、theme/…、pattern/…）；
 *    publicUrl() 会自动重映射到 content/ 下对应目录——该兼容垫片可在
 *    确认无存量数据后移除。
 *
 * ── 各环境行为 ───────────────────────────────────────────────────────
 * 开发环境 (pnpm dev):
 *   - content/uploads/themes/patterns 资源由本地 PHP 服务提供，需拼上站点前缀
 *     （从 VITE_API_BASE_URL 推导，如 /PeachtreesCMS/）走 Vite 代理；
 *   - languages/imgs 等静态资源由 Vite public/ 直接提供。
 * 生产环境 (base './'):
 *   - 全部统一拼接相对 base，实现“构建一次、到处运行”。
 * 绝对外链 (http(s):// 或 //) 一律原样返回。
 */

// 开发环境下参与代理的静态资源目录（与 vite.config.js 共用 src/constants.js，新增目录改一处即可）
const PROXIED_DIRS = STATIC_DIRS.map((dir) => `${dir}/`)

/**
 * 开发环境站点根前缀：'/PeachtreesCMS/pt_api/' -> '/PeachtreesCMS'
 */
function getSitePrefix() {
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/PeachtreesCMS/pt_api/'
  return apiBase.replace(/pt_api\/?$/, '').replace(/\/$/, '')
}

/**
 * 旧目录名兼容垫片：upload/…、theme/…、pattern/… → content/ 下对应目录
 * @param {string} normalized - 已去掉前导 / 的站点相对路径
 * @returns {string}
 */
function remapLegacyDir(normalized) {
  const map = {
    'upload/': `${CONTENT_BASE}/uploads/`,
    'theme/': `${CONTENT_BASE}/themes/`,
    'pattern/': `${CONTENT_BASE}/patterns/`
  }
  for (const [legacy, modern] of Object.entries(map)) {
    if (normalized.startsWith(legacy)) {
      return modern + normalized.slice(legacy.length)
    }
  }
  return normalized
}

/**
 * 将站点相对路径转换为可访问 URL（统一转换入口）
 * @param {string} path - 相对路径（可带或不带前导 /）
 * @returns {string}
 */
export function publicUrl(path) {
  if (!path) return ''

  // 绝对外链（http://、https://、//）原样返回
  if (/^(https?:)?\/\//i.test(path)) {
    return path
  }

  let normalized = path.startsWith('/') ? path.slice(1) : path

  // 旧目录名兼容：upload/theme/pattern → content/uploads|themes|patterns
  normalized = remapLegacyDir(normalized)

  // 开发环境：content/uploads|themes|patterns 由 PHP 服务提供，拼站点前缀走 Vite 代理
  if (import.meta.env.DEV) {
    if (PROXIED_DIRS.some((dir) => normalized.startsWith(dir))) {
      return `${getSitePrefix()}/${normalized}`
    }
    // 其余静态资源（languages/imgs/favicon）由 Vite public/ 提供
    return `/${normalized}`
  }

  // 生产环境：统一拼接 Vite base（'./'），实现任意子目录部署
  const base = import.meta.env.BASE_URL || '/'
  return `${base.endsWith('/') ? base : base + '/'}${normalized}`
}

/**
 * 上传附件 URL。接受 'content/uploads/2026/04/x.jpg'（推荐）、
 * 旧路径 'upload/...'、'/...' 或绝对外链。
 */
export function uploadUrl(path) {
  if (!path) return ''
  const str = String(path)
  if (/^(https?:)?\/\//i.test(str)) return str
  let normalized = str.startsWith('/') ? str.slice(1) : str
  if (normalized.startsWith('upload/')) {
    // 旧路径兼容
    normalized = `${CONTENT_BASE}/uploads/${normalized.slice('upload/'.length)}`
  }
  const prefix = `${CONTENT_BASE}/uploads/`
  const rel = normalized.startsWith(prefix) ? normalized : prefix + normalized
  return publicUrl(rel)
}

/**
 * 主题资源 URL。slug 为主题目录名（如 'default'），file 默认 style.css。
 */
export function themeUrl(slug, file = 'style.css') {
  if (!slug) return ''
  return publicUrl(`/${CONTENT_BASE}/themes/${encodeURIComponent(slug)}/${String(file).replace(/^\/+/, '')}`)
}

/**
 * 页面风格(pattern)资源 URL。slug 为风格目录名（如 '01'），file 默认 style.css。
 */
export function patternUrl(slug, file = 'style.css') {
  if (!slug) return ''
  return publicUrl(`/${CONTENT_BASE}/patterns/${encodeURIComponent(slug)}/${String(file).replace(/^\/+/, '')}`)
}

/**
 * 语言包资源 URL（languages/{code}.json）
 */
export function languageUrl(langCode) {
  return publicUrl(`/languages/${langCode}.json`)
}
