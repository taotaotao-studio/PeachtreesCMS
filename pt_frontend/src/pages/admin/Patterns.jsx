import { useState, useEffect } from 'react'
import { stylesAPI } from '../../services/api'
import { useLanguage } from '../../contexts/LanguageContext'
import { publicUrl } from '../../utils/path'

export default function Patterns() {
  const { lang } = useLanguage()
  const [styles, setStyles] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadStyles()
  }, [])

  const loadStyles = async () => {
    setLoading(true)
    try {
      const res = await stylesAPI.getList()
      if (res.success) {
        setStyles(Array.isArray(res.data.styles) ? res.data.styles : [])
      }
    } catch (err) {
      console.error('Failed to load styles:', err)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h4 className="mb-0">
          <i className="bi bi-brush me-2"></i>
          {lang('patternManagement')}
        </h4>
        <div className="d-flex gap-2">
          <button className="btn btn-outline-secondary btn-sm" onClick={loadStyles} disabled={loading}>
            <i className="bi bi-arrow-repeat me-1"></i>
            {lang('scanLocalPatterns')}
          </button>
        </div>
      </div>

      {loading ? (
        <div className="text-center py-5">
          <div className="spinner-border text-primary" role="status"></div>
        </div>
      ) : styles.length === 0 ? (
        <div className="alert alert-warning">{lang('noPatternFound')}</div>
      ) : (
        <div className="row g-3">
          {styles.map((style) => (
            <div key={style.id} className="col-12 col-md-4 col-xl-2">
              <div className="card shadow-sm h-80">
                <div className="position-relative">
                  <div className="pattern-card-thumb d-flex align-items-center justify-content-center bg-light" style={{ minHeight: '120px' }}>
                    {style.thumbnail ? (
                      <img
                        src={publicUrl(style.thumbnail)}
                        alt={`${style.slug} preview`}
                        className="w-100 h-100 object-fit-cover"
                        loading="lazy"
                      />
                    ) : (
                      <div className="text-center">
                        <i className="bi bi-palette fs-2 d-block mb-2 text-muted"></i>
                        <small className="d-block text-muted">{style.slug}</small>
                      </div>
                    )}
                  </div>
                </div>
                <div className="card-body d-flex flex-column">
                  <div className="d-flex justify-content-between align-items-start mb-1">
                    <h6 className="card-title mb-0">{style.slug}</h6>
                  </div>
                  <div className="small text-muted mb-2">
                    {lang('version')} {style.version || '-'} · {style.author || lang('unknownAuthor')}
                  </div>
                  <p className="card-text text-muted small flex-grow-1 mb-3">
                    {style.description || lang('noDescription')}
                  </p>
                  <div className="d-flex gap-2">
                    <a
                      href={publicUrl(style.css_url)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="btn btn-sm btn-outline-secondary flex-grow-1"
                    >
                      <i className="bi bi-eye me-1"></i>
                      {lang('viewCSS')}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
