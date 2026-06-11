import { useLanguage } from '../../contexts/LanguageContext'

export default function Pager({ page, totalPages, onPageChange }) {
  const { lang } = useLanguage()

  if (totalPages <= 1) return null

  return (
    <nav className="main-pager-nav">
      <ul className="pagination main-pager-list">
        <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}>
          <button
            className="page-link main-pager-link"
            onClick={() => onPageChange(page - 1)}
            disabled={page <= 1}
          >
            <i className="bi bi-chevron-left"></i>
            {lang('prev')}
          </button>
        </li>

        <li className="page-item disabled">
          <span className="page-link main-pager-current">
            {page} / {totalPages}
          </span>
        </li>

        <li className={`page-item ${page >= totalPages ? 'disabled' : ''}`}>
          <button
            className="page-link main-pager-link"
            onClick={() => onPageChange(page + 1)}
            disabled={page >= totalPages}
          >
            {lang('next')}
            <i className="bi bi-chevron-right"></i>
          </button>
        </li>
      </ul>
    </nav>
  )
}