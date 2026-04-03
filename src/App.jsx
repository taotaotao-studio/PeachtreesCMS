import { HashRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import { LanguageProvider } from './contexts/LanguageContext'
import { ThemeProvider } from './contexts/ThemeContext'

// Pages
import Home from './pages/Home'
import PostDetail from './pages/PostDetail'
import AdminLayout from './pages/admin/Layout'
import Login from './pages/admin/Login'
import PostList from './pages/admin/PostList'
import PostEdit from './pages/admin/PostEdit'
import Tags from './pages/admin/Tags'
import Users from './pages/admin/Users'
import Comments from './pages/admin/Comments'
import CommentWhitelist from './pages/admin/CommentWhitelist'
import Themes from './pages/admin/Themes'
import Settings from './pages/admin/Settings'

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

function App() {
  return (
    <HashRouter>
      <ThemeProvider>
        <LanguageProvider>
          <AuthProvider>
            <Routes>
              {/* Frontend routes */}
              <Route path="/" element={<Home />} />
              <Route path="/post/:identifier" element={<PostDetail />} />

              {/* Admin routes */}
              <Route path="/admin/login" element={<Login />} />
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
                <Route path="posts/edit/:id" element={<PostEdit />} />
                <Route path="tags" element={<Tags />} />
                <Route path="users" element={<Users />} />
                <Route path="comments" element={<Comments />} />
                <Route path="comment-whitelist" element={<CommentWhitelist />} />
                <Route path="themes" element={<Themes />} />
                <Route path="settings" element={<Settings />} />
              </Route>

              {/* 404 */}
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </AuthProvider>
        </LanguageProvider>
      </ThemeProvider>
    </HashRouter>
  )
}

export default App
