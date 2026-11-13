import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { type GoldAsset, useGoldAssets } from '../goldAsset/goldAssetApi'
import { Card } from '../ui/Card'
import { Note } from '../ui/Feedback'
import { LineChart } from '../ui/LineChart'
import { QueryResult } from '../ui/QueryResult'
import { toSeries } from '../ui/series'
import { GoldPriceForm } from './GoldPriceForm'
import { useChartGoldPrices, useCreateGoldPrice, useRecentGoldPrices } from './goldPriceApi'
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

// /gold-prices, as in the web app: the chart of the prices, the form of a new price and the latest prices. The chart,
// the form and the list all name the assets, so nothing shows until their names are there (no '#2' meanwhile).
export function GoldPricesPage() {
  const { t } = useTranslation()
  const assets = useGoldAssets()

  return (
    <QueryResult query={assets} failure={t('app.gold_assets_load_failed')} fresh>
      {(all) => <GoldPrices assets={all} />}
    </QueryResult>
  )
}

function GoldPrices({ assets }: { assets: GoldAsset[] }) {
  const { t } = useTranslation()
  const assetNames = React.useMemo(() => new Map(assets.map((asset) => [asset.id, asset.description])), [assets])

  return (
    <div className="space-y-6">
      <Card title={t('gold_price.chart_title')}>
        <PricesChart assetNames={assetNames} />
      </Card>
      <Card title={t('gold_price.add_title')} heading="h2">
        <NewPrice assets={assets} />
      </Card>
      <Card title={t('gold_price.list_title')} heading="h2">
        <RecentPrices assetNames={assetNames} />
      </Card>
    </div>
  )
}

function PricesChart({ assetNames }: { assetNames: Map<number, string> }) {
  const { t } = useTranslation()
  const prices = useChartGoldPrices()
  // One line per asset. Memoized: the chart is drawn anew whenever its lines change.
  const series = React.useMemo(() => toSeries(prices.data ?? [], {
    line: (price) => price.goldAssetId,
    label: (assetId) => assetNames.get(assetId) ?? `#${assetId}`,
    date: (price) => price.priceDate,
    value: (price) => price.pricePln,
  }), [prices.data, assetNames])

  return (
    <QueryResult query={prices} failure={t('app.gold_prices_load_failed')}>
      {() => series.length === 0
        ? <Note>{t('gold_price.none_found')}</Note>
        : <LineChart series={series} colors={COLORS} xTitle={t('form.date')} yTitle={t('gold_price.price_pln')} />}
    </QueryResult>
  )
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

  return (
    <QueryResult query={prices} failure={t('app.gold_prices_load_failed')}>
      {(recent) => <GoldPricesTable prices={recent} assetNames={assetNames} />}
    </QueryResult>
  )
}
