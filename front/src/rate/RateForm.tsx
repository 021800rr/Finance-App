import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '../api'
import { errorMessage } from '../messages'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { fieldErrors, inputClass, MAX_DATE, MIN_DATE, today } from '../ui/form'
import { FormRow } from '../ui/FormRow'
import type { Bank, RateInput } from './rateApi'

type Field = 'bank' | 'currency' | 'date' | 'rate'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  bankId: 'bank',
  currencyCode: 'currency',
  rateDate: 'date',
  rateToPln: 'rate',
}

// A rate as the API takes it. Rates are below 100, so a comma or a dot is always the decimal one ('4,559' is 4.559;
// for an amount it would be 4559).
function toApiRate(rate: string): string {
  return rate.replace(/,/g, '.')
}

type Props = {
  // the banks that take rates: those with a visible account in a foreign currency
  banks: Bank[]
  // the currencies to choose from, PLN left out
  currencies: string[]
  // sends the form; when it fails, the form shows why
  save: (input: RateInput) => Promise<void>
}

// The form of a new rate, as in the web app: bank, currency, date (today at first), rate to PLN.
export function RateForm({ banks, currencies, save }: Props) {
  const { t } = useTranslation()
  const [bankId, setBankId] = React.useState(banks[0]?.id ?? null)
  const [currencyCode, setCurrencyCode] = React.useState(currencies[0] ?? '')
  const [rateDate, setRateDate] = React.useState(today)
  const [rate, setRate] = React.useState('')
  const [errors, setErrors] = React.useState<Partial<Record<Field, string>>>({})
  const [failure, setFailure] = React.useState('')
  const [sending, setSending] = React.useState(false)

  async function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setFailure('')
    setErrors({})
    setSending(true)
    try {
      await save({ bankId, currencyCode, rateDate, rateToPln: toApiRate(rate) })
    } catch (error) {
      if (error instanceof ApiError && error.violations.length > 0) {
        setErrors(fieldErrors(error.violations, FIELD_OF_PATH))
        setFailure(t('form.fill_required_fields'))
      } else {
        setFailure(errorMessage(error, t('app.save_rate_failed')))
      }
    } finally {
      setSending(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {failure && <p role="alert" className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{failure}</p>}
      <FormRow label={t('account.bank')} htmlFor="bank" error={errors.bank}>
        <select
          id="bank"
          value={bankId ?? ''}
          onChange={(event) => setBankId(Number(event.target.value))}
          className={inputClass(errors.bank)}
        >
          {banks.map((bank) => <option key={bank.id} value={bank.id}>{bank.name}</option>)}
        </select>
      </FormRow>
      <FormRow label={t('account.currency')} htmlFor="currency" error={errors.currency}>
        <select
          id="currency"
          value={currencyCode}
          onChange={(event) => setCurrencyCode(event.target.value)}
          className={inputClass(errors.currency)}
        >
          {currencies.map((code) => <option key={code} value={code}>{code}</option>)}
        </select>
      </FormRow>
      <FormRow label={t('form.date')} htmlFor="date" error={errors.date}>
        <DateInput
          id="date"
          value={rateDate}
          min={MIN_DATE}
          max={MAX_DATE}
          onChange={setRateDate}
          className={inputClass(errors.date)}
        />
      </FormRow>
      <FormRow label={t('rate.value')} htmlFor="rate" error={errors.rate}>
        <AmountInput
          id="rate"
          value={rate}
          onChange={setRate}
          allowMinus={false}
          placeholder={t('app.rate_placeholder')}
          className={inputClass(errors.rate)}
        />
      </FormRow>
      <div className="pt-2">
        <button
          type="submit"
          disabled={sending}
          className="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {sending ? t('app.saving') : t('common.add')}
        </button>
      </div>
    </form>
  )
}
