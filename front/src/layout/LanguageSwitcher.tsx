import { useTranslation } from 'react-i18next'

// Offer only the other language; every text and amount follows at once, without a reload.
export function LanguageSwitcher() {
  const { i18n } = useTranslation()
  const language = i18n.resolvedLanguage === 'en' ? 'pl' : 'en'
  const label = language === 'en' ? 'English' : 'Polski'

  return (
    <button
      type="button"
      onClick={() => void i18n.changeLanguage(language)}
      title={label}
      aria-label={label}
      lang={language}
      className="min-w-9 cursor-pointer rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-700
        hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
    >
      {language.toUpperCase()}
    </button>
  )
}
