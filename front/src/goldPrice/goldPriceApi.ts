import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, postJson } from '../api'
import { queryKeys, useInvalidate } from '../queryKeys'

// One price of a gold asset, in PLN, on a day.
export type GoldPrice = {
  id: number
  goldAssetId: number
  // YYYY-MM-DD
  priceDate: string
  // a decimal string, e.g. '8650.00'
  pricePln: string
  currencyCode: string
}

// What the form sends; the API asks for whatever is missing.
export type GoldPriceInput = { goldAssetId: number | null; priceDate: string; pricePln: string }

// The latest prices, newest first, as many as the web list shows.
export function useRecentGoldPrices() {
  return useQuery({
    queryKey: queryKeys.recentGoldPrices(),
    queryFn: () => getJson<GoldPrice[]>('/api/gold_prices/recent'),
  })
}

// The prices of the chart: the last price of every asset and day, oldest first.
export function useChartGoldPrices() {
  return useQuery({
    queryKey: queryKeys.chartGoldPrices(),
    queryFn: () => getJson<GoldPrice[]>('/api/gold_prices/chart'),
  })
}

export function useCreateGoldPrice() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: GoldPriceInput) => postJson<GoldPrice>('/api/gold_prices', input),
    onSuccess: () => invalidate('gold'),
  })
}

export function useDeleteGoldPrice() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/gold_prices/${id}`),
    onSettled: () => invalidate('gold'),
  })
}
