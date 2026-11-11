import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { describe, expect, it } from 'vitest'
import { type Change, queryKeys, useInvalidate } from './queryKeys'

const queries = {
  summary: queryKeys.summary(false),
  accounts: queryKeys.accounts(),
  account: queryKeys.account('1'),
  history: queryKeys.accountOperations('1', 2),
  operation: queryKeys.operation('3'),
  search: queryKeys.search('rent', 1),
  contractors: queryKeys.contractors('rent'),
  fixedExpenses: queryKeys.fixedExpenses(),
  fixedExpense: queryKeys.fixedExpense('1'),
  rates: queryKeys.chartRates(),
  recentRates: queryKeys.recentRates(),
  goldAssets: queryKeys.goldAssets(),
  goldAsset: queryKeys.goldAsset('1'),
  goldPrices: queryKeys.chartGoldPrices(),
  recentGoldPrices: queryKeys.recentGoldPrices(),
  banks: queryKeys.banks(),
}

const affected: Record<Change, string[]> = {
  operations: ['summary', 'account', 'history', 'operation', 'search', 'contractors'],
  accounts: ['summary', 'accounts', 'account', 'history'],
  fixedExpenses: ['fixedExpenses', 'fixedExpense', 'contractors'],
  rates: ['rates', 'recentRates', 'summary'],
  gold: ['goldAssets', 'goldAsset', 'goldPrices', 'recentGoldPrices', 'summary'],
}

describe('cache invalidation after mutations', () => {
  it.each(Object.entries(affected))('invalidates the related queries after %s', async (change, expected) => {
    const client = new QueryClient()
    for (const key of Object.values(queries)) {
      client.setQueryData(key, 'cached')
    }
    function wrapper({ children }: { children: ReactNode }) {
      return <QueryClientProvider client={client}>{children}</QueryClientProvider>
    }
    const { result, unmount } = renderHook(() => useInvalidate(), { wrapper })

    await act(() => result.current(change as Change))

    for (const [name, key] of Object.entries(queries)) {
      expect(client.getQueryState(key)?.isInvalidated, name).toBe(expected.includes(name))
    }
    unmount()
    client.clear()
  })
})
