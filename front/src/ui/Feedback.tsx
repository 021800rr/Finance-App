import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'

// A line of grey text: what the page says while there is nothing (yet) to show.
export function Note({ children }: { children: React.ReactNode }) {
  return <p className="text-slate-500 dark:text-slate-400">{children}</p>
}

// What a page says while it waits for the API.
export function Loading({ text }: { text?: string }) {
  const { t } = useTranslation()

  return <Note>{text ?? t('app.loading')}</Note>
}

// Why something could not be read: the text of the API's error code, or the fallback.
export function ErrorText({ error, fallback }: { error: unknown; fallback: string }) {
  return <p role="alert" className="text-red-600 dark:text-red-400">{errorMessage(error, fallback)}</p>
}

const TONES = {
  // a form that was not saved
  error: 'bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300',
  // an action the API refused (delete, plan…), the page itself is fine
  warning: 'bg-amber-100 dark:bg-amber-950 text-amber-900 dark:text-amber-200',
}

// A message in a coloured box, read out by screen readers as soon as it appears.
export function Alert({ tone, children }: { tone: keyof typeof TONES; children: React.ReactNode }) {
  return <p role="alert" className={`rounded px-3 py-2 text-sm ${TONES[tone]}`}>{children}</p>
}
