import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { errorMessage } from '../messages'
import { useAccounts } from '../operation/operationApi'
import { LineChart, type Series } from '../ui/LineChart'
import { RateForm } from './RateForm'
import { type Bank, type Rate, useBanks, useChartRates, useCreateRate, useCurrencies, useRecentRates } from './rateApi'
import { RatesTable } from './RatesTable'

// The colours of the banks' lines, as in the web app (assets/js/rates-chart.js).
const COLORS = [
  'rgb(75, 192, 192)',
  'rgb(255, 99, 132)',
  'rgb(54, 162, 235)',
  'rgb(255, 205, 86)',
  'rgb(153, 102, 255)',
  'rgb(201, 203, 207)',
  'rgb(255, 159, 64)',
]

const CARD = 'rounded-xl bg-white dark:bg-slate-900 p-6 shadow'

// /rate, as in the web app: the chart of the rates, the form of a new rate and the latest rates.
export function RatesPage() {
  const { t } = useTranslation()
  const banks = useBanks()
  const bankNames = React.useMemo(() => new Map(banks.data?.map((bank) => [bank.id, bank.name])), [banks.data])

  // The chart, the form and the list all name the banks: nothing until their names are there (no '#1' meanwhile).
  if (banks.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (banks.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(banks.error, t('app.rate_form_load_failed'))}
      </p>
    )
  }

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h1 className="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{t('rate.chart_title')}</h1>
        <RatesChart bankNames={bankNames} />
      </section>
      <section className={CARD}>
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{t('rate.add_title')}</h2>
        <NewRate banks={banks.data} />
      </section>
      <section className={CARD}>
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{t('rate.list_title')}</h2>
        <RecentRates bankNames={bankNames} />
      </section>
    </div>
  )
}

function RatesChart({ bankNames }: { bankNames: Map<number, string> }) {
  const { t } = useTranslation()
  const rates = useChartRates()
  const series = React.useMemo(() => bankSeries(rates.data ?? [], bankNames), [rates.data, bankNames])

  if (rates.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (rates.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(rates.error, t('app.rates_load_failed'))}
      </p>
    )
  }
  if (series.length === 0) {
    return <p className="text-slate-500 dark:text-slate-400">{t('rate.none_found')}</p>
  }

  return <LineChart series={series} colors={COLORS} xTitle={t('form.date')} yTitle={t('rate.chart_y_axis')} />
}

// One line per bank, in the order the banks first appear.
function bankSeries(rates: Rate[], bankNames: Map<number, string>): Series[] {
  const byBank = new Map<number, Series>()
  for (const rate of rates) {
    let line = byBank.get(rate.bankId)
    if (line === undefined) {
      line = { label: bankNames.get(rate.bankId) ?? `#${rate.bankId}`, values: {} }
      byBank.set(rate.bankId, line)
    }
    line.values[rate.rateDate] = rate.rateToPln
  }

  return [...byBank.values()]
}

function NewRate({ banks }: { banks: Bank[] }) {
  const { t } = useTranslation()
  const accounts = useAccounts()
  const currencies = useCurrencies()
  const create = useCreateRate()
  // A new key gives a new, empty form after every rate added.
  const [formKey, setFormKey] = React.useState(0)

  const error = accounts.error ?? currencies.error
  if (error) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(error, t('app.rate_form_load_failed'))}
      </p>
    )
  }
  if (accounts.data === undefined || currencies.data === undefined) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }

  // As in the web app: only a bank with a visible account in a foreign currency takes rates. The accounts name their
  // bank but have no bank id; the API checks the bank once more anyway.
  const foreignBanks = new Set(accounts.data
    .filter((account) => !account.hidden && account.currencyCode !== 'PLN')
    .map((account) => account.bankName))

  return (
    <RateForm
      key={formKey}
      banks={banks.filter((bank) => foreignBanks.has(bank.name))}
      currencies={currencies.data.filter((code) => code !== 'PLN')}
      save={async (input) => {
        await create.mutateAsync(input)
        setFormKey((key) => key + 1)
      }}
    />
  )
}

function RecentRates({ bankNames }: { bankNames: Map<number, string> }) {
  const { t } = useTranslation()
  const rates = useRecentRates()

  if (rates.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (rates.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(rates.error, t('app.rates_load_failed'))}
      </p>
    )
  }

  return <RatesTable rates={rates.data} bankNames={bankNames} />
}
