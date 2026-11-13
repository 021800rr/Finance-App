import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { SortHeader } from './SortHeader'
import type { SortDirection } from './sorting'

function showHeader(direction: SortDirection | undefined) {
  const onSort = vi.fn()
  render(
    <table>
      <thead>
        <tr><SortHeader label="Saldo" direction={direction} onSort={onSort} /></tr>
      </thead>
    </table>,
  )

  return onSort
}

const arrow = (label: string) => screen.getByRole('button', { name: i18n.t(label) })

describe('SortHeader', () => {
  it.each([
    [undefined, 'asc'],
    ['asc', 'desc'],
    ['desc', 'asc'],
  ] as const)('switches the order with a click on the header: %s to %s', async (direction, next) => {
    const onSort = showHeader(direction)

    await userEvent.click(screen.getByRole('columnheader'))

    expect(onSort.mock.calls).toEqual([[next]])
  })

  // The header under the arrow would otherwise switch the order straight back.
  it('sets the order with an arrow, the header leaving it be', async () => {
    const onSort = showHeader('desc')

    await userEvent.click(arrow('common.sort_descending'))

    expect(onSort.mock.calls).toEqual([['desc']])
  })

  it('fills the arrow of the current order', () => {
    showHeader('asc')

    expect(screen.getByRole('columnheader')).toHaveAttribute('aria-sort', 'ascending')
    expect(arrow('common.sort_ascending')).toHaveAttribute('aria-pressed', 'true')
    expect(arrow('common.sort_ascending')).toHaveTextContent('▲')
    expect(arrow('common.sort_descending')).toHaveAttribute('aria-pressed', 'false')
    expect(arrow('common.sort_descending')).toHaveTextContent('▽')
  })
})
