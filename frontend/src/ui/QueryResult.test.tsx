import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { describe, expect, it } from 'vitest'
import { ApiError } from '../api'
import { QueryResult } from './QueryResult'

type Options = { read: () => Promise<string>; notFound?: boolean; fresh?: boolean; cached?: string }

// QueryResult around one query of a test, in the providers of the app.
function show({ read, notFound, fresh, cached }: Options) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  if (cached !== undefined) {
    client.setQueryData(['test'], cached)
  }

  function Page() {
    const query = useQuery({ queryKey: ['test'], queryFn: read })

    return (
      <QueryResult query={query} failure="Nie udało się wczytać." notFound={notFound} fresh={fresh}>
        {(data) => <p>Dane: {data}</p>}
      </QueryResult>
    )
  }

  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <Page />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('QueryResult', () => {
  it('says it is loading, then shows the data', async () => {
    show({ read: async () => 'konto' })

    expect(screen.getByText('Wczytuję…')).toBeInTheDocument()
    expect(await screen.findByText('Dane: konto')).toBeInTheDocument()
  })

  it('shows the "no such page" page for a 404 of a record named in the address', async () => {
    show({ read: () => Promise.reject(new ApiError(404, 'account.not_found', 'Not found')), notFound: true })

    expect(await screen.findByRole('heading', { name: 'Nie ma takiej strony' })).toBeInTheDocument()
  })

  it('says why it failed otherwise', async () => {
    show({ read: () => Promise.reject(new ApiError(500, undefined, 'Internal error')) })

    expect(await screen.findByRole('alert')).toHaveTextContent('Nie udało się wczytać.')
  })

  it('shows a copy kept from earlier at once', () => {
    show({ read: () => new Promise(() => {}), cached: 'stare' })

    expect(screen.getByText('Dane: stare')).toBeInTheDocument()
  })

  it('makes a form wait for data read after it opened, never starting from a copy kept from earlier', async () => {
    show({ read: async () => 'nowe', cached: 'stare', fresh: true })

    expect(screen.getByText('Wczytuję…')).toBeInTheDocument()
    expect(await screen.findByText('Dane: nowe')).toBeInTheDocument()
    expect(screen.queryByText('Dane: stare')).not.toBeInTheDocument()
  })
})
