import * as React from 'react'
import { useNavigate } from 'react-router'
import { ApiError } from '../api'
import { errorMessage, violationMessage } from '../messages'
import { type ContractorChoice, ContractorField } from './ContractorField'
import type { AccountChoice, OperationInput } from './operationApi'

const INPUT = 'block w-full rounded-md border px-3 py-2 text-slate-900 focus:outline-none focus:ring-1 '
const INPUT_OK = `${INPUT}border-slate-300 focus:border-indigo-500 focus:ring-indigo-500`
const INPUT_ERROR = `${INPUT}border-red-500 focus:border-red-500 focus:ring-red-500`

// The business date range of the backend (BusinessDateRange).
const MIN_DATE = '2025-01-01'
const MAX_DATE = '2070-12-31'

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

// 1234,56 → 1234.56: the API takes a plain decimal number with a dot.
function apiAmount(amount: string): string {
  return amount.replace(',', '.')
}

// The characters an amount is made of: digits, a comma or a dot, and a minus only at the start.
const AMOUNT_CHARACTER = /^[\d,.-]$/

// What is left of a typed or pasted text when only amount characters stay, e.g. '-1 234,56 zł' → '-1234,56'.
function amountCharacters(text: string): string {
  const kept = text.replace(/[^\d,.-]/g, '')

  return (kept.startsWith('-') ? '-' : '') + kept.replace(/-/g, '')
}

// The form of a new operation and of an edit, as in the web app: account, contractor, date, amount, description,
// confirmed. The API checks the values; its field errors appear under their fields, in Polish.
export function OperationForm({ title, initial, accounts, accountFixed, save }: Props) {
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
      ownErrors.account = 'Wybierz konto.'
    }
    if (values.contractor === null) {
      ownErrors.contractor = 'Wybierz kontrahenta z listy.'
    }
    setErrors(ownErrors)
    if (values.accountId === null || values.contractor === null) {
      setFailure('Uzupełnij wymagane pola. Zaznaczone na czerwono.')
      return
    }

    const input: OperationInput = {
      ...(values.contractor.id === null
        ? { contractorName: values.contractor.name }
        : { contractorId: values.contractor.id }),
      operationDate: values.operationDate,
      amount: apiAmount(values.amount),
      description: values.description.trim() === '' ? null : values.description,
      confirmed: values.confirmed,
    }
    setSending(true)
    try {
      navigate(await save(input, values.accountId))
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        const apiErrors: Partial<Record<Field, string>> = {}
        for (const violation of error.violations) {
          const field = FIELD_OF_PATH[violation.propertyPath]
          if (field !== undefined) {
            apiErrors[field] ??= violationMessage(violation)
          }
        }
        setErrors(apiErrors)
        setFailure('Uzupełnij wymagane pola. Zaznaczone na czerwono.')
      } else {
        setFailure(errorMessage(error, 'Nie udało się zapisać operacji.'))
      }
    } finally {
      setSending(false)
    }
  }

  return (
    <section className="max-w-xl rounded-xl bg-white p-6 shadow">
      <h1 className="mb-4 text-xl font-semibold text-slate-900">{title}</h1>
      {failure && <p role="alert" className="mb-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{failure}</p>}
      <form onSubmit={handleSubmit} noValidate className="space-y-4">
        <Row label="Konto" htmlFor="account" error={errors.account}>
          <select
            id="account"
            value={values.accountId ?? ''}
            onChange={(event) => set('accountId', Number(event.target.value))}
            disabled={accountFixed}
            className={`${errors.account ? INPUT_ERROR : INPUT_OK} disabled:bg-slate-100`}
          >
            {accounts.map((account) => (
              <option key={account.id} value={account.id}>{account.displayLabel}</option>
            ))}
          </select>
        </Row>
        <Row label="Kontrahent" htmlFor="contractor">
          <ContractorField
            value={values.contractor}
            onChange={(contractor) => set('contractor', contractor)}
            error={errors.contractor}
            inputClassName={errors.contractor ? INPUT_ERROR : INPUT_OK}
          />
        </Row>
        <Row label="Data" htmlFor="date" error={errors.date}>
          <input
            id="date"
            type="date"
            value={values.operationDate}
            min={MIN_DATE}
            max={MAX_DATE}
            onChange={(event) => set('operationDate', event.target.value)}
            className={errors.date ? INPUT_ERROR : INPUT_OK}
          />
        </Row>
        <Row label="Kwota" htmlFor="amount" error={errors.amount}>
          <input
            id="amount"
            type="text"
            inputMode="decimal"
            value={values.amount}
            // A typed key that is not an amount character does nothing, and the caret stays where it was. Longer
            // text (a paste) goes on to onChange, which keeps only its amount characters.
            onBeforeInput={(event) => {
              if (event.data.length === 1 && !AMOUNT_CHARACTER.test(event.data)) {
                event.preventDefault()
              }
            }}
            onChange={(event) => set('amount', amountCharacters(event.target.value))}
            placeholder="-309,00 wydatek · 309,00 wpływ"
            className={`${errors.amount ? INPUT_ERROR : INPUT_OK} tabular-nums`}
          />
        </Row>
        <Row label="Opis" htmlFor="description" error={errors.description}>
          <input
            id="description"
            type="text"
            maxLength={255}
            value={values.description}
            onChange={(event) => set('description', event.target.value)}
            className={errors.description ? INPUT_ERROR : INPUT_OK}
          />
        </Row>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={values.confirmed}
            onChange={(event) => set('confirmed', event.target.checked)}
            className="size-4"
          />
          Potwierdzone
        </label>
        <div className="flex gap-2 pt-2">
          <button
            type="submit"
            disabled={sending}
            className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700
              disabled:opacity-50"
          >
            {sending ? 'Zapisuję…' : 'Zapisz'}
          </button>
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="rounded-md border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-50"
          >
            Anuluj
          </button>
        </div>
      </form>
    </section>
  )
}

function Row({ label, htmlFor, error, children }: {
  label: string
  htmlFor: string
  error?: string
  children: React.ReactNode
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
      {children}
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  )
}
