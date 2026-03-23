import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useLanguage } from '../../contexts/LanguageContext'
import { useTheme } from '../../contexts/ThemeContext'
import { optionsAPI } from '../../services/api'

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const { lang, language, setLanguage } = useLanguage()
  const { refetchSettings } = useTheme()

  const handleLanguageChange = async (e) => {
    const newLang = e.target.value
    try {
      await optionsAPI.update({ default_lang: newLang })
      setLanguage(newLang)
      refetchSettings()
    } catch (err) {
      console.error('Failed to update language:', err)
      alert('Failed to update language setting')
    }
  }

  return (
    <div className="d-flex min-vh-100">
      {/* Sidebar */}
      <nav className="d-flex flex-column flex-shrink-0 p-3 text-bg-dark" style={{ width: '220px' }}>
        <h5 className="mb-3 text-center">
          <i className="bi bi-gear me-2"></i>
          {lang('admin')}
        </h5>
        <hr />
        <ul className="nav nav-pills flex-column mb-auto">
          <li className="nav-item">
            <NavLink
              to="/admin/posts"
              end
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-file-text me-2"></i>
              {lang('postList')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/posts/new"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-plus-circle me-2"></i>
              {lang('addPost')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/posts/new-big-picture"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-image me-2"></i>
              {lang('addBigPicture')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/tags"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-tags me-2"></i>
              {lang('tags')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/users"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-people me-2"></i>
              {lang('users')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/comments"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-chat-dots me-2"></i>
              {lang('comments')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/comment-whitelist"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-shield-check me-2"></i>
              {lang('commentWhitelist')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/themes"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-palette me-2"></i>
              {lang('themeManagement')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/settings"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-gear me-2"></i>
              {lang('settings')}
            </NavLink>
          </li>
          <li className="nav-item">
            <NavLink
              to="/admin/data"
              className={({ isActive }) => `nav-link text-white ${isActive ? 'active' : ''}`}
            >
              <i className="bi bi-arrow-left-right me-2"></i>
              {lang('dataManagement')}
            </NavLink>
          </li>
        </ul>
      </nav>

      {/* Main content */}
      <div className="flex-grow-1 d-flex flex-column">
        {/* Top bar */}
        <nav className="navbar navbar-expand navbar-light bg-light border-bottom px-3">
          <div className="ms-auto d-flex align-items-center gap-3">
            {/* Language Switcher */}
            <select
              className="form-select form-select-sm"
              style={{ width: 'auto' }}
              value={language}
              onChange={handleLanguageChange}
            >
              <option value="zh-CN">简体中文</option>
              <option value="en-US">English</option>
            </select>
            
            <span className="navbar-text text-muted">
              <i className="bi bi-person me-1"></i>
              {lang('home')}, <strong>{user.username}</strong>
            </span>
            <button className="btn btn-outline-secondary btn-sm" onClick={logout}>
              <i className="bi bi-box-arrow-right me-1"></i>
              {lang('logout')}
            </button>
            <a
              href="/"
              target="_blank"
              rel="noopener noreferrer"
              className="btn btn-outline-primary btn-sm"
            >
              <i className="bi bi-house me-1"></i>
              {lang('home')}
            </a>
          </div>
        </nav>

        {/* Page content */}
        <main className="flex-grow-1 p-4 bg-light">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
