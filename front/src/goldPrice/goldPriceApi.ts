import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): a price of a gold asset as the
// API answers it (in PLN, on a day)…
export type GoldPrice = components['schemas']['GoldPrice']

// …and what the form sends: any field may be missing or null, and the API says which one is wrong.
export type GoldPriceInput = components['schemas']['GoldPrice.GoldPriceInput']

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
