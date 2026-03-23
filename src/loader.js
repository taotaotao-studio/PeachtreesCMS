// 动态加载器：根据URL路径决定加载前台还是后台
const isAdminPath = () => {
  const hash = window.location.hash
  return hash.startsWith('#/admin') || hash === '#/admin'
}

// 动态加载入口文件
if (isAdminPath()) {
  // 后台路径，加载 admin 入口（包含Bootstrap）
  import('./main-admin.jsx')
} else {
  // 前台路径，加载 frontend 入口（不包含Bootstrap）
  import('./main-frontend.jsx')
}