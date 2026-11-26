import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { useTheme } from './ThemeContext'
import { languageUrl } from '../utils/path'

const LanguageContext = createContext(null)

const SUPPORTED_LANGS = ['zh-CN', 'en-US', 'ja-JP', 'de-DE', 'fr-FR', 'es-ES']

// <html lang> 映射：ja-JP -> ja 等，与文档描述一致（此前非中文一律写 'en'）
const HTML_LANG_MAP = {
  'zh-CN': 'zh-CN',
  'en-US': 'en',
  'ja-JP': 'ja',
  'de-DE': 'de',
  'fr-FR': 'fr',
  'es-ES': 'es'
}

const LANG_STORAGE_KEY = 'pt_language'

/** navigator.languages 与支持的六种语言做精确/前缀匹配 */
function detectBrowserLanguage() {
  if (typeof navigator === 'undefined') return null
  const candidates = navigator.languages || [navigator.language || '']
  for (const candidate of candidates) {
    const exact = SUPPORTED_LANGS.find((l) => l.toLowerCase() === candidate.toLowerCase())
    if (exact) return exact
    const base = candidate.toLowerCase().split('-')[0]
    const byBase = SUPPORTED_LANGS.find((l) => l.toLowerCase().split('-')[0] === base)
    if (byBase) return byBase
  }
  return null
}

export function LanguageProvider({ children }) {
  const { siteOptions, loading: themeLoading } = useTheme()

  // Default to Simplified Chinese
  const [language, setLanguage] = useState('zh-CN')
  const [translations, setTranslations] = useState({})
  const [loading, setLoading] = useState(true)

  const loadLanguage = useCallback(async (langCode) => {
    try {
      const response = await fetch(languageUrl(langCode))
      if (!response.ok) throw new Error(`Failed to load ${langCode}`)
      const data = await response.json()
      setTranslations(prev => ({ ...prev, [langCode]: data }))
    } catch (err) {
      console.error(`Error loading language file:`, err)
    }
  }, [])

  // After siteOptions is loaded, decide the language:
  //   1. user's manual choice persisted in localStorage
  //   2. browser language (auto-detect, first visit)
  //   3. the site's default language
  useEffect(() => {
    if (themeLoading) return

    const stored = (() => {
      try { return localStorage.getItem(LANG_STORAGE_KEY) } catch { return null }
    })()
    const siteLang = siteOptions?.default_lang || 'zh-CN'
    const targetLang =
      (stored && SUPPORTED_LANGS.includes(stored) && stored) ||
      detectBrowserLanguage() ||
      siteLang

    const applyLanguage = async () => {
      // 并行加载目标语言 + 回退语言（减少首屏等待）
      const otherLang = targetLang === 'zh-CN' ? 'en-US' : 'zh-CN'
      await Promise.all([loadLanguage(targetLang), loadLanguage(otherLang)])

      setLanguage(targetLang)
      setLoading(false)
    }

    applyLanguage()
  }, [themeLoading, siteOptions?.default_lang, loadLanguage])

  useEffect(() => {
    document.documentElement.lang = HTML_LANG_MAP[language] || language.split('-')[0]
  }, [language])

  const lang = (key) => {
    const current = translations[language] || {}
    const fallback = translations['zh-CN'] || translations['en-US'] || {}

    return current[key] || fallback[key] || key
  }

  const setLanguageWithStorage = (newLang) => {
    if (!SUPPORTED_LANGS.includes(newLang)) return
    try { localStorage.setItem(LANG_STORAGE_KEY, newLang) } catch { /* private mode */ }
    setLanguage(newLang)
    // 立即补载语言包（避免切换后短暂缺词）
    if (!translations[newLang]) {
      loadLanguage(newLang)
    }
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
