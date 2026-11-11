import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { AmountInput } from '../ui/AmountInput'
import { fieldErrors, inputClass, toApiDecimal } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import type { GoldAssetInput, GoldForm } from './goldAssetApi'

type Field = 'form' | 'ounces' | 'description'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  formId: 'form',
  ounces: 'ounces',
  description: 'description',
}

export type GoldAssetFormValues = { formId: number | null; ounces: string; description: string }

type Props = {
  // the gold forms to choose from (bar, coin…)
  forms: GoldForm[]
  initial: GoldAssetFormValues
  // a new asset has one "Dodaj" button, as in the web app; an edit has Zapisz / Anuluj
  adding: boolean
  // sends the form; when it fails, the form shows why
  save: (input: GoldAssetInput) => Promise<void>
}

// The form of a new gold asset and of an edit, as in the web app: form, ounces, description.
export function GoldAssetForm({ forms, initial, adding, save }: Props) {
  const { t } = useTranslation()
  const [values, setValues] = React.useState(initial)
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')
  const [sending, setSending] = React.useState(false)

  function set<K extends keyof GoldAssetFormValues>(key: K, value: GoldAssetFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    setErrors({})
    setSending(true)
    try {
      await save({ ...values, ounces: toApiDecimal(values.ounces) })
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else {
        setFailure(errorMessage(error, t('app.save_gold_asset_failed')))
      }
    } finally {
      setSending(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {failure && (
        <p role="alert" className="rounded bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300">
          {failure}
        </p>
      )}
      <FormRow label={t('gold_asset.shape')} htmlFor="form" error={errors.form}>
        <select
          id="form"
          value={values.formId ?? ''}
          onChange={(event) => set('formId', Number(event.target.value))}
          className={inputClass(errors.form)}
        >
          {forms.map((form) => <option key={form.id} value={form.id}>{form.name}</option>)}
        </select>
      </FormRow>
      <FormRow label={t('gold_asset.ounces')} htmlFor="ounces" error={errors.ounces}>
        <AmountInput
          id="ounces"
          value={values.ounces}
          onChange={(ounces) => set('ounces', ounces)}
          allowMinus={false}
          placeholder={t('app.ounces_placeholder')}
          className={inputClass(errors.ounces)}
        />
      </FormRow>
      <FormRow label={t('form.description')} htmlFor="description" error={errors.description}>
        <input
          id="description"
          type="text"
          maxLength={255}
          value={values.description}
          onChange={(event) => set('description', event.target.value)}
          className={inputClass(errors.description)}
        />
      </FormRow>
      {adding
        ? (
          <div className="pt-2">
            <button
              type="submit"
              disabled={sending}
              className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700
                disabled:opacity-50"
            >
              {sending ? t('app.saving') : t('common.add')}
            </button>
          </div>
        )
        : <FormButtons sending={sending} />}
    </form>
  )
}
