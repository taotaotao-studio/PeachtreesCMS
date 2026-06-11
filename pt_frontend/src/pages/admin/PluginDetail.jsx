import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { pluginsAPI } from '../../services/api'
import { useLanguage } from '../../contexts/LanguageContext'

export default function PluginDetail() {
  const { slug } = useParams()
  const { lang, language } = useLanguage()
  const [plugin, setPlugin] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const loadPlugin = async () => {
      setLoading(true)
      try {
        const res = await pluginsAPI.getList()
        if (res.success) {
          const found = res.data.find((item) => item.slug === slug)
          setPlugin(found || null)
        } else {
          setPlugin(null)
        }
      } catch (err) {
        console.error('Failed to load plugins:', err)
        setPlugin(null)
      } finally {
        setLoading(false)
      }
    }

    loadPlugin()
  }, [slug])

  const displayName = useMemo(() => {
    if (!plugin) return ''
    return language === 'en-US'
      ? plugin.name_en || plugin.name
      : plugin.name
  }, [plugin, language])

  const displayDesc = useMemo(() => {
    if (!plugin) return ''
    return language === 'en-US'
      ? plugin.description_en || plugin.description
      : plugin.description
  }, [plugin, language])

  if (loading) {
    return (
      <div className="text-center py-5">
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">{lang('loading')}</span>
        </div>
      </div>
    )
  }

  if (!plugin) {
    return (
      <div className="alert alert-warning">{lang('pluginNotFound')}</div>
    )
  }

  return (
    <div className="card shadow-sm">
      <div className="card-header bg-white">
        <h5 className="mb-0">
          <i className="bi bi-plug me-2"></i>
          {displayName}
        </h5>
      </div>
      <div className="card-body">
        {displayDesc && (
          <p className="text-muted mb-3">{displayDesc}</p>
        )}
        <div className="row g-3">
          <div className="col-md-6">
            <div className="text-muted small">{lang('pluginSlug')}</div>
            <div className="fw-medium">{plugin.slug}</div>
          </div>
          <div className="col-md-6">
            <div className="text-muted small">{lang('version')}</div>
            <div className="fw-medium">{plugin.version || '-'}</div>
          </div>
        </div>
      </div>
    </div>
  )
}