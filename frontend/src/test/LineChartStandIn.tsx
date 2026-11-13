import type { Series } from '../ui/LineChart'

// jsdom has no canvas to draw on. A page test puts this list of the lines in place of the chart, so it sees what the
// page asks the chart to draw:  vi.mock('../ui/LineChart', () => import('../test/LineChartStandIn'))
export function LineChart({ series }: { series: Series[] }) {
  return (
    <ul aria-label="chart">
      {series.map((line, index) => <li key={index}>{line.label}: {JSON.stringify(line.values)}</li>)}
    </ul>
  )
}
