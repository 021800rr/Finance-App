import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'

// Zapisz / Anuluj under a form, as in the web app; Anuluj goes back where the user came from.
export function FormButtons({ sending }: { sending: boolean }) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  return (
    <div className="flex gap-2 pt-2">
      <button
        type="submit"
        disabled={sending}
        className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {sending ? t('app.saving') : t('form.save')}
      </button>
      <button
        type="button"
        onClick={() => navigate(-1)}
        className="rounded-md border border-slate-300 dark:border-slate-600 px-4 py-2 text-slate-700
          dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
      >
        {t('form.cancel')}
      </button>
    </div>
  )
}
