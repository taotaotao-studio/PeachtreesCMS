import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useLanguage } from '../../contexts/LanguageContext'

export default function Login() {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const { lang } = useLanguage()

  if (user) {
    return <Navigate to="/admin/posts" replace />
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      await login(username, password)
      navigate('/admin/posts')
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-vh-100 d-flex align-items-center justify-content-center bg-light">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-md-5">
            <div className="card shadow-lg border-0">
              <div className="card-header bg-primary text-white text-center py-4">
                <h3 className="mb-0">
                  <i className="bi bi-shield-lock me-2"></i>
                  {lang('loginTitle')}
                </h3>
              </div>
              <div className="card-body p-4">
                {error && (
                  <div className="alert alert-danger d-flex align-items-center" role="alert">
                    <i className="bi bi-exclamation-triangle-fill me-2"></i>
                    {error}
                  </div>
                )}

                <form onSubmit={handleSubmit}>
                  <div className="mb-3">
                    <label className="form-label">
                      <i className="bi bi-person me-1"></i>
                      {lang('username')}
                    </label>
                    <input
                      type="text"
                      className="form-control form-control-lg"
                      placeholder={lang('username')}
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                      required
                      autoFocus
                    />
                  </div>

                  <div className="mb-4">
                    <label className="form-label">
                      <i className="bi bi-lock me-1"></i>
                      {lang('password')}
                    </label>
                    <input
                      type="password"
                      className="form-control form-control-lg"
                      placeholder={lang('password')}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                    />
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary btn-lg w-100"
                    disabled={loading}
                  >
                    {loading ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2" role="status"></span>
                        {lang('loading')}
                      </>
                    ) : (
                      <>
                        <i className="bi bi-box-arrow-in-right me-2"></i>
                        {lang('loginButton')}
                      </>
                    )}
                  </button>
                </form>
              </div>
              <div className="card-footer text-center text-muted py-3">
                <small>© {new Date().getFullYear()} PeachtreesCMS</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}