import type { Series } from './LineChart'

type Reading<T> = {
  // the line a row belongs to (a bank, a gold asset) and its name in the legend
  line: (row: T) => number
  label: (line: number) => string
  // the day and the value of the row, as the API sends them
  date: (row: T) => string
  value: (row: T) => string
}

// The lines of a chart from rows of the API: one line per bank or asset, in the order they first appear. Two lines
// may have the same name (two assets described alike) and still stay two lines.
export function toSeries<T>(rows: T[], { line, label, date, value }: Reading<T>): Series[] {
  const lines = new Map<number, Series>()
  for (const row of rows) {
    const id = line(row)
    let series = lines.get(id)
    if (series === undefined) {
      series = { label: label(id), values: {} }
      lines.set(id, series)
    }
    series.values[date(row)] = value(row)
  }

  return [...lines.values()]
}
