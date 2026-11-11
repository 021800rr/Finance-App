import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { type GoldAsset, useGoldAssets } from '../goldAsset/goldAssetApi'
import { errorMessage } from '../messages'
import { LineChart, type Series } from '../ui/LineChart'
import { GoldPriceForm } from './GoldPriceForm'
import { type GoldPrice, useChartGoldPrices, useCreateGoldPrice, useRecentGoldPrices } from './goldPriceApi'
import { GoldPricesTable } from './GoldPricesTable'

// The colours of the assets' lines, as in the web app (assets/js/gold-prices-chart.js).
const COLORS = [
  'rgb(255, 215, 0)', // gold
  'rgb(192, 192, 192)', // silver
  'rgb(205, 127, 50)', // bronze
  'rgb(75, 192, 192)',
  'rgb(255, 99, 132)',
  'rgb(54, 162, 235)',
  'rgb(153, 102, 255)',
]

const CARD = 'rounded-xl bg-white dark:bg-slate-900 p-6 shadow'

// /gold-prices, as in the web app: the chart of the prices, the form of a new price and the latest prices.
export function GoldPricesPage() {
  const { t } = useTranslation()
  const assets = useGoldAssets()
  const assetNames = React.useMemo(
    () => new Map(assets.data?.map((asset) => [asset.id, asset.description])),
    [assets.data],
  )

  // The chart, the form and the list all name the assets: nothing until their names are there (no '#2' meanwhile).
  if (assets.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (assets.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(assets.error, t('app.gold_assets_load_failed'))}
      </p>
    )
  }

  return (
    <div className="space-y-6">
      <section className={CARD}>
        <h1 className="mb-4 text-xl font-semibold text-slate-900 dark:text-slate-100">{t('gold_price.chart_title')}</h1>
        <PricesChart assetNames={assetNames} />
      </section>
      <section className={CARD}>
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{t('gold_price.add_title')}</h2>
        <NewPrice assets={assets.data} />
      </section>
      <section className={CARD}>
        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">{t('gold_price.list_title')}</h2>
        <RecentPrices assetNames={assetNames} />
      </section>
    </div>
  )
}

function PricesChart({ assetNames }: { assetNames: Map<number, string> }) {
  const { t } = useTranslation()
  const prices = useChartGoldPrices()
  const series = React.useMemo(() => assetSeries(prices.data ?? [], assetNames), [prices.data, assetNames])

  if (prices.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (prices.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(prices.error, t('app.gold_prices_load_failed'))}
      </p>
    )
  }
  if (series.length === 0) {
    return <p className="text-slate-500 dark:text-slate-400">{t('gold_price.none_found')}</p>
  }

  return <LineChart series={series} colors={COLORS} xTitle={t('form.date')} yTitle={t('gold_price.price_pln')} />
}

// One line per asset, in the order the assets first appear; two assets with the same description get a line each.
function assetSeries(prices: GoldPrice[], assetNames: Map<number, string>): Series[] {
  const byAsset = new Map<number, Series>()
  for (const price of prices) {
    let line = byAsset.get(price.goldAssetId)
    if (line === undefined) {
      line = { label: assetNames.get(price.goldAssetId) ?? `#${price.goldAssetId}`, values: {} }
      byAsset.set(price.goldAssetId, line)
    }
    line.values[price.priceDate] = price.pricePln
  }

  return [...byAsset.values()]
}

function NewPrice({ assets }: { assets: GoldAsset[] }) {
  const create = useCreateGoldPrice()
  // A new key gives a new, empty form after every price added.
  const [formKey, setFormKey] = React.useState(0)

  return (
    <GoldPriceForm
      key={formKey}
      assets={assets}
      save={async (input) => {
        await create.mutateAsync(input)
        setFormKey((key) => key + 1)
      }}
    />
  )
}

function RecentPrices({ assetNames }: { assetNames: Map<number, string> }) {
  const { t } = useTranslation()
  const prices = useRecentGoldPrices()

  if (prices.isPending) {
    return <p className="text-slate-500 dark:text-slate-400">{t('app.loading')}</p>
  }
  if (prices.isError) {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        {errorMessage(prices.error, t('app.gold_prices_load_failed'))}
      </p>
    )
  }

  return <GoldPricesTable prices={prices.data} assetNames={assetNames} />
}
