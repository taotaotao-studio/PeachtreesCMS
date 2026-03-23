import React from 'react'
import ReactDOM from 'react-dom/client'
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import { LanguageProvider } from './contexts/LanguageContext'
import { ThemeProvider } from './contexts/ThemeContext'

// Load Bootstrap CSS and Icons for admin pages
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import './admin.css'

// Pages
import Home from './pages/Home'
import PostDetail from './pages/PostDetail'
import AdminLayout from './pages/admin/Layout'
import Login from './pages/admin/Login'
import PostList from './pages/admin/PostList'
import PostEdit from './pages/admin/PostEdit'
import BigPicturePostEdit from './pages/admin/BigPicturePostEdit'
import Tags from './pages/admin/Tags'
import Users from './pages/admin/Users'
import Comments from './pages/admin/Comments'
import CommentWhitelist from './pages/admin/CommentWhitelist'
import Themes from './pages/admin/Themes'
import Settings from './pages/admin/Settings'
import Data from './pages/admin/Data'

// Protected Route
function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <div style={{ textAlign: 'center', padding: '50px' }}>Loading...</div>
  }

  if (!user) {
    return <Navigate to="/admin/login" replace />
  }

  return children
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ThemeProvider>
      <LanguageProvider>
        <AuthProvider>
          <HashRouter>
            <Routes>
              {/* Admin 入口默认重定向到后台 */}
              <Route path="/" element={<Navigate to="/admin" replace />} />

              {/* Admin login - 无需认证，独立于受保护的路由之外 */}
              <Route path="/admin/login" element={<Login />} />

              {/* Protected admin routes */}
              <Route
                path="/admin/*"
                element={
                  <ProtectedRoute>
                    <AdminLayout />
                  </ProtectedRoute>
                }
              >
                <Route index element={<Navigate to="/admin/posts" replace />} />
                <Route path="posts" element={<PostList />} />
                <Route path="posts/new" element={<PostEdit />} />
                <Route path="posts/new-big-picture" element={<BigPicturePostEdit />} />
                <Route path="posts/edit/:id" element={<PostEdit />} />
                <Route path="tags" element={<Tags />} />
                <Route path="users" element={<Users />} />
                <Route path="comments" element={<Comments />} />
                <Route path="comment-whitelist" element={<CommentWhitelist />} />
                <Route path="themes" element={<Themes />} />
                <Route path="settings" element={<Settings />} />
                <Route path="data" element={<Data />} />
              </Route>

              {/* 404 - 重定向到后台 */}
              <Route path="*" element={<Navigate to="/admin" replace />} />
            </Routes>
          </HashRouter>
        </AuthProvider>
      </LanguageProvider>
    </ThemeProvider>
  </React.StrictMode>
)
