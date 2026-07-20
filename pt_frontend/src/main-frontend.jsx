import React from 'react'
import ReactDOM from 'react-dom/client'
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { LanguageProvider } from './contexts/LanguageContext'
import { ThemeProvider } from './contexts/ThemeContext'

// Pages
import Home from './pages/Home'
import PostDetail from './pages/PostDetail'

const frontendContainer = document.getElementById('root')
const frontendRoot = ReactDOM.createRoot(frontendContainer)

// Vite HMR — entry module should NOT self-accept; full reload on change is correct
if (import.meta.hot) {
  import.meta.hot.dispose(() => {
    frontendRoot.unmount()
  })
}

frontendRoot.render(
  <React.StrictMode>
    <ThemeProvider>
      <LanguageProvider>
        <AuthProvider>
          <HashRouter>
            <Routes>
              {/* Frontend routes only */}
              <Route path="/" element={<Home />} />
              <Route path="/post/:identifier" element={<PostDetail />} />

              {/* 404 */}
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </HashRouter>
        </AuthProvider>
      </LanguageProvider>
    </ThemeProvider>
  </React.StrictMode>
)