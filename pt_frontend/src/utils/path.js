/**
 * 将路径转换为绝对路径（自动加上 Vite 的 base 前缀）
 * @param {string} path - 路径（可以以 / 开头，也可以不以 / 开头）
 * @returns {string} 转换后的路径
 */
export function toAbsolutePath(path) {
  const base = import.meta.env.BASE_URL || '/'
  // 去掉 path 开头的 /（如果有）
  const normalizedPath = path.startsWith('/') ? path.slice(1) : path
  // 拼接 base 和 path
  return `${base.endsWith('/') ? base : base + '/'}${normalizedPath}`
}
