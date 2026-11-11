import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types): an exchange rate as the API
// answers it (what a bank sells a currency for, in PLN, on a day), the banks and the currencies…
export type Rate = components['schemas']['CurrencyExchangeRate']
export type Bank = components['schemas']['Bank']
type Currency = components['schemas']['Currency']

// …and what the form sends: any field may be missing or null, and the API says which one is wrong.
export type RateInput = components['schemas']['CurrencyExchangeRate.CurrencyExchangeRateInput']

// The latest rates, newest first, as many as the web list shows.
export function useRecentRates() {
  return useQuery({
    queryKey: queryKeys.recentRates(),
    queryFn: () => getJson<Rate[]>('/api/currency_exchange_rates/recent'),
  })
}

// The rates of the chart: the tracked currency (CHF), the last rate of every bank and day, oldest first.
export function useChartRates() {
  return useQuery({
    queryKey: queryKeys.chartRates(),
    queryFn: () => getJson<Rate[]>('/api/currency_exchange_rates/chart'),
  })
}

export function useBanks() {
  return useQuery({ queryKey: queryKeys.banks(), queryFn: () => getJson<Bank[]>('/api/banks') })
}

// Every currency code, PLN too, in alphabetical order.
export function useCurrencies() {
  return useQuery({
    queryKey: queryKeys.currencies(),
    queryFn: async () => (await getJson<Currency[]>('/api/currencies')).map((currency) => currency.code),
  })
}

export function useCreateRate() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: RateInput) => postJson<Rate>('/api/currency_exchange_rates', input),
    onSuccess: () => invalidate('rates'),
  })
}

export function useDeleteRate() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/currency_exchange_rates/${id}`),
    onSettled: () => invalidate('rates'),
  })
}
