import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { type Answer, contractorSearch, fakeApi, sentJson } from '../test/fakeApi'
import { today } from '../ui/form'
import { NewOperationPage } from './NewOperationPage'

const account = (id: number, hidden = false) => ({
  id, hidden, currencyCode: 'PLN', accountNumber: `account-${id}`, bankName: 'Bank', typeName: 'ROR',
  householdName: 'Home', displayLabel: `Account ${id}`, isMain: id === 1,
})

const ACCOUNTS = [account(1), account(2), account(3, true)]

// The API of the page: the accounts and the contractors; `answer` replies to the save.
function serve(accounts = ACCOUNTS, answer?: Answer) {
  return fakeApi({ '/api/accounts': accounts }, contractorSearch([{ id: 4, name: 'Shop' }]), answer)
}

const saved: Answer = (method, path) => method === 'POST' && path === '/api/account_operations'
  ? Response.json({ id: 9, accountId: 2 }, { status: 201 })
  : undefined

// Every other page says its address, so a test sees where the form led.
function Elsewhere() {
  const location = useLocation()

  return <p>Page {location.pathname}{location.search}</p>
}

function showPage(address = '/operation/new') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[address]}>
        <Routes>
          <Route path="operation/new" element={<NewOperationPage />} />
          <Route path="*" element={<Elsewhere />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const field = (label: string) => screen.getByLabelText(i18n.t(label))

// By its label: the list of the accounts is a combobox too.
const contractor = () => field('form.contractor')

async function pickContractor(text: string, option: string) {
  const user = userEvent.setup()
  await user.type(contractor(), text)
  await user.click(await screen.findByRole('option', { name: option }))
}

afterEach(() => { vi.unstubAllGlobals() })

describe('NewOperationPage', () => {
  it('offers the accounts that are not hidden, the first one chosen', async () => {
    serve()
    showPage()

    const accounts = await screen.findByLabelText(i18n.t('form.account'))
    expect(within(accounts).getAllByRole('option').map((option) => option.textContent))
      .toEqual(['Account 1', 'Account 2'])
    expect(accounts).toHaveDisplayValue('Account 1')
    expect(field('form.date')).toHaveValue(today())
  })

  it('chooses the account of the address', async () => {
    serve()
    showPage('/operation/new?account=2')

    expect(await screen.findByLabelText(i18n.t('form.account'))).toHaveDisplayValue('Account 2')
  })

  // As in the web app: not quietly another account instead.
  it.each([
    ['missing', '/operation/new?account=99', 'account.not_found'],
    ['hidden', '/operation/new?account=3', 'account.hidden_not_selectable'],
  ])('refuses an account of the address that is %s', async (_, address, code) => {
    serve()
    showPage(address)

    expect(await screen.findByRole('alert')).toHaveTextContent(i18n.t(code, { ns: 'validators' }))
    expect(screen.getByRole('link', { name: i18n.t('error.back_home') })).toHaveAttribute('href', '/')
    // and no form to send
    expect(screen.queryByRole('button', { name: i18n.t('form.save') })).not.toBeInTheDocument()
  })

  it('adds an operation, then shows it highlighted in the history of its account', async () => {
    const user = userEvent.setup()
    const fetch = serve(ACCOUNTS, saved)
    showPage()
    await user.selectOptions(await screen.findByLabelText(i18n.t('form.account')), 'Account 2')
    await pickContractor('sho', 'Shop')
    fireEvent.change(field('form.date'), { target: { value: '2026-11-03' } })
    fireEvent.change(field('form.amount'), { target: { value: '-12,50' } })
    await user.type(field('form.description'), 'Bread')
    await user.click(field('form.confirmed'))

    await user.click(screen.getByRole('button', { name: i18n.t('form.save') }))

    expect(await screen.findByText('Page /account/2/operations?highlight=9')).toBeInTheDocument()
    expect(sentJson(fetch, 'POST')).toEqual({
      accountId: 2, contractorId: 4, operationDate: '2026-11-03', amount: '-12.50', description: 'Bread',
      confirmed: true,
    })
  })

  it('sends a new contractor by its name, and no description as null', async () => {
    const user = userEvent.setup()
    const fetch = serve(ACCOUNTS, saved)
    showPage()
    await screen.findByLabelText(i18n.t('form.account'))
    await pickContractor('Bakery', i18n.t('contractor_autocomplete.add', { name: 'Bakery' }))
    fireEvent.change(field('form.amount'), { target: { value: '-3,20' } })

    await user.click(screen.getByRole('button', { name: i18n.t('form.save') }))

    await screen.findByText('Page /account/2/operations?highlight=9')
    expect(sentJson(fetch, 'POST')).toEqual({
      accountId: 1, contractorName: 'Bakery', operationDate: today(), amount: '-3.20', description: null,
      confirmed: false,
    })
  })

  // What the API cannot tell: a name typed but not picked would go as a new contractor.
  it('asks for an account and a contractor from the list, without sending anything', async () => {
    const user = userEvent.setup()
    const fetch = serve([account(3, true)])
    showPage()
    await screen.findByLabelText(i18n.t('form.account'))
    await user.type(contractor(), 'sho')

    await user.click(screen.getByRole('button', { name: i18n.t('form.save') }))

    expect(field('form.account'))
      .toHaveAccessibleDescription(i18n.t('operation.account_required', { ns: 'validators' }))
    expect(contractor()).toHaveAccessibleDescription(i18n.t('contractor.required', { ns: 'validators' }))
    expect(fetch).not.toHaveBeenCalledWith('/api/account_operations', expect.anything())
  })

  it.each([
    ['/operation/new', 'Page /'],
    ['/operation/new?account=2', 'Page /account/2/operations'],
  ])('goes back without saving from %s, opened first in the tab, to the page of its account', async (address, page) => {
    const user = userEvent.setup()
    serve()
    showPage(address)

    await user.click(await screen.findByRole('button', { name: i18n.t('form.cancel') }))

    expect(screen.getByText(page)).toBeInTheDocument()
  })
})
