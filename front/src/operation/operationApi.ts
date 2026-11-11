import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { getJson, patchJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'
import type { Operation } from '../account/accountApi'

// The types of the API, generated from its OpenAPI description (npm run api-types): an account of the list (without
// its balances) and a contractor.
export type AccountChoice = components['schemas']['Account']
export type Contractor = components['schemas']['Contractor']

// What the form sends. The contractor goes as contractorId (one from the list) or contractorName (a new one: the API
// creates it).
export type OperationInput = {
  contractorId?: number
  contractorName?: string
  operationDate: string
  amount: string
  description: string | null
  confirmed: boolean
}

// Every account, ordered by bank, type, currency and number; a new operation can go only to one that is not hidden.
export function useAccounts() {
  return useQuery({ queryKey: queryKeys.accounts(), queryFn: () => getJson<AccountChoice[]>('/api/accounts') })
}

export function useOperation(id: string) {
  return useQuery({
    queryKey: queryKeys.operation(id),
    queryFn: () => getJson<Operation>(`/api/account_operations/${encodeURIComponent(id)}`),
  })
}

// Contractors whose name contains the text (case-insensitive); nothing is asked for an empty text. The list keeps the
// previous ones while the next load, instead of blinking empty.
export function useContractorSearch(text: string) {
  return useQuery({
    queryKey: queryKeys.contractors(text),
    queryFn: () => getJson<Contractor[]>(`/api/contractors?name=${encodeURIComponent(text)}`),
    enabled: text !== '',
    placeholderData: keepPreviousData,
  })
}

export function useCreateOperation() {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: OperationInput & { accountId: number }) =>
      postJson<Operation>('/api/account_operations', input),
    onSuccess: () => invalidate('operations'),
  })
}

export function useUpdateOperation(id: number) {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (input: OperationInput) => patchJson(`/api/account_operations/${id}`, input),
    onSuccess: () => invalidate('operations'),
  })
}
