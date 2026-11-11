import { useTranslation } from 'react-i18next'
import { LANGUAGES } from '../i18n'

// PL / EN: every text and amount of the app follows at once, without a reload.
export function LanguageSwitcher() {
  const { t, i18n } = useTranslation()

  return (
    <div
      role="group"
      aria-label={t('app.language')}
      className="flex overflow-hidden rounded-md border border-slate-300 dark:border-slate-600"
    >
      {LANGUAGES.map((language) => (
        <button
          key={language}
          type="button"
          onClick={() => void i18n.changeLanguage(language)}
          aria-pressed={i18n.resolvedLanguage === language}
          className={i18n.resolvedLanguage === language
            ? 'bg-slate-700 px-2 py-1 text-xs font-medium text-white'
            : 'cursor-pointer px-2 py-1 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-100 '
              + 'dark:hover:bg-slate-800'}
        >
          {language.toUpperCase()}
        </button>
      ))}
    </div>
  )
}
