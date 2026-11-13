import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { AmountInput } from '../ui/AmountInput'
import { Alert } from '../ui/Feedback'
import { fieldErrorProps, inputClass, toApiDecimal } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { useApiForm } from '../ui/useApiForm'
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
  // sends the form; when it fails, the form shows why
  save: (input: GoldAssetInput) => Promise<void>
  // "Dodaj" for a new one (the list is right under it), "Zapisz" for an edit
  submitLabel?: string
  // an edit has "Anuluj"
  onCancel?: () => void
}

// The form of a new gold asset and of an edit, as in the web app: form, ounces, description.
export function GoldAssetForm({ forms, initial, save, submitLabel, onCancel }: Props) {
  const { t } = useTranslation()
  const [values, setValues] = React.useState(initial)
  const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_gold_asset_failed') })

  function set<K extends keyof GoldAssetFormValues>(key: K, value: GoldAssetFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    void form.submit(() => save({ ...values, ounces: toApiDecimal(values.ounces) }))
  }

  const { errors } = form

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {form.message && <Alert tone="error">{form.message}</Alert>}
      <FormRow label={t('gold_asset.shape')} htmlFor="form" error={errors.form}>
        <select
          id="form"
          {...fieldErrorProps('form', errors.form)}
          value={values.formId ?? ''}
          onChange={(event) => set('formId', Number(event.target.value))}
          className={inputClass(errors.form)}
        >
          {forms.map((shape) => <option key={shape.id} value={shape.id}>{shape.name}</option>)}
        </select>
      </FormRow>
      <FormRow label={t('gold_asset.ounces')} htmlFor="ounces" error={errors.ounces}>
        <AmountInput
          id="ounces"
          {...fieldErrorProps('ounces', errors.ounces)}
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
          {...fieldErrorProps('description', errors.description)}
          type="text"
          maxLength={255}
          value={values.description}
          onChange={(event) => set('description', event.target.value)}
          className={inputClass(errors.description)}
        />
      </FormRow>
      <FormButtons sending={form.sending} submitLabel={submitLabel} onCancel={onCancel} />
    </form>
  )
}
