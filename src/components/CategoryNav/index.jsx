import { useEffect, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { tagsAPI } from '../../services/api'

export default function CategoryNav({ className = '' }) {
  const [tags, setTags] = useState([])
  const location = useLocation()

  useEffect(() => {
    loadTags()
  }, [])

  const loadTags = async () => {
    try {
      const res = await tagsAPI.getList()
      if (res.success) {
        setTags(res.data)
      }
    } catch (err) {
      console.error('Failed to load tags:', err)
    }
  }

  return (
    <nav className={className}>
      <ul>
        {tags.map(tag => (
          <li key={tag.id}>
            <NavLink
              to={`/?tag=${tag.tag}`}
              className={({ isActive }) => {
                const isMatch = isActive && location.search === `?tag=${tag.tag}`
                return isMatch ? 'active' : ''
              }}
            >
              {tag.display_name}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
