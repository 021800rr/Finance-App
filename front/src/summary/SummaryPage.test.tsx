import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { type Answer, fakeApi, sentJson } from '../test/fakeApi'
import { SummaryPage } from './SummaryPage'
import type { Summary, SummaryAccount } from './summaryApi'

const pln = (amount: string) => ({ amount, currency: 'PLN' })

const account = (id: number, fields: Partial<SummaryAccount> = {}): SummaryAccount => ({
  id, bankName: `Bank${id}`, typeName: 'ROR', currencyCode: 'PLN', accountNumber: `Bank${id}-ROR`, hidden: false,
  householdName: 'Nasze', isOwnFunds: true, isRetirement: false, saldo: pln('100.00'), missingRate: false,
  isMain: id === 1, ...fields,
})

const summary = (accounts: SummaryAccount[], fields: Partial<Summary> = {}): Summary => ({
  accounts,
  byType: { ROR: { raw: { PLN: pln('1000.00') }, pln: pln('1000.00') } },
  byHousehold: { Nasze: pln('1500.00') },
  ownFundsHouseholdName: 'Nasze',
  gold: pln('8650.00'),
  retirementBalance: pln('300.00'),
  retirementBalanceWithGold: pln('8950.00'),
  accountsMissingRate: [],
  ...fields,
})

// The API of the page: the summary without the hidden accounts, and with them when the test gives that one too.
function serve(visible: Summary, withHidden: Summary = visible, answer?: Answer) {
  return fakeApi({ '/api/summary?showHidden=false': visible, '/api/summary?showHidden=true': withHidden }, answer)
}

function showPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <SummaryPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

// The rows of a table, each as the texts of its first cells.
async function rowsOf(index: number, cells: number) {
  const table = (await screen.findAllByRole('table'))[index]
  if (table === undefined) {
    throw new Error(`There is no table ${index}.`)
  }

  return within(table).getAllByRole('row').slice(1)
    .map((row) => within(row).getAllByRole('cell').slice(0, cells).map((cell) => cell.textContent))
}

const accountRows = () => rowsOf(0, 6)
const banks = async () => (await accountRows()).map(([bank]) => bank)

afterEach(() => { vi.unstubAllGlobals() })

