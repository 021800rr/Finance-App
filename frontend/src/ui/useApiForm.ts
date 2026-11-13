import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { fieldErrors } from './form'

type Options<F extends string> = {
  // the fields of the API's field errors (propertyPath) on the fields of the form
  fieldOfPath: Record<string, F>
  // refusals that come without field errors, only with an errorCode, and the field each is about
  fieldOfErrorCode?: Record<string, F>
  // what to say when the save fails for another reason
  failure: string
}

// What every form of the app does with the API: sends, waits (the button says "Zapisuję…"), and when the API refuses,
// puts its field errors under the fields and says above the form what went wrong.
//
//   const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_rate_failed') })
//   <form onSubmit={(event) => { event.preventDefault(); void form.submit(() => save(input)) }}>
//   <FormRow error={form.errors.rate}>…
export function useApiForm<F extends string>({ fieldOfPath, fieldOfErrorCode = {}, failure }: Options<F>) {
  const { t } = useTranslation()
  const [errors, setErrors] = React.useState<Partial<Record<F, string>>>({})
  const [message, setMessage] = React.useState('')
  const [sending, setSending] = React.useState(false)

  // Errors the form finds itself, before asking the API (e.g. a contractor typed but not picked from the list).
  function fail(ownErrors: Partial<Record<F, string>>) {
    setErrors(ownErrors)
    setMessage(t('form.fill_required_fields'))
  }

  async function submit(send: () => Promise<unknown>) {
    setErrors({})
    setMessage('')
    setSending(true)
    try {
      await send()
    } catch (error) {
      const field = error instanceof ApiError && error.errorCode !== undefined
        ? fieldOfErrorCode[error.errorCode]
        : undefined
      if (error instanceof ApiError && error.violations.length > 0) {
        fail(fieldErrors(error.violations, fieldOfPath))
      } else if (field !== undefined) {
        const text = errorMessage(error, failure)
        const own: Partial<Record<F, string>> = {}
        own[field] = text
        setErrors(own)
        setMessage(text)
      } else {
        setMessage(errorMessage(error, failure))
      }
    } finally {
      setSending(false)
    }
  }

  return { errors, message, sending, fail, submit }
}
