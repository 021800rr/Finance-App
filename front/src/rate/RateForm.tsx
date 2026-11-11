import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { AmountInput } from '../ui/AmountInput'
import { DateInput } from '../ui/DateInput'
import { Alert } from '../ui/Feedback'
import { inputClass, MAX_DATE, MIN_DATE, toApiDecimal, today } from '../ui/form'
import { FormButtons } from '../ui/FormButtons'
import { FormRow } from '../ui/FormRow'
import { useApiForm } from '../ui/useApiForm'
import type { Bank, RateInput } from './rateApi'

type Field = 'bank' | 'currency' | 'date' | 'rate'

// The fields of the API's field errors (propertyPath) on the fields of the form.
const FIELD_OF_PATH: Record<string, Field> = {
  bankId: 'bank',
  currencyCode: 'currency',
  rateDate: 'date',
  rateToPln: 'rate',
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
  const form = useApiForm({ fieldOfPath: FIELD_OF_PATH, failure: t('app.save_rate_failed') })

  function handleSubmit(event: React.SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    void form.submit(() => save({ bankId, currencyCode, rateDate, rateToPln: toApiDecimal(rate) }))
  }

  const { errors } = form

  return (
    <form onSubmit={handleSubmit} noValidate className="max-w-xl space-y-4">
      {form.message && <Alert tone="error">{form.message}</Alert>}
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
      <FormButtons sending={form.sending} submitLabel={t('common.add')} />
    </form>
  )
}
