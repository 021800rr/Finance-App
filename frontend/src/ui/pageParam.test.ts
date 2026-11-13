import { describe, expect, it } from 'vitest'
import { pageFromParam } from './pageParam'

describe('pageFromParam', () => {
  it('reads a whole number from 1 up', () => {
    expect(pageFromParam('3')).toBe(3)
  })

  it.each([null, '', '0', '-1', '2.5', '1e2', '007', 'abc'])('gives the first page for %j', (value) => {
    expect(pageFromParam(value)).toBe(1)
  })
})
