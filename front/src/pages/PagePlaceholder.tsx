import { useTranslation } from 'react-i18next'

// A page that is not built yet: its address and its place in the menu already work.
export function PagePlaceholder({ title }: { title: string }) {
  const { t } = useTranslation()

  return (
    <section>
      <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
      <p className="mt-2 text-slate-500">{t('app.soon')}</p>
    </section>
  )
}
