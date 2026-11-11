import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { fieldErrors, inputClass, MAX_DATE, MIN_DATE, toApiAmount } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { type ContractorChoice, ContractorField } from './ContractorField'
import type { AccountChoice, OperationInput } from './operationApi'

type Field = 'account' | 'contractor' | 'date' | 'amount' | 'description'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  accountId: 'account',
  contractorId: 'contractor',
  contractorName: 'contractor',
  operationDate: 'date',
  amount: 'amount',
  description: 'description',
}

export type OperationFormValues = {
  accountId: number | null
  contractor: ContractorChoice | null
  operationDate: string
  amount: string
  description: string
  confirmed: boolean
}

type Props = {
  title: string
  initial: OperationFormValues
  // the accounts to choose from; for an edit only the operation's own one, which cannot change
  accounts: AccountChoice[]
  accountFixed: boolean
  // sends the form; resolves to where to go afterwards
  save: (input: OperationInput, accountId: number) => Promise<string>
}

// The form of a new operation and of an edit, as in the web app: account, contractor, date, amount, description,
// confirmed. The API checks the values; its field errors appear under their fields, in Polish.
export function OperationForm({ title, initial, accounts, accountFixed, save }: Props) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [values, setValues] = React.useState(initial)
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')
  const [sending, setSending] = React.useState(false)

  function set<K extends keyof OperationFormValues>(key: K, value: OperationFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    // What the API cannot tell: no account to choose, or a contractor typed but not picked from the list.
    const ownErrors: Partial<Record<Field, string>> = {}
    if (values.accountId === null) {
      ownErrors.account = t('operation.account_required', { ns: 'validators' })
    }
    if (values.contractor === null) {
      ownErrors.contractor = t('contractor.required', { ns: 'validators' })
    }
    setErrors(ownErrors)
    if (values.accountId === null || values.contractor === null) {
      setFailure(t('form.fill_required_fields'))
      return
    }

    const input: OperationInput = {
      ...(values.contractor.id === null
        ? { contractorName: values.contractor.name }
        : { contractorId: values.contractor.id }),
      operationDate: values.operationDate,
      amount: toApiAmount(values.amount),
      description: values.description.trim() === '' ? null : values.description,
      confirmed: values.confirmed,
    }
    setSending(true)
    try {
      navigate(await save(input, values.accountId))
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else {
        setFailure(errorMessage(error, t('app.save_operation_failed')))
      }
    } finally {
      setSending(false)
    }
  }

  return (
    <section className="rounded-xl bg-white dark:bg-slate-900 p-6 shadow">
      <h1 className="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{title}</h1>
      {failure && (
        <p role="alert" className="mb-4 rounded bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700
          dark:text-red-300">
          {failure}
        </p>
      )}
      <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
        <FormRow label={t('form.account')} htmlFor="account" error={errors.account}>
          <select
            id="account"
            value={values.accountId ?? ''}
            onChange={(event) => set('accountId', Number(event.target.value))}
            disabled={accountFixed}
            className={inputClass(errors.account)}
          >
            {accounts.map((account) => (
              <option key={account.id} value={account.id}>{account.displayLabel}</option>
            ))}
          </select>
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
        <FormRow label={t('form.date')} htmlFor="date" error={errors.date}>
          <DateInput
            id="date"
            value={values.operationDate}
            min={MIN_DATE}
            max={MAX_DATE}
            onChange={(date) => set('operationDate', date)}
            className={inputClass(errors.date)}
          />
        </FormRow>
        <FormRow label={t('form.amount')} htmlFor="amount" error={errors.amount}>
          <AmountInput
            id="amount"
            value={values.amount}
            onChange={(amount) => set('amount', amount)}
            allowMinus
            placeholder={t('app.amount_placeholder')}
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
        <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
          <input
            type="checkbox"
            checked={values.confirmed}
            onChange={(event) => set('confirmed', event.target.checked)}
            className="size-4"
          />
          {t('form.confirmed')}
        </label>
        <FormButtons sending={sending} />
      </form>
    </section>
  )
}
