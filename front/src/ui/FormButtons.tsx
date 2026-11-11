import { useTranslation } from 'react-i18next'

type Props = {
  sending: boolean
  // "Zapisz" by default; "Dodaj" for the forms that add to the list under them
  submitLabel?: string
  // shows "Anuluj"; what it does is up to the page (back to the list, or where the user came from)
  onCancel?: () => void
}

// The buttons under a form, as in the web app. While the form is on its way the main one waits, so one click sends
// one form.
export function FormButtons({ sending, submitLabel, onCancel }: Props) {
  const { t } = useTranslation()

  return (
    <div className="flex gap-2 pt-2">
      <button
        type="submit"
        disabled={sending}
        className="cursor-pointer rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700
          disabled:cursor-wait disabled:opacity-50"
      >
        {sending ? t('app.saving') : submitLabel ?? t('form.save')}
      </button>
      {onCancel && (
        <button
          type="button"
          onClick={onCancel}
          className="cursor-pointer rounded-md border border-slate-300 dark:border-slate-600 px-4 py-2 text-slate-700
            dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
        >
          {t('form.cancel')}
        </button>
      )}
    </div>
  )
}
