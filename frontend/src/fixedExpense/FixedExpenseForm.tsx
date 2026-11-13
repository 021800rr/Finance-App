import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { type ContractorChoice, ContractorField } from '../operation/ContractorField'
import { AmountInput } from '../ui/AmountInput'
import { Alert } from '../ui/Feedback'
import { fieldErrorProps, inputClass, toApiAmount } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { useApiForm } from '../ui/useApiForm'
import type { FixedExpenseInput } from './fixedExpenseApi'

type Field = 'day' | 'contractor' | 'amount' | 'description'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  day: 'day',
  contractorId: 'contractor',
  contractorName: 'contractor',
  amount: 'amount',
  description: 'description',
}

export type FixedExpenseFormValues = {
  day: string
  contractor: ContractorChoice | null
  amount: string
  description: string
}

type Props = {
  initial: FixedExpenseFormValues
  // sends the form; when it fails, the form shows why
  save: (input: FixedExpenseInput) => Promise<void>
  // "Dodaj" for a new one (the list is right under it), "Zapisz" for an edit
  submitLabel?: string
  // an edit has "Anuluj"
  onCancel?: () => void
}

// The form of a new fixed expense and of an edit, as in the web app: day, contractor, amount, description. The API
// checks the values; its field errors appear under their fields.
export function FixedExpenseForm({ initial, save, submitLabel, onCancel }: Props) {
  const { t } = useTranslation()
  const [values, setValues] = React.useState(initial)
  const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_fixed_expense_failed') })

  function set<K extends keyof FixedExpenseFormValues>(key: K, value: FixedExpenseFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    // What the API cannot tell: a contractor typed but not picked from the list. (An edit without a contractor
    // would even keep the old one.)
    const { contractor } = values
    if (contractor === null) {
      form.fail({ contractor: t('contractor.required', { ns: 'validators' }) })
      return
    }

    const input: FixedExpenseInput = {
      ...(contractor.id === null ? { contractorName: contractor.name } : { contractorId: contractor.id }),
      // an empty day goes as null, and the API asks for it
      day: values.day === '' ? null : Number(values.day),
      amount: toApiAmount(values.amount),
      description: values.description.trim() === '' ? null : values.description,
    }
    void form.submit(() => save(input))
  }

  const { errors } = form

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {form.message && <Alert tone="error">{form.message}</Alert>}
      <FormRow label={t('form.day')} htmlFor="day" error={errors.day}>
        {/* Digits only, as the day of a month; the API checks 1-28. */}
        <div className="w-24">
          <input
            id="day"
            {...fieldErrorProps('day', errors.day)}
            type="text"
            inputMode="numeric"
            maxLength={2}
            value={values.day}
            onChange={(event) => set('day', event.target.value.replace(/\D/g, ''))}
            placeholder={t('app.day_placeholder')}
            className={`${inputClass(errors.day)} tabular-nums`}
          />
        </div>
      </FormRow>
      <FormRow label={t('form.contractor')} htmlFor="contractor">
        <ContractorField
          id="contractor"
          value={values.contractor}
          onChange={(contractor) => set('contractor', contractor)}
          error={errors.contractor}
          inputClassName={inputClass(errors.contractor)}
        />
      </FormRow>
      <FormRow label={t('form.amount')} htmlFor="amount" error={errors.amount}>
        <AmountInput
          id="amount"
          {...fieldErrorProps('amount', errors.amount)}
          value={values.amount}
          onChange={(amount) => set('amount', amount)}
          allowMinus
          placeholder={t('app.fixed_amount_placeholder')}
          className={inputClass(errors.amount)}
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
