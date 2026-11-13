// How many dates of a chart's axis get a label, as in the web app (assets/js/line-chart.mjs).
export const MAX_LABELS = 10

// The values that get a label: all of them up to `most`, otherwise the first, the last and the others spread evenly
// between them, so the labels never pile up however many dates there are.
export function labelledTicks<T>(values: T[], most = MAX_LABELS): T[] {
  if (values.length <= most) {
    return values
  }
  const step = (values.length - 1) / (most - 1)
  const picked = new Set(Array.from({ length: most }, (_, index) => Math.round(index * step)))

  return values.filter((_, index) => picked.has(index))
}
