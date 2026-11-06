import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { type AccountChoice, useAccounts } from '../operation/operationApi'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { fieldErrors, inputClass, MAX_DATE, MIN_DATE, toApiAmount, today } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { type TransferInput, useCreateTransfer } from './transferApi'

type Field = 'from' | 'to' | 'amountFrom' | 'amountTo' | 'date' | 'description'

const FIELD_OF_PATH: Record<string, Field> = {
  fromAccountId: 'from',
  toAccountId: 'to',
  amountFrom: 'amountFrom',
  amountTo: 'amountTo',
  operationDate: 'date',
  description: 'description',
}

// The refusals of a transfer that come without field errors (only an errorCode), and the field each is about.
const FIELD_OF_ERROR_CODE: Record<string, Field> = {
  'transfer.accounts_must_differ': 'to',
  'transfer.amount_to_required_for_currency_exchange': 'amountTo',
}

// /transfer/new: a transfer between two own accounts, or an exchange between two currencies.
export function TransferPage() {
  const { t } = useTranslation()
  const accounts = useAccounts()

  if (accounts.isPending) {
    return <p className="text-slate-500">{t('app.loading')}</p>
  }
  if (accounts.isError) {
    return <p role="alert" className="text-red-600">{errorMessage(accounts.error, t('app.accounts_load_failed'))}</p>
  }

  return <TransferForm accounts={accounts.data.filter((account) => !account.hidden)} />
}

// As in the web app: from, to, the amount that leaves and the amount that arrives, date, description. With one
// currency the second amount is the first one, so its field is blocked; what was typed in it comes back when the
// currencies differ again.
function TransferForm({ accounts }: { accounts: AccountChoice[] }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateTransfer()
  const [fromId, setFromId] = React.useState(accounts[0]?.id ?? null)
  // Another account than the first one, so the form does not start with a transfer to itself.
  const [toId, setToId] = React.useState(accounts[1]?.id ?? accounts[0]?.id ?? null)
  const [amountFrom, setAmountFrom] = React.useState('')
  const [amountTo, setAmountTo] = React.useState('')
  const [date, setDate] = React.useState(today())
  const [description, setDescription] = React.useState('')
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')

  const currencyOf = (id: number | null) => accounts.find((account) => account.id === id)?.currencyCode
  const sameCurrency = currencyOf(fromId) !== undefined && currencyOf(fromId) === currencyOf(toId)

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    setErrors({})
    if (fromId === null || toId === null) {
      setErrors({ from: t('transfer.from_account_required', { ns: 'validators' }) })
      setFailure(t('form.fill_required_fields'))
      return
    }

    const input: TransferInput = {
      fromAccountId: fromId,
      toAccountId: toId,
      operationDate: date,
      amountFrom: toApiAmount(amountFrom),
      ...(sameCurrency ? {} : { amountTo: toApiAmount(amountTo) }),
      description: description.trim() === '' ? null : description,
    }
    try {
      const transfer = await create.mutateAsync(input)
      // As in the web app: the history of the first account, with the operation that left it framed.
      navigate(`/account/${transfer.fromAccountId}/operations?highlight=${transfer.outOperationId}`)
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else if (error instanceof ApiError && error.errorCode !== undefined && error.errorCode in FIELD_OF_ERROR_CODE) {
        const message = errorMessage(error, '')
        setErrors({ [FIELD_OF_ERROR_CODE[error.errorCode]]: message })
        setFailure(message)
      } else {
        setFailure(errorMessage(error, t('app.save_transfer_failed')))
      }
    }
  }

  const accountOptions = accounts.map((account) => (
    <option key={account.id} value={account.id}>{account.displayLabel}</option>
  ))

  return (
    <section className="max-w-3xl rounded-xl bg-white p-6 shadow">
      <h1 className="mb-4 text-xl font-semibold text-slate-900">{t('transfer.title')}</h1>
      {failure && <p role="alert" className="mb-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{failure}</p>}
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <FormRow label={t('transfer.from_account')} htmlFor="from" error={errors.from}>
            <select
              id="from"
              value={fromId ?? ''}
              onChange={(event) => setFromId(Number(event.target.value))}
              className={inputClass(errors.from)}
            >
              {accountOptions}
            </select>
          </FormRow>
          <FormRow label={t('transfer.to_account')} htmlFor="to" error={errors.to}>
            <select
              id="to"
              value={toId ?? ''}
              onChange={(event) => setToId(Number(event.target.value))}
              className={inputClass(errors.to)}
            >
              {accountOptions}
            </select>
          </FormRow>
          <FormRow label={t('transfer.debit')} htmlFor="amountFrom" error={errors.amountFrom}>
            <AmountInput
              id="amountFrom"
              value={amountFrom}
              onChange={setAmountFrom}
              allowMinus={false}
              className={inputClass(errors.amountFrom)}
            />
          </FormRow>
          <FormRow label={t('transfer.credit')} htmlFor="amountTo" error={sameCurrency ? undefined : errors.amountTo}>
            <AmountInput
              id="amountTo"
              value={sameCurrency ? '' : amountTo}
              onChange={setAmountTo}
              allowMinus={false}
              disabled={sameCurrency}
              placeholder={sameCurrency ? t('transfer.credit_same_currency') : undefined}
              className={inputClass(sameCurrency ? undefined : errors.amountTo)}
            />
          </FormRow>
        </div>
        <FormRow label={t('form.date')} htmlFor="date" error={errors.date}>
          <div className="sm:max-w-xs">
            <DateInput
              id="date"
              value={date}
              min={MIN_DATE}
              max={MAX_DATE}
              onChange={setDate}
              className={inputClass(errors.date)}
            />
          </div>
        </FormRow>
        <FormRow label={t('form.description')} htmlFor="description" error={errors.description}>
          <input
            id="description"
            type="text"
            maxLength={255}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            className={inputClass(errors.description)}
          />
        </FormRow>
        <FormButtons sending={create.isPending} />
      </form>
    </section>
  )
}
