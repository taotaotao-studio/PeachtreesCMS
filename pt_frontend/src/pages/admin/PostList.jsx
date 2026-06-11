import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { postsAPI } from '../../services/api'
import { useLanguage } from '../../contexts/LanguageContext'
import Pager from '../../components/Pager'

export default function PostList() {
  const [posts, setPosts] = useState([])
  const [pagination, setPagination] = useState({ page: 1, totalPages: 1 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const { lang } = useLanguage()

  const page = pagination.page

  useEffect(() => {
    loadPosts()
  }, [page])

  const loadPosts = async () => {
    setLoading(true)
    try {
      const res = await postsAPI.getList({ page, perPage: 15, showInactive: true })
      if (res.success) {
        setPosts(res.data.posts)
        setPagination(res.data.pagination)
      }
    } catch (err) {
      console.error('Failed to load posts:', err)
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleDelete = async (id) => {
    if (!window.confirm(lang('deleteConfirm'))) return

    try {
      await postsAPI.delete(id)
      loadPosts()
    } catch (err) {
      alert(err.message)
    }
  }

  const handleToggleActive = async (id, currentActive) => {
    try {
      await postsAPI.toggleActive(id)
      loadPosts()
    } catch (err) {
      alert(err.message)
    }
  }

  const handlePageChange = (newPage) => {
    setPagination(prev => ({ ...prev, page: newPage }))
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h4 className="mb-0">
          <i className="bi bi-file-text me-2"></i>
          {lang('postList')}
        </h4>
        <Link to="/admin/posts/new" className="btn btn-primary">
          <i className="bi bi-plus-circle me-1"></i>
          {lang('addPost')}
        </Link>
      </div>

      {loading ? (
        <div className="text-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">{lang('loading')}</span>
          </div>
        </div>
      ) : error ? (
        <div className="alert alert-danger text-center">
          <i className="bi bi-exclamation-triangle me-2"></i>
          {error}
        </div>
      ) : posts.length === 0 ? (
        <div className="alert alert-info text-center">
          <i className="bi bi-info-circle me-2"></i>
          {lang('noPosts')}
        </div>
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead className="table-light">
                <tr>
                  <th style={{ width: '60px' }}>ID</th>
                  <th>{lang('postTitle')}</th>
                  <th style={{ width: '120px' }}>{lang('postCategory')}</th>
                  <th style={{ width: '120px' }}>{lang('postDate')}</th>
                  <th style={{ width: '160px' }} className="text-center">{lang('edit')}</th>
                </tr>
              </thead>
              <tbody>
                {posts.map(post => (
                  <tr key={post.id}>
                    <td className="align-middle">{post.id}</td>
                    <td className="align-middle">
                      <a href={`/#/post/${post.slug || post.id}`} target="_blank" rel="noopener noreferrer" className="text-decoration-none">
                        {post.title}
                      </a>
                      {post.post_type === 'big-picture' && (
                        <span className="badge bg-dark ms-2">big-picture</span>
                      )}
                      {post.slug && (
                        <span className="badge bg-light text-secondary ms-2" style={{ fontSize: '0.75rem' }}>
                          {post.slug}
                        </span>
                      )}
                    </td>
                    <td className="align-middle">
                      <span className="badge bg-secondary">{post.display_name}</span>
                    </td>
                    <td className="align-middle text-muted">
                      <small>{post.created_at?.split(' ')[0]}</small>
                    </td>
                    <td className="align-middle text-center">
                      <div className="btn-group btn-group-sm">
                        <Link
                          to={`/admin/posts/edit/${post.id}`}
                          className="btn btn-outline-primary"
                          title={lang('edit')}
                        >
                          <i className="bi bi-pencil"></i>
                        </Link>
                        {post.active == 1 ? (
                          <button
                            className="btn btn-outline-warning"
                            onClick={() => handleToggleActive(post.id, post.active)}
                            title={lang('unpublish')}
                          >
                            <i className="bi bi-eye-slash"></i>
                          </button>
                        ) : (
                          <button
                            className="btn btn-outline-success"
                            onClick={() => handleToggleActive(post.id, post.active)}
                            title={lang('publish')}
                          >
                            <i className="bi bi-eye"></i>
                          </button>
                        )}
                        <button
                          className="btn btn-outline-danger"
                          onClick={() => handleDelete(post.id)}
                          title={lang('delete')}
                        >
                          <i className="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <Pager
        page={pagination.page}
        totalPages={pagination.totalPages}
        onPageChange={handlePageChange}
      />
    </div>
  )
}
