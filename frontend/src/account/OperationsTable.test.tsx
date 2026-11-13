import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ComponentProps } from 'react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import type { Operation } from './accountApi'
import { OperationsTable } from './OperationsTable'

const operation = (id: number, fields: Partial<Operation> = {}): Operation => ({
  id, accountId: 1, contractorId: 4, contractorName: 'Shop', operationDate: '2026-11-10', amount: '-10.00',
  currencyCode: 'PLN', confirmed: false, ...fields,
})

function show(operations: Operation[], props: Partial<ComponentProps<typeof OperationsTable>> = {}) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <OperationsTable operations={operations} {...props} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const confirmation = () => screen.getByRole('button', { name: i18n.t('operation.change_confirmation_status') })

async function deleteConfirmed() {
  const user = userEvent.setup()
  await user.click(screen.getByRole('button', { name: i18n.t('operation.delete') }))
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: i18n.t('app.confirm') }))
}

const answering = (status: number, body: unknown = {}): Answer => (method) => method === 'GET'
  ? undefined
  : status === 204 ? new Response(null, { status }) : Response.json(body, { status })

afterEach(() => { vi.unstubAllGlobals() })

describe('OperationsTable', () => {
  it.each([
    [false, 'app.unconfirmed', { confirmed: true }],
    [true, 'app.confirmed', { confirmed: false }],
  ])('switches the confirmation of an operation confirmed: %s', async (confirmed, title, changes) => {
    const fetch = fakeApi({}, answering(204))
    show([operation(7, { confirmed })])
    expect(confirmation()).toHaveAttribute('aria-pressed', String(confirmed))
    expect(confirmation()).toHaveAttribute('title', i18n.t(title))

    await userEvent.click(confirmation())

    await waitFor(() => { expect(sentJson(fetch, 'PATCH', '/api/account_operations/7')).toEqual(changes) })
  })

  it('deletes an operation once confirmed', async () => {
    const fetch = fakeApi({}, answering(204))
    show([operation(7)])

    await deleteConfirmed()

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith('/api/account_operations/7', expect.objectContaining({ method: 'DELETE' }))
    })
  })

  // Deleted meanwhile, e.g. in another tab: the next action says so, and the failure before it goes.
  it('says why a change failed, the last failure only', async () => {
    let changes = 0
    fakeApi({}, (method) => {
      if (method === 'GET') {
        return undefined
      }
      changes += 1

      return changes === 1
        ? Response.json({}, { status: 500 })
        : Response.json({ errorCode: 'account_operation.not_found' }, { status: 404 })
    })
    show([operation(7)])

    await userEvent.click(confirmation())

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('app.confirm_failed'))

    await deleteConfirmed()

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(i18n.t('account_operation.not_found', { ns: 'validators' }))
    })
  })

  it('shows an income in green, an expense in the normal colour', () => {
    show([operation(1, { amount: '250.00' }), operation(2, { amount: '-10.00' })])

    expect(screen.getByRole('cell', { name: formatAmount('250.00') })).toHaveClass('text-green-700')
    expect(screen.getByRole('cell', { name: formatAmount('-10.00') })).not.toHaveClass('text-green-700')
  })

  // As in the web app: the results of a search name the account and have no 🔗.
  it('names the account of every operation in the search results, without the way to the other side', () => {
    show([operation(1, { transactionUuid: 'transfer' }), operation(2, { accountId: 9 })], {
      accountNumbers: new Map([[1, 'Bank1-ROR-PLN']]),
      otherSideLinks: false,
    })

    expect(screen.getByRole('columnheader', { name: i18n.t('search.account_column') })).toBeInTheDocument()
    expect(screen.getByText('Bank1-ROR-PLN')).toBeInTheDocument()
    // an account the list does not have: its id
    expect(screen.getByRole('cell', { name: '9' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('operation.show_other_side') })).not.toBeInTheDocument()
  })

  it('says it has no operations, in its own words, across all its columns', () => {
    show([], { accountNumbers: new Map(), emptyText: 'No results' })

    expect(screen.getByRole('cell', { name: 'No results' })).toHaveAttribute('colspan', '7')
  })
})
