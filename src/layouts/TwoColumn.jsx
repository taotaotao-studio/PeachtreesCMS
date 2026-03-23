export default function TwoColumn({ header, tags, main, footer, sidebarPosition = 'left' }) {
  const positionClass = sidebarPosition === 'right' ? 'sidebar-right' : 'sidebar-left'

  return (
    <div className={`layout-wrap layout-two-column ${positionClass}`}>
      <div className="sidebar">
        {header}
        {tags}
      </div>
      <div className="maincon">
        {main}
        {footer}
      </div>
    </div>
  )
}
