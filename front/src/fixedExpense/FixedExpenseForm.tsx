import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { type ContractorChoice, ContractorField } from '../operation/ContractorField'
import { AmountInput } from '../ui/AmountInput'
import { fieldErrors, inputClass, toApiAmount } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
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
  // a new fixed expense has one "Dodaj" button, as in the web app; an edit has Zapisz / Anuluj
  adding: boolean
  // sends the form; when it fails, the form shows why
  save: (input: FixedExpenseInput) => Promise<void>
}

// The form of a new fixed expense and of an edit, as in the web app: day, contractor, amount, description. The API
// checks the values; its field errors appear under their fields.
export function FixedExpenseForm({ initial, adding, save }: Props) {
  const { t } = useTranslation()
  const [values, setValues] = React.useState(initial)
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')
  const [sending, setSending] = React.useState(false)

  function set<K extends keyof FixedExpenseFormValues>(key: K, value: FixedExpenseFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    // What the API cannot tell: a contractor typed but not picked from the list. (An edit without a contractor
    // would even keep the old one.)
    if (values.contractor === null) {
      setErrors({ contractor: t('contractor.required', { ns: 'validators' }) })
      setFailure(t('form.fill_required_fields'))
      return
    }
    setErrors({})

    const input: FixedExpenseInput = {
      ...(values.contractor.id === null
        ? { contractorName: values.contractor.name }
        : { contractorId: values.contractor.id }),
      // an empty day goes as null, and the API asks for it
      day: values.day === '' ? null : Number(values.day),
      amount: toApiAmount(values.amount),
      description: values.description.trim() === '' ? null : values.description,
    }
    setSending(true)
    try {
      await save(input)
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else {
        setFailure(errorMessage(error, t('app.save_fixed_expense_failed')))
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
      <FormRow label={t('form.day')} htmlFor="day" error={errors.day}>
        {/* Digits only, as the day of a month; the API checks 1-28. */}
        <div className="w-24">
          <input
            id="day"
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
              {sending ? t('app.saving') : t('fixed_expense.add')}
            </button>
          </div>
        )
        : <FormButtons sending={sending} />}
    </form>
  )
}
