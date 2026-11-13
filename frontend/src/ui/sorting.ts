export type SortDirection = 'asc' | 'desc'

// The column a table is sorted by and in which direction; null: the order the rows came in.
export type SortState = { column: string; direction: SortDirection } | null

// The rows ordered by the value one column gives each of them. Numbers compare as numbers, text as Polish text
// (ą after a, ł after l); equal rows keep their order.
export function sortRows<T>(rows: T[], value: (row: T) => string | number, direction: SortDirection): T[] {
  const sign = direction === 'asc' ? 1 : -1

  return rows.toSorted((a, b) => sign * compare(value(a), value(b)))
}

function compare(a: string | number, b: string | number): number {
  return typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b), 'pl')
}
