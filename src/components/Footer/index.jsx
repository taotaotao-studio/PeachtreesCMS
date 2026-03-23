import { useTheme } from '../../contexts/ThemeContext'
import { sanitizeHtml } from '../../utils/sanitizeHtml'

export default function Footer() {
  const { siteOptions } = useTheme()

  const defaultFooter = `© ${new Date().getFullYear()} ${siteOptions.site_title || ''}`
  const footerContent = siteOptions.footer_text || defaultFooter

  // 安全地过滤HTML
  const safeHtml = sanitizeHtml(footerContent)

  return (
    <footer className="footer">
      <div
        className="inner"
        dangerouslySetInnerHTML={{ __html: safeHtml }}
      />
    </footer>
  )
}
