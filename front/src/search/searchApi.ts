import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { Operation } from '../account/accountApi'
import { getPage } from '../api'
import { queryKeys } from '../queryKeys'

// One page of the operations of all accounts whose description or contractor name contains the phrase (any letter
// case), newest first, as GET /api/account_operations?q= finds them. Nothing is asked for an empty phrase.
export function useOperationSearch(phrase: string, page: number) {
  return useQuery({
    queryKey: queryKeys.search(phrase, page),
    queryFn: () => getPage<Operation>(`/api/account_operations?q=${encodeURIComponent(phrase)}&page=${page}`),
    enabled: phrase !== '',
    placeholderData: keepPreviousData,
  })
}
