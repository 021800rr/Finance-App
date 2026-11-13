import { describe, expect, it } from 'vitest'
import { toSeries } from './series'

type Price = { assetId: number; date: string; price: string }

const reading = (names: Record<number, string>) => ({
  line: (row: Price) => row.assetId,
  label: (assetId: number) => names[assetId] ?? `#${assetId}`,
  date: (row: Price) => row.date,
  value: (row: Price) => row.price,
})

describe('toSeries', () => {
  it('makes one line per asset, in the order the assets first appear', () => {
    const rows: Price[] = [
      { assetId: 2, date: '2026-07-01', price: '8050.00' },
      { assetId: 1, date: '2026-07-01', price: '4100.00' },
      { assetId: 2, date: '2026-08-02', price: '8350.00' },
    ]

    expect(toSeries(rows, reading({ 1: 'Coin', 2: 'Bar' }))).toEqual([
      { label: 'Bar', values: { '2026-07-01': '8050.00', '2026-08-02': '8350.00' } },
      { label: 'Coin', values: { '2026-07-01': '4100.00' } },
    ])
  })

  it('keeps two assets described alike as two lines', () => {
    const rows: Price[] = [
      { assetId: 1, date: '2026-07-01', price: '1.00' },
      { assetId: 2, date: '2026-07-01', price: '2.00' },
    ]

    expect(toSeries(rows, reading({ 1: '1 oz', 2: '1 oz' }))).toHaveLength(2)
  })
})
