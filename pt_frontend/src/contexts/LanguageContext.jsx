import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { useTheme } from './ThemeContext'

const LanguageContext = createContext(null)

export function LanguageProvider({ children }) {
  const { siteOptions, loading: themeLoading } = useTheme()
  
  // 默认使用简体中文
  const [language, setLanguage] = useState('zh-CN')
  const [translations, setTranslations] = useState({})
  const [loading, setLoading] = useState(true)

  const loadLanguage = useCallback(async (langCode) => {
    try {
      const response = await fetch(`/languages/${langCode}.json`)
      if (!response.ok) throw new Error(`Failed to load ${langCode}`)
      const data = await response.json()
      setTranslations(prev => ({ ...prev, [langCode]: data }))
    } catch (err) {
      console.error(`Error loading language file:`, err)
    }
  }, [])

  // 当 siteOptions 加载完成后，使用站点默认语言
  useEffect(() => {
    if (themeLoading) return
    
    const siteLang = siteOptions?.default_lang || 'zh-CN'
    
    const applyLanguage = async () => {
      // 加载站点默认语言
      await loadLanguage(siteLang)
      
      // 也加载另一种语言作为备用
      const otherLang = siteLang === 'zh-CN' ? 'en-US' : 'zh-CN'
      await loadLanguage(otherLang)
      
      setLanguage(siteLang)
      setLoading(false)
    }

    applyLanguage()
  }, [themeLoading, siteOptions?.default_lang, loadLanguage])

  useEffect(() => {
    document.documentElement.lang = language === 'zh-CN' ? 'zh-CN' : 'en'
  }, [language])

  const lang = (key) => {
    const current = translations[language] || {}
    const fallback = translations['zh-CN'] || translations['en-US'] || {}

    return current[key] || fallback[key] || key
  }

  const setLanguageWithStorage = (newLang) => {
    setLanguage(newLang)
  }

  if (loading) {
    return <div style={{ padding: '20px', textAlign: 'center' }}>Loading Language...</div>
  }

  return (
    <LanguageContext.Provider value={{ language, lang, loading, setLanguage: setLanguageWithStorage }}>
      {children}
    </LanguageContext.Provider>
  )
}

export function useLanguage() {
  const context = useContext(LanguageContext)
  if (!context) {
    throw new Error('useLanguage must be used within a LanguageProvider')
  }
  return context
}
