import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { AmountInput } from '../ui/AmountInput'
import { Card } from '../ui/Card'
import { DateInput } from '../ui/DateInput'
import { Alert } from '../ui/Feedback'
import { fieldErrorProps, inputClass, MAX_DATE, MIN_DATE, toApiAmount } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { useApiForm } from '../ui/useApiForm'
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
  // sends the form; when it fails, the form shows why
  save: (input: OperationInput, accountId: number) => Promise<void>
  onCancel: () => void
}

// The form of a new operation and of an edit, as in the web app: account, contractor, date, amount, description,
// confirmed. The API checks the values; its field errors appear under their fields.
export function OperationForm({ title, initial, accounts, accountFixed, save, onCancel }: Props) {
  const { t } = useTranslation()
  const [values, setValues] = React.useState(initial)
  const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_operation_failed') })

  function set<K extends keyof OperationFormValues>(key: K, value: OperationFormValues[K]) {
    setValues((previous) => ({ ...previous, [key]: value }))
  }

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    // What the API cannot tell: no account to choose, or a contractor typed but not picked from the list.
    const { accountId, contractor } = values
    if (accountId === null || contractor === null) {
      form.fail({
        ...(accountId === null && { account: t('operation.account_required', { ns: 'validators' }) }),
        ...(contractor === null && { contractor: t('contractor.required', { ns: 'validators' }) }),
      })
      return
    }

    const input: OperationInput = {
      ...(contractor.id === null ? { contractorName: contractor.name } : { contractorId: contractor.id }),
      operationDate: values.operationDate,
      amount: toApiAmount(values.amount),
      description: values.description.trim() === '' ? null : values.description,
      confirmed: values.confirmed,
    }
    void form.submit(() => save(input, accountId))
  }

  const { errors } = form

  return (
    <Card title={title}>
      {form.message && <Alert tone="error">{form.message}</Alert>}
      <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
        <FormRow label={t('form.account')} htmlFor="account" error={errors.account}>
          <select
            id="account"
            {...fieldErrorProps('account', errors.account)}
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
            {...fieldErrorProps('contractor', errors.contractor)}
            value={values.contractor}
            onChange={(contractor) => set('contractor', contractor)}
            error={errors.contractor}
            inputClassName={inputClass(errors.contractor)}
          />
        </FormRow>
        <FormRow label={t('form.date')} htmlFor="date" error={errors.date}>
          <DateInput
            id="date"
            {...fieldErrorProps('date', errors.date)}
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
            {...fieldErrorProps('amount', errors.amount)}
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
            {...fieldErrorProps('description', errors.description)}
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
        <FormButtons sending={form.sending} onCancel={onCancel} />
      </form>
    </Card>
  )
}
