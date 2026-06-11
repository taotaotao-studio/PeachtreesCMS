import SingleColumn from './SingleColumn'
import TwoColumn from './TwoColumn'

export function getLayoutComponent(template) {
  if (template === 'two-column') return TwoColumn
  return SingleColumn
}

export { SingleColumn, TwoColumn }
