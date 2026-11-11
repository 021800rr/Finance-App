import { describe, expect, it } from 'vitest'
import { sortRows } from './sorting'

describe('sortRows', () => {
  it('compares numbers as numbers, not as text', () => {
    const rows = [10, 9, 100]

    expect(sortRows(rows, (row) => row, 'asc')).toEqual([9, 10, 100])
    expect(sortRows(rows, (row) => row, 'desc')).toEqual([100, 10, 9])
  })

  it('puts the Polish letters where a Polish reader expects them', () => {
    const names = ['Zakopane', 'Łódź', 'Lublin', 'Ąb', 'Ab']

    expect(sortRows(names, (name) => name, 'asc')).toEqual(['Ab', 'Ąb', 'Lublin', 'Łódź', 'Zakopane'])
  })

  it('keeps the order of equal rows and leaves the given list as it was', () => {
    const rows = [{ bank: 'B', id: 1 }, { bank: 'A', id: 2 }, { bank: 'B', id: 3 }]

    expect(sortRows(rows, (row) => row.bank, 'asc').map((row) => row.id)).toEqual([2, 1, 3])
    expect(rows.map((row) => row.id)).toEqual([1, 2, 3])
  })
})
