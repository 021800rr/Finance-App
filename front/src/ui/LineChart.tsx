import { Chart, Legend, LinearScale, LineController, LineElement, type Plugin, PointElement } from 'chart.js'
import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { formatAmount } from '../money'
import { dateFromValue } from './form'
import { usePrefersDark } from './usePrefersDark'

Chart.register(LineController, LineElement, PointElement, LinearScale, Legend)

// One line of a chart: its name and its values by date, as the API sends them: { '2026-11-05': '4.5590' }.
export type Series = { label: string; values: Record<string, string> }

type Props = {
  // pass it memoized (useMemo): every new array draws the chart anew
  series: Series[]
  // the colours of the lines, used again in turn when there are more lines
  colors: string[]
  xTitle: string
  yTitle: string
}

// The line chart of the web app (assets/js/line-chart.js): one line per series over the dates, the labels at dates of
// the data, and under the cursor a vertical line at the nearest date, whose values appear under the chart.
export function LineChart({ series, colors, xTitle, yTitle }: Props) {
  const { i18n } = useTranslation()
  const locale = i18n.resolvedLanguage === 'en' ? 'en-US' : 'pl-PL'
  const canvas = React.useRef<HTMLCanvasElement>(null)
  const dark = usePrefersDark()
  // the date under the cursor, YYYY-MM-DD
  const [hovered, setHovered] = React.useState<string | null>(null)

  React.useEffect(() => {
    // The chart is drawn on a canvas, which the dark: classes do not reach: in the dark style its texts and grid get
    // colours of their own (slate-400, faint white lines); otherwise Chart.js keeps its defaults.
    const text = dark ? 'rgb(148, 163, 184)' : undefined
    const grid = dark ? 'rgba(255, 255, 255, 0.1)' : undefined
    // Every date of every series once, oldest first, and its place on the axis (local midnight).
    const dates = [...new Set(series.flatMap((line) => Object.keys(line.values)))]
      .filter((date) => dateFromValue(date) !== undefined)
      .sort()
    const timestamps = dates.map(timestampOf)
    // As in the web app: every date labelled up to 10 of them (5 on a phone), otherwise that many spread evenly.
    const labelled = spread(timestamps, window.matchMedia('(min-width: 992px)').matches ? 10 : 5)
    const tickFormat = new Intl.DateTimeFormat(locale, { year: '2-digit', month: '2-digit', day: '2-digit' })

    // The vertical line at the date nearest to the cursor; it tells the page which date that is.
    let hoverX: number | null = null
    const hoverLine: Plugin<'line'> = {
      id: 'hoverLine',
      afterEvent(chart, { event, inChartArea }) {
        const axis = chart.scales.x
        const value = inChartArea && event.type !== 'mouseout' && event.x !== null
          ? axis.getValueForPixel(event.x)
          : undefined
        const index = value === undefined ? -1 : nearestIndex(timestamps, value)
        const x = index === -1 ? null : axis.getPixelForValue(timestamps[index])
        if (x !== hoverX) {
          hoverX = x
          setHovered(index === -1 ? null : dates[index])
          chart.draw()
        }
      },
      afterDatasetsDraw(chart) {
        if (hoverX === null) {
          return
        }
        const { ctx, chartArea } = chart
        ctx.save()
        ctx.beginPath()
        ctx.moveTo(hoverX, chartArea.top)
        ctx.lineTo(hoverX, chartArea.bottom)
        ctx.lineWidth = 1
        ctx.strokeStyle = 'rgba(108, 117, 125, 0.6)'
        ctx.stroke()
        ctx.restore()
      },
    }

    const chart = new Chart(canvas.current!, {
      type: 'line',
      data: {
        datasets: series.map((line, index) => {
          const color = colors[index % colors.length]

          return {
            label: line.label,
            data: Object.entries(line.values)
              .map(([date, value]) => ({ x: timestampOf(date), y: Number(value) }))
              .sort((a, b) => a.x - b.x),
            borderColor: color,
            backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
            tension: 0.1,
            spanGaps: true,
          }
        }),
      },
      plugins: [hoverLine],
      options: {
        // the numbers of the axes in the language of the app, not of the browser
        locale,
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            // Dates as numbers (milliseconds): the gaps between them keep their length, and no date library is needed.
            type: 'linear',
            min: timestamps[0],
            max: timestamps[timestamps.length - 1],
            afterBuildTicks: (axis) => {
              axis.ticks = labelled.map((value) => ({ value }))
            },
            ticks: {
              minRotation: 45,
              maxRotation: 45,
              callback: (value) => tickFormat.format(new Date(Number(value))),
              color: text,
            },
            grid: { color: grid },
            title: { display: true, text: xTitle, color: text },
          },
          y: {
            ticks: { color: text },
            grid: { color: grid },
            title: { display: true, text: yTitle, color: text },
          },
        },
        plugins: { legend: { position: 'top', labels: { color: text } } },
      },
    })

    return () => chart.destroy()
  }, [series, colors, xTitle, yTitle, locale, dark])

  const hoveredDate = hovered === null ? undefined : dateFromValue(hovered)
  const dateFormat = new Intl.DateTimeFormat(locale, { year: 'numeric', month: '2-digit', day: '2-digit' })

  return (
    <div>
      <div className="relative h-[25rem]">
        <canvas ref={canvas} />
      </div>
      {/* The values of the date under the cursor. The box keeps its place without one, so the page does not jump. */}
      <div
        className={'mt-2 inline-block rounded border border-slate-200 dark:border-slate-700 px-2 py-1 text-sm '
          + 'tabular-nums ' + (hoveredDate === undefined ? 'invisible' : '')}
      >
        <div className="mb-1 font-semibold">
          {hoveredDate === undefined ? '\u00a0' : dateFormat.format(hoveredDate)}
        </div>
        {series.map((line, index) => (
          <div key={index} className="flex items-center gap-2">
            <span className="inline-block size-2 rounded-full" style={{ background: colors[index % colors.length] }} />
            {line.label}: {hovered !== null && line.values[hovered] !== undefined
              ? formatAmount(line.values[hovered])
              : '—'}
          </div>
        ))}
      </div>
    </div>
  )
}

function timestampOf(date: string): number {
  return dateFromValue(date)?.getTime() ?? Number.NaN
}

// At most `most` of the values, the first and the last among them, the others evenly between.
function spread(values: number[], most: number): number[] {
  if (values.length <= most) {
    return values
  }

  const picked = Array.from({ length: most }, (_, i) => values[Math.round((i * (values.length - 1)) / (most - 1))])

  return [...new Set(picked)]
}

function nearestIndex(values: number[], value: number): number {
  let nearest = -1
  values.forEach((current, index) => {
    if (nearest === -1 || Math.abs(current - value) < Math.abs(values[nearest] - value)) {
      nearest = index
    }
  })

  return nearest
}
