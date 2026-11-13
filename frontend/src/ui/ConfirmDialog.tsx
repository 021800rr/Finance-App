import * as React from 'react'
import { useTranslation } from 'react-i18next'

type Props = {
  open: boolean
  message: string
  onConfirm: () => void
  onClose: () => void
  disabled?: boolean
}

export function ConfirmDialog({ open, message, onConfirm, onClose, disabled }: Props) {
  const { t } = useTranslation()
  const dialog = React.useRef<HTMLDialogElement>(null)
  const cancelButton = React.useRef<HTMLButtonElement>(null)
  const messageId = React.useId()

  React.useEffect(() => {
    const element = dialog.current!
    if (!open) {
      return
    }

    element.showModal()
    cancelButton.current?.focus()

    return () => element.close()
  }, [open])

  return (
    <dialog
      ref={dialog}
      aria-labelledby={messageId}
      onCancel={(event) => {
        event.preventDefault()
        onClose()
      }}
      className="m-auto w-[calc(100%_-_2rem)] max-w-sm rounded-xl bg-white p-6 text-slate-900 shadow-xl
        backdrop:bg-black/40 dark:bg-slate-900 dark:text-slate-100"
    >
      <p id={messageId} className="text-base font-semibold">{message}</p>
      <div className="mt-6 flex justify-end gap-3">
        <button
          ref={cancelButton}
          type="button"
          onClick={onClose}
          className="cursor-pointer rounded-md border border-slate-300 px-4 py-2 text-sm font-medium
            hover:bg-slate-100 dark:border-slate-600 dark:hover:bg-slate-800"
        >
          {t('form.cancel')}
        </button>
        <button
          type="button"
          disabled={disabled}
          onClick={() => {
            onClose()
            onConfirm()
          }}
          className="cursor-pointer rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white
            hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-50"
        >
          {t('app.confirm')}
        </button>
      </div>
    </dialog>
  )
}
