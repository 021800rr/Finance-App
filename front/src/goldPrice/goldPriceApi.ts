import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { deleteResource, getJson, postJson } from '../api'

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
    queryKey: ['goldPrices', 'recent'],
    queryFn: () => getJson<GoldPrice[]>('/api/gold_prices/recent'),
  })
}

// The prices of the chart: the last price of every asset and day, oldest first.
export function useChartGoldPrices() {
  return useQuery({
    queryKey: ['goldPrices', 'chart'],
    queryFn: () => getJson<GoldPrice[]>('/api/gold_prices/chart'),
  })
}

// After a change the list and the chart are read again.
export function useCreateGoldPrice() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: GoldPriceInput) => postJson<GoldPrice>('/api/gold_prices', input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['goldPrices'] }),
  })
}

export function useDeleteGoldPrice() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/gold_prices/${id}`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['goldPrices'] }),
  })
}
