import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { type AccountChoice, useAccounts } from '../operation/operationApi'
import { Card } from '../ui/Card'
import { Note } from '../ui/Feedback'
import { LineChart } from '../ui/LineChart'
import { QueryResult } from '../ui/QueryResult'
import { toSeries } from '../ui/series'
import { RateForm } from './RateForm'
import { type Bank, useBanks, useChartRates, useCreateRate, useCurrencies, useRecentRates } from './rateApi'
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

// /rate, as in the web app: the chart of the rates, the form of a new rate and the latest rates. The chart, the
// form and the list all name the banks, so nothing shows until their names are there (no '#1' meanwhile).
export function RatesPage() {
  const { t } = useTranslation()
  const banks = useBanks()

  return (
    <QueryResult query={banks} failure={t('app.rate_form_load_failed')}>
      {(all) => <Rates banks={all} />}
    </QueryResult>
  )
}

function Rates({ banks }: { banks: Bank[] }) {
  const { t } = useTranslation()
  const bankNames = React.useMemo(() => new Map(banks.map((bank) => [bank.id, bank.name])), [banks])

  return (
    <div className="space-y-6">
      <Card title={t('rate.chart_title')}>
        <RatesChart bankNames={bankNames} />
      </Card>
      <Card title={t('rate.add_title')} heading="h2">
        <NewRate banks={banks} />
      </Card>
      <Card title={t('rate.list_title')} heading="h2">
        <RecentRates bankNames={bankNames} />
      </Card>
    </div>
  )
}

function RatesChart({ bankNames }: { bankNames: Map<number, string> }) {
  const { t } = useTranslation()
  const rates = useChartRates()
  // One line per bank. Memoized: the chart is drawn anew whenever its lines change.
  const series = React.useMemo(() => toSeries(rates.data ?? [], {
    line: (rate) => rate.bankId,
    label: (bankId) => bankNames.get(bankId) ?? `#${bankId}`,
    date: (rate) => rate.rateDate,
    value: (rate) => rate.rateToPln,
  }), [rates.data, bankNames])

  return (
    <QueryResult query={rates} failure={t('app.rates_load_failed')}>
      {() => series.length === 0
        ? <Note>{t('rate.none_found')}</Note>
        : <LineChart series={series} colors={COLORS} xTitle={t('form.date')} yTitle={t('rate.chart_y_axis')} />}
    </QueryResult>
  )
}

// As in the web app: only a bank with a visible account in a foreign currency takes rates. The accounts name their
// bank but have no bank id; the API checks the bank once more anyway.
function banksTakingRates(banks: Bank[], accounts: AccountChoice[]): Bank[] {
  const foreign = new Set(accounts
    .filter((account) => !account.hidden && account.currencyCode !== 'PLN')
    .map((account) => account.bankName))

  return banks.filter((bank) => foreign.has(bank.name))
}

function NewRate({ banks }: { banks: Bank[] }) {
  const { t } = useTranslation()
  const accounts = useAccounts()
  const currencies = useCurrencies()
  const create = useCreateRate()
  // A new key gives a new, empty form after every rate added.
  const [formKey, setFormKey] = React.useState(0)

  return (
    <QueryResult query={accounts} failure={t('app.rate_form_load_failed')}>
      {(allAccounts) => (
        <QueryResult query={currencies} failure={t('app.rate_form_load_failed')}>
          {(codes) => (
            <RateForm
              key={formKey}
              banks={banksTakingRates(banks, allAccounts)}
              currencies={codes.filter((code) => code !== 'PLN')}
              save={async (input) => {
                await create.mutateAsync(input)
                setFormKey((key) => key + 1)
              }}
            />
          )}
        </QueryResult>
      )}
    </QueryResult>
  )
}

function RecentRates({ bankNames }: { bankNames: Map<number, string> }) {
  const { t } = useTranslation()
  const rates = useRecentRates()

  return (
    <QueryResult query={rates} failure={t('app.rates_load_failed')}>
      {(recent) => <RatesTable rates={recent} bankNames={bankNames} />}
    </QueryResult>
  )
}
