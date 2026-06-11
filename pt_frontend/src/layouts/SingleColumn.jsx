export default function SingleColumn({ header, tags, main, footer }) {
  return (
    <div className="layout-wrap layout-single-column">
      {header}
      {tags}
      {main}
      {footer}
    </div>
  )
}
