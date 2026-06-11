import { useState, useEffect } from 'react'
import { usersAPI } from '../../services/api'
import { useAuth } from '../../contexts/AuthContext'
import { useLanguage } from '../../contexts/LanguageContext'

export default function Users() {
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [showAddForm, setShowAddForm] = useState(false)
  const [passwordForm, setPasswordForm] = useState({
    oldPassword: '',
    newPassword: '',
    confirmPassword: ''
  })
  const { user: currentUser } = useAuth()
  const { lang } = useLanguage()

  const isAdmin = currentUser?.id === 1

  useEffect(() => {
    loadUsers()
  }, [])

  const loadUsers = async () => {
    if (!isAdmin) return
    setLoading(true)
    try {
      const res = await usersAPI.getList()
      if (res.success) {
        setUsers(res.data)
      }
    } catch (err) {
      console.error('Failed to load users:', err)
    } finally {
      setLoading(false)
    }
  }

  const handlePasswordChange = async (e) => {
    e.preventDefault()
    
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      alert('Passwords do not match')
      return
    }

    try {
      await usersAPI.updatePassword({
        oldPassword: passwordForm.oldPassword,
        newPassword: passwordForm.newPassword,
        confirmPassword: passwordForm.confirmPassword
      })
      setPasswordForm({ oldPassword: '', newPassword: '', confirmPassword: '' })
      alert(lang('success'))
    } catch (err) {
      alert(err.message)
    }
  }

  const handleAddUser = async (e) => {
    e.preventDefault()
    const formData = new FormData(e.target)
    const data = {
      username: formData.get('username'),
      email: formData.get('email'),
      password: formData.get('password')
    }

    try {
      await usersAPI.create(data)
      setShowAddForm(false)
      loadUsers()
    } catch (err) {
      alert(err.message)
    }
  }

  const handleDeleteUser = async (id) => {
    if (!window.confirm(lang('deleteConfirm'))) return
    
    try {
      await usersAPI.delete(id)
      loadUsers()
    } catch (err) {
      alert(err.message)
    }
  }

  return (
    <div>
      {/* Change password section */}
      <div className="card shadow-sm mb-4">
        <div className="card-header bg-white">
          <h5 className="mb-0">
            <i className="bi bi-key me-2"></i>
            {lang('changePassword')}
          </h5>
        </div>
        <div className="card-body">
          <form onSubmit={handlePasswordChange}>
            <div className="row g-3">
              <div className="col-md-3">
                <label className="form-label">{lang('oldPassword')}</label>
                <input
                  type="password"
                  className="form-control"
                  placeholder={lang('oldPassword')}
                  value={passwordForm.oldPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, oldPassword: e.target.value })}
                  required
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">{lang('newPassword')}</label>
                <input
                  type="password"
                  className="form-control"
                  placeholder={lang('newPassword')}
                  value={passwordForm.newPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, newPassword: e.target.value })}
                  required
                  minLength={6}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label">{lang('confirmPassword')}</label>
                <input
                  type="password"
                  className="form-control"
                  placeholder={lang('confirmPassword')}
                  value={passwordForm.confirmPassword}
                  onChange={(e) => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
                  required
                />
              </div>
              <div className="col-md-3 d-flex align-items-end">
                <button type="submit" className="btn btn-primary w-100">
                  <i className="bi bi-check-lg me-1"></i>
                  {lang('submit')}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {/* User management (admin only) */}
      {isAdmin && (
        <>
          <div className="d-flex justify-content-between align-items-center mb-4">
            <h4 className="mb-0">
              <i className="bi bi-people me-2"></i>
              {lang('userList')}
            </h4>
            <button 
              className="btn btn-primary"
              onClick={() => setShowAddForm(!showAddForm)}
            >
              <i className="bi bi-plus-circle me-1"></i>
              {lang('addUser')}
            </button>
          </div>

          {showAddForm && (
            <div className="card shadow-sm mb-4">
              <div className="card-header bg-white">
                <h5 className="mb-0">
                  <i className="bi bi-person-plus me-2"></i>
                  {lang('addUser')}
                </h5>
              </div>
              <div className="card-body">
                <form onSubmit={handleAddUser}>
                  <div className="row g-3">
                    <div className="col-md-3">
                      <label className="form-label">{lang('username')}</label>
                      <input
                        type="text"
                        name="username"
                        className="form-control"
                        placeholder={lang('username')}
                        required
                      />
                    </div>
                    <div className="col-md-3">
                      <label className="form-label">{lang('email')}</label>
                      <input
                        type="email"
                        name="email"
                        className="form-control"
                        placeholder={lang('email')}
                        required
                      />
                    </div>
                    <div className="col-md-3">
                      <label className="form-label">{lang('password')}</label>
                      <input
                        type="password"
                        name="password"
                        className="form-control"
                        placeholder={lang('password')}
                        required
                        minLength={6}
                      />
                    </div>
                    <div className="col-md-3 d-flex align-items-end gap-2">
                      <button type="submit" className="btn btn-primary flex-grow-1">
                        <i className="bi bi-check-lg me-1"></i>
                        {lang('submit')}
                      </button>
                      <button 
                        type="button" 
                        className="btn btn-outline-secondary"
                        onClick={() => setShowAddForm(false)}
                      >
                        <i className="bi bi-x-lg"></i>
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          )}

          {loading ? (
            <div className="text-center py-5">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">{lang('loading')}</span>
              </div>
            </div>
          ) : (
            <div className="card shadow-sm">
              <div className="table-responsive">
                <table className="table table-hover mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{lang('username')}</th>
                      <th>{lang('email')}</th>
                      <th>{lang('registerTime')}</th>
                      <th>{lang('lastLogin')}</th>
                      <th style={{ width: '100px' }} className="text-center">{lang('edit')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {users.map(user => (
                      <tr key={user.id}>
                        <td className="align-middle">
                          <span className="fw-medium">
                            {user.username}
                            {user.id === 1 && (
                              <span className="badge bg-warning text-dark ms-2">Admin</span>
                            )}
                          </span>
                        </td>
                        <td className="align-middle">
                          <a href={`mailto:${user.email}`} className="text-decoration-none">
                            {user.email}
                          </a>
                        </td>
                        <td className="align-middle text-muted">
                          <small>{user.created_at}</small>
                        </td>
                        <td className="align-middle text-muted">
                          <small>{user.last_login_at}</small>
                        </td>
                        <td className="text-center align-middle">
                          {user.id !== 1 && (
                            <button 
                              className="btn btn-sm btn-outline-danger"
                              onClick={() => handleDeleteUser(user.id)}
                              title={lang('delete')}
                            >
                              <i className="bi bi-trash"></i>
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}