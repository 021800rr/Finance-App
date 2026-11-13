import { describe, expect, it } from 'vitest'
import { labelledTicks, MAX_LABELS } from './chartTicks'

const days = (count: number) => Array.from({ length: count }, (_, index) => index)

describe('labelledTicks', () => {
  it('labels every date up to ten of them', () => {
    expect(labelledTicks(days(10))).toEqual(days(10))
    expect(labelledTicks(days(3))).toEqual([0, 1, 2])
    expect(labelledTicks([])).toEqual([])
  })

  it('labels ten dates of more, the first and the last among them, spread evenly as the web app does', () => {
    expect(labelledTicks(days(12))).toEqual([0, 1, 2, 4, 5, 6, 7, 9, 10, 11])
    expect(labelledTicks(days(100))).toEqual([0, 11, 22, 33, 44, 55, 66, 77, 88, 99])
  })

  it('never labels more than ten, however many dates there are', () => {
    for (const count of [11, 37, 365, 2000]) {
      const labelled = labelledTicks(days(count))
      expect(labelled).toHaveLength(MAX_LABELS)
      expect(labelled[0]).toBe(0)
      expect(labelled.at(-1)).toBe(count - 1)
    }
  })
})
