import { NavLink } from 'react-router-dom'
import { useTheme } from '../../contexts/ThemeContext'
import { toAbsolutePath } from '../../utils/path'

export default function Header() {
  const { siteOptions } = useTheme()

  return (
    <header className="header">
      <h1>
        <NavLink to="/" className="header-link">
          {siteOptions.show_logo ? (
            <img src={toAbsolutePath('/theme/default/logo.png')} alt={siteOptions.site_title} />
          ) : (
            siteOptions.site_title
          )}
        </NavLink>
      </h1>
    </header>
  )
}
