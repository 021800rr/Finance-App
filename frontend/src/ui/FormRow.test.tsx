import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ContractorField } from '../operation/ContractorField'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AmountInput } from './AmountInput'
import { DateInput } from './DateInput'
import { fieldErrorProps } from './form'
import { FormRow } from './FormRow'

describe('field error accessibility', () => {
  it('connects errors to native and custom inputs and clears their invalid state', () => {
    const error = 'Wpisz poprawną wartość.'
    function Fields({ error }: { error?: string }) {
      return <>
        <FormRow label="Opis" htmlFor="description" error={error}>
          <input id="description" {...fieldErrorProps('description', error)} />
        </FormRow>
        <FormRow label="Kwota" htmlFor="amount" error={error}>
          <AmountInput id="amount" value="" onChange={() => {}} allowMinus className=""
            {...fieldErrorProps('amount', error)} />
        </FormRow>
        <FormRow label="Data" htmlFor="date" error={error}>
          <DateInput id="date" value="" onChange={() => {}} min="2025-01-01" max="2070-12-31" className=""
            {...fieldErrorProps('date', error)} />
        </FormRow>
      </>
    }
    const { rerender } = render(<Fields error={error} />)
    for (const label of ['Opis', 'Kwota', 'Data']) {
      expect(screen.getByLabelText(label)).toBeInvalid()
      expect(screen.getByLabelText(label)).toHaveAccessibleDescription(error)
    }

    rerender(<Fields />)
    for (const label of ['Opis', 'Kwota', 'Data']) {
      expect(screen.getByLabelText(label)).not.toHaveAttribute('aria-invalid')
      expect(screen.getByLabelText(label)).not.toHaveAttribute('aria-describedby')
    }
  })

  it('connects a contractor error directly to its combobox', () => {
    render(
      <QueryClientProvider client={new QueryClient()}>
        <ContractorField id="contractor" value={null} onChange={() => {}}
          error="Wybierz kontrahenta." inputClassName="" />
      </QueryClientProvider>,
    )

    expect(screen.getByRole('combobox')).toBeInvalid()
    expect(screen.getByRole('combobox')).toHaveAccessibleDescription('Wybierz kontrahenta.')
  })
})
