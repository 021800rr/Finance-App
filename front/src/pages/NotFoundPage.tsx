import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'

export function NotFoundPage() {
  const { t } = useTranslation()

  return (
    <section>
      <h1 className="text-2xl font-semibold text-slate-900 dark:text-slate-100">{t('error.not_found_title')}</h1>
      <p className="mt-2 text-slate-500 dark:text-slate-400">{t('error.not_found_text')}</p>
      <Link to="/" className="mt-2 inline-block text-indigo-600 dark:text-indigo-400 hover:underline">
        {t('error.back_home')}
      </Link>
    </section>
  )
}