describe('SummaryPage', () => {
  it('lists every account with its balance, and the rate of one in another currency', async () => {
    serve(summary([
      account(1, { saldo: pln('1000.00') }),
      account(2, { currencyCode: 'CHF', saldo: { amount: '200.00', currency: 'CHF' }, rate: pln('4.5590') }),
      account(3, { currencyCode: 'EUR', saldo: { amount: '50.00', currency: 'EUR' }, missingRate: true }),
    ]))
    showPage()

    expect(await accountRows()).toEqual([
      ['Bank1', 'ROR', 'Bank1-ROR', '', formatAmount('1000.00'), 'PLN'],
      ['Bank2', 'ROR', 'Bank2-ROR', formatAmount('4.5590'), formatAmount('200.00'), 'CHF'],
      ['Bank3', 'ROR', 'Bank3-ROR', i18n.t('home.missing_rate'), formatAmount('50.00'), 'EUR'],
    ])
    expect(screen.getAllByRole('link', { name: i18n.t('home.history') })[1])
      .toHaveAttribute('href', '/account/2/operations')
  })

  // A household with a translation is named by it (Matka: Mama), any other by its name.
  it('sums up by account type and by household, and says which total it has no accounts for', async () => {
    serve(summary([account(1)], {
      byHousehold: { Nasze: pln('1500.00'), Matka: pln('400.00'), Sąsiad: pln('50.00') },
    }))
    showPage()

    expect(await rowsOf(1, 2)).toEqual([
      [i18n.t('home.category_ror'), formatAmount('1000.00')],
      [i18n.t('home.category_wakacje'), i18n.t('home.category_not_found')],
      [i18n.t('home.category_kasa'), formatAmount('300.00')],
      [i18n.t('home.category_gold'), formatAmount('8650.00')],
      [i18n.t('home.category_backup'), formatAmount('8950.00')],
      ['Nasze', formatAmount('1500.00')],
      ['Mama', formatAmount('400.00')],
      ['Sąsiad', formatAmount('50.00')],
    ])
    // the household of our own funds stands out
    expect(screen.getByRole('cell', { name: 'Nasze' }).closest('tr')).toHaveClass('bg-slate-100')
    expect(screen.getByRole('cell', { name: 'Mama' }).closest('tr')).not.toHaveClass('bg-slate-100')
  })

  it('shows the hidden accounts on request, and hides them again', async () => {
    const user = userEvent.setup()
    const hidden = account(2, { hidden: true })
    serve(summary([account(1)]), summary([account(1), hidden]))
    showPage()
    expect(await banks()).toEqual(['Bank1'])

    await user.click(screen.getByRole('button', { name: i18n.t('home.show_hidden') }))

    await waitFor(async () => { expect(await banks()).toEqual(['Bank1', 'Bank2']) })
    // in paler grey, and with the eye that shows it again
    const row = screen.getByRole('cell', { name: 'Bank2' }).closest('tr')
    expect(row).toHaveClass('text-slate-400')
    expect(within(row!).getByRole('button', { name: i18n.t('home.show') })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: i18n.t('home.hide_hidden') }))

    await waitFor(async () => { expect(await banks()).toEqual(['Bank1']) })
  })

  // Every column by its own value, the numbers as numbers: as text, 1000.00 would come before 900.00. An account
  // without a rate counts as 0.
  it.each([
    ['account.bank', ['Alior', 'mBank', 'Pekao']],
    ['account.type', ['Pekao', 'mBank', 'Alior']],
    ['account.number', ['Alior', 'Pekao', 'mBank']],
    ['home.rate', ['mBank', 'Pekao', 'Alior']],
    ['account.balance', ['Pekao', 'mBank', 'Alior']],
    ['account.currency', ['Alior', 'Pekao', 'mBank']],
  ])('sorts by %s, either way', async (label, ascending) => {
    const user = userEvent.setup()
    serve(summary([
      account(1, { bankName: 'mBank', typeName: 'ROR', accountNumber: 'C-3', saldo: pln('900.00') }),
      account(2, {
        bankName: 'Alior', typeName: 'Wakacje', accountNumber: 'A-1', currencyCode: 'CHF', rate: pln('4.5590'),
        saldo: { amount: '1000.00', currency: 'CHF' },
      }),
      account(3, {
        bankName: 'Pekao', typeName: 'Lokata', accountNumber: 'B-2', currencyCode: 'EUR', rate: pln('4.2500'),
        saldo: { amount: '50.00', currency: 'EUR' },
      }),
    ]))
    showPage()
    await accountRows()
    // the totals have a "Typ" column too
    const [accounts] = screen.getAllByRole('table')
    const header = within(accounts!).getByRole('columnheader', { name: new RegExp(`^${i18n.t(label)}`) })

    await user.click(header)

    expect(header).toHaveAttribute('aria-sort', 'ascending')
    expect(await banks()).toEqual(ascending)

    await user.click(header)

    expect(header).toHaveAttribute('aria-sort', 'descending')
    expect(await banks()).toEqual(ascending.toReversed())
  })

  it.each([
    ['makes an account the main one', 'home.set_main', { isMain: true }],
    ['hides an account', 'home.hide', { hidden: true }],
  ])('%s and reads the summary again', async (_, button, changes) => {
    const user = userEvent.setup()
    const fetch = serve(summary([account(1), account(2)]), undefined, (method, path) =>
      method === 'PATCH' && path === '/api/accounts/2' ? new Response(null, { status: 204 }) : undefined)
    showPage()
    const row = (await screen.findByRole('cell', { name: 'Bank2' })).closest('tr')

    await user.click(within(row!).getByRole('button', { name: i18n.t(button) }))

    await waitFor(() => {
      expect(fetch.mock.calls.filter(([path]) => path === '/api/summary?showHidden=false').length).toBeGreaterThan(1)
    })
    expect(sentJson(fetch, 'PATCH')).toEqual(changes)
  })

  // Another request has just moved the star; then hiding fails too: only the last failure stays on the screen.
  it('says why a change failed, the last failure only', async () => {
    const user = userEvent.setup()
    let changes = 0
    serve(summary([account(1), account(2)]), undefined, (method) => {
      if (method !== 'PATCH') {
        return undefined
      }
      changes += 1

      return changes === 1
        ? Response.json({ errorCode: 'account.main_changed_concurrently' }, { status: 409 })
        : Response.json({}, { status: 500 })
    })
    showPage()
    const row = (await screen.findByRole('cell', { name: 'Bank2' })).closest('tr')

    await user.click(within(row!).getByRole('button', { name: i18n.t('home.set_main') }))

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t('account.main_changed_concurrently'))

    await user.click(within(row!).getByRole('button', { name: i18n.t('home.hide') }))

    await waitFor(() => { expect(screen.getByRole('alert')).toHaveTextContent(i18n.t('app.hidden_failed')) })
  })
})
