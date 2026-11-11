import { useMutation, useQuery } from '@tanstack/react-query'
import { deleteResource, getJson, patchJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'

// The types of the API, generated from its OpenAPI description (npm run api-types), so they cannot drift from the
// backend: what it answers (every field there, the description only when there is one)…
export type FixedExpense = components['schemas']['FixedExpense']

// …what the form sends: any field may be missing or null, and the API says which one is wrong. The contractor goes as
// in an operation: contractorId (one from the list) or contractorName (a new one: the API creates it)…
export type FixedExpenseInput = components['schemas']['FixedExpense.FixedExpenseInput']

// …and what "Zaplanuj wydatki" did: the operations of the month (YYYY-MM) went to the main account.
export type Plan = components['schemas']['FixedExpensePlan']

// Every fixed expense, by day, as in the web app; the list has no pages.
export function useFixedExpenses() {
  return useQuery({
    queryKey: queryKeys.fixedExpenses(),
    queryFn: () => getJson<FixedExpense[]>('/api/fixed_expenses'),
  })
}

export function useFixedExpense(id: string) {
  return useQuery({
    queryKey: queryKeys.fixedExpense(id),
    queryFn: () => getJson<FixedExpense>(`/api/fixed_expenses/${encodeURIComponent(id)}`),
  })
}

export function useCreateFixedExpense() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: FixedExpenseInput) => postJson<FixedExpense>('/api/fixed_expenses', input),
    onSuccess: () => invalidate('fixedExpenses'),
  })
}

export function useUpdateFixedExpense(id: number) {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: FixedExpenseInput) => patchJson(`/api/fixed_expenses/${id}`, input),
    onSuccess: () => invalidate('fixedExpenses'),
  })
}

export function useDeleteFixedExpense() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (id: number) => deleteResource(`/api/fixed_expenses/${id}`),
    onSettled: () => invalidate('fixedExpenses'),
  })
}

// "Zaplanuj wydatki": one operation on the main account for every fixed expense, dated in the next month. The API
// refuses (409, nothing created) when there is no main account it can use, or when such an operation already exists.
export function usePlanFixedExpenses() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: () => postJson<Plan>('/api/fixed_expenses/plan', {}),
    onSuccess: () => invalidate('operations'),
  })
}
