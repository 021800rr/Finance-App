import type { UseQueryResult } from '@tanstack/react-query'
import * as React from 'react'
import { ApiError } from '../api'
import { NotFoundPage } from '../pages/NotFoundPage'
import { ErrorText, Loading } from './Feedback'

type Props<T> = {
  query: UseQueryResult<T>
  // the message when the API cannot be read
  failure: string
  // what to say while waiting, instead of "Wczytuję…"
  loading?: string
  // a 404 shows the "no such page" page: the address names a record that does not exist
  notFound?: boolean
  // for a form that copies the data into its fields once, when it opens: waits for the data read after the page
  // opened, never starts from a copy kept since an earlier visit, which may be older (an account hidden since, an
  // operation confirmed since)
  fresh?: boolean
  // what to show once the data is there
  children: (data: T) => React.ReactNode
}

// The three states of everything read from the API, in one place: waiting, failed (or not found), there.
//
//   <QueryResult query={useAccount(id)} failure={t('app.account_load_failed')} notFound>
//     {(account) => <AccountDetails account={account} />}
//   </QueryResult>
export function QueryResult<T>({ query, failure, loading, notFound = false, fresh = false, children }: Props<T>) {
  if (query.isPending || (fresh && query.isStale && !query.isFetchedAfterMount)) {
    return <Loading text={loading} />
  }
  if (query.isError) {
    return notFound && query.error instanceof ApiError && query.error.status === 404
      ? <NotFoundPage />
      : <ErrorText error={query.error} fallback={failure} />
  }

  return children(query.data)
}
