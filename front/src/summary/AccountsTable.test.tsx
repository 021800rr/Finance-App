import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { describe, expect, it } from 'vitest'
import { AccountsTable } from './AccountsTable'
import type { SummaryAccount } from './summaryApi'

const main: SummaryAccount = {
  id: 1, bankName: 'Bank1', typeName: 'ROR', currencyCode: 'PLN', accountNumber: 'Bank1-ROR-PLN', hidden: false,
  householdName: 'Nasze', isOwnFunds: true, isRetirement: false, saldo: { amount: '100.00', currency: 'PLN' },
  missingRate: false, isMain: true,
}

function show(accounts: SummaryAccount[]) {
  render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter>
        <AccountsTable accounts={accounts} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('AccountsTable', () => {
  it('says there are no accounts, across all its columns', () => {
    show([])

    const cell = screen.getByRole('cell', { name: 'Brak kont' })
    expect(cell).toHaveAttribute('colspan', '7')
  })

  it('names the main account for a screen reader instead of reading out the star', () => {
    show([main, { ...main, id: 2, accountNumber: 'Bank1-WAK-PLN', isMain: false }])

    const [, mainRow, otherRow] = screen.getAllByRole('row')
    expect(within(mainRow!).getByText('★')).toHaveAttribute('aria-hidden', 'true')
    expect(within(mainRow!).getByText('Konto główne')).toHaveClass('sr-only')
    expect(within(otherRow!).queryByText('Konto główne')).toBeNull()
  })
})
