import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as React from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { type ContractorChoice, ContractorField } from './ContractorField'

const CONTRACTORS = [{ id: 1, name: 'Probe' }, { id: 2, name: 'Probe2' }, { id: 3, name: 'Biedronka' }]

// The API of the test: GET /api/contractors?name=… finds the contractors whose name contains the text.
beforeEach(() => {
  vi.stubGlobal('fetch', vi.fn(async (path: string) => {
    const text = new URL(path, 'http://localhost').searchParams.get('name') ?? ''
    const found = CONTRACTORS.filter((contractor) => contractor.name.toLowerCase().includes(text.toLowerCase()))

    return Response.json(found)
  }))
})

// The field in a form, as in the forms of the app; the test reads what was chosen and whether the form was sent.
function showField() {
  const chosen: (ContractorChoice | null)[] = []
  const sent = vi.fn()

  function Form() {
    const [value, setValue] = React.useState<ContractorChoice | null>(null)

    return (
      <form onSubmit={(event) => { event.preventDefault(); sent() }}>
        <ContractorField
          id="contractor"
          value={value}
          onChange={(choice) => {
            setValue(choice)
            chosen.push(choice)
          }}
          inputClassName=""
        />
      </form>
    )
  }

  render(
    <QueryClientProvider client={new QueryClient()}>
      <Form />
    </QueryClientProvider>,
  )

  return { field: screen.getByRole('combobox'), lastChoice: () => chosen.at(-1), sent }
}

describe('ContractorField', () => {
  it('picks a contractor with the arrows and Enter, the focus staying in the field', async () => {
    const user = userEvent.setup()
    const { field, lastChoice, sent } = showField()

    await user.type(field, 'Prob')
    await screen.findByRole('option', { name: 'Probe' })
    await user.keyboard('{ArrowDown}{ArrowDown}')

    expect(screen.getByRole('option', { name: 'Probe2' })).toHaveAttribute('aria-selected', 'true')
    expect(field).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: 'Probe2' }).id)

    await user.keyboard('{Enter}')

    expect(lastChoice()).toEqual({ id: 2, name: 'Probe2' })
    expect(field).toHaveValue('Probe2')
    expect(field).toHaveFocus()
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    // Enter picked the contractor, it did not send the form.
    expect(sent).not.toHaveBeenCalled()
  })

  it('closes the list with Escape', async () => {
    const user = userEvent.setup()
    const { field } = showField()

    await user.type(field, 'Bied')
    await screen.findByRole('option', { name: 'Biedronka' })
    await user.keyboard('{Escape}')

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
    expect(field).toHaveAttribute('aria-expanded', 'false')
  })

  it('offers a new contractor only once the API has answered for the very text', async () => {
    const user = userEvent.setup()
    const { field, lastChoice } = showField()

    await user.type(field, 'Nowy')

    // Still asking: the name may yet be found, so nothing is offered.
    expect(screen.queryByRole('option')).not.toBeInTheDocument()

    await screen.findByRole('option', { name: '➕ Dodaj „Nowy”' })
    await user.keyboard('{ArrowDown}{Enter}')

    expect(lastChoice()).toEqual({ id: null, name: 'Nowy' })
    expect(screen.getByText('Nowy kontrahent „Nowy” zostanie dodany przy zapisie.')).toBeInTheDocument()
  })

  it('does not offer to add a name that exists', async () => {
    const user = userEvent.setup()
    const { field } = showField()

    await user.type(field, 'Probe2')
    await screen.findByRole('option', { name: 'Probe2' })

    expect(screen.queryByRole('option', { name: /Dodaj/ })).not.toBeInTheDocument()
  })
})
