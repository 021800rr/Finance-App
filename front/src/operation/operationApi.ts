import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { getJson, patchJson, postJson } from '../api'
import type { components } from '../generated/api'
import { queryKeys, useInvalidate } from '../queryKeys'
import type { Operation } from '../account/accountApi'

// The types of the API, generated from its OpenAPI description (npm run api-types): an account of the list (without
// its balances), a contractor…
export type AccountChoice = components['schemas']['Account']
export type Contractor = components['schemas']['Contractor']

// …what a new operation sends: its account, chosen once, on create, and the rest of the form. Any field may be missing
// or null, and the API says which one is wrong. The contractor goes as contractorId (one from the list) or
// contractorName (a new one: the API creates it)…
export type NewOperationInput = components['schemas']['AccountOperation.NewAccountOperationInput']

// …and what a change sends: a merge patch, where what is left out stays as it is; the account cannot change.
export type OperationChanges = components['schemas']['AccountOperation.AccountOperationInput.jsonMergePatch']

// What the form fills in for both: all but the account.
export type OperationInput = Omit<NewOperationInput, 'accountId'>

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
    mutationFn: (input: NewOperationInput) => postJson<Operation>('/api/account_operations', input),
    onSuccess: () => invalidate('operations'),
  })
}

export function useUpdateOperation(id: number) {
  const invalidate = useInvalidate()

  return useMutation({
    mutationFn: (changes: OperationChanges) => patchJson(`/api/account_operations/${id}`, changes),
    onSuccess: () => invalidate('operations'),
  })
}
