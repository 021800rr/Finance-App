import { act, render, screen } from '@testing-library/react'
import type { ChartConfiguration, Plugin } from 'chart.js'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '../i18n'
import { formatAmount } from '../money'
import { fakeMatchMedia } from '../test/fakeMatchMedia'
import { dateFromValue } from './form'
import { LineChart, type Series } from './LineChart'

// jsdom has no canvas: Chart.js is replaced by a stand-in that keeps what each chart was asked to draw.
const charts = vi.hoisted(() => [] as { config: ChartConfiguration<'line'>; destroy: () => void }[])

vi.mock('chart.js', () => ({
  Chart: class {
    static register() {}
    destroy = vi.fn()
    constructor(_canvas: HTMLCanvasElement, config: ChartConfiguration<'line'>) {
      charts.push({ config, destroy: this.destroy })
    }
  },
  Legend: {}, LinearScale: {}, LineController: {}, LineElement: {}, PointElement: {},
}))

// The parts of the x axis the chart sets up, as Chart.js gets them.
type XAxis = {
  min: number
  max: number
  afterBuildTicks: (axis: { ticks: { value: number }[] }) => void
  ticks: { callback: (value: number) => string; color?: string }
  grid: { color?: string }
}

const SERIES: Series[] = [
  { label: 'Bank A', values: { '2026-11-05': '4.5590', '2026-11-03': '4.4000' } },
  { label: 'Bank B', values: { '2026-11-04': '4.5000' } },
]
const COLORS = ['rgb(1, 2, 3)']

const at = (date: string) => dateFromValue(date)!.getTime()

function lastChart() {
  const chart = charts.at(-1)
  if (chart === undefined) {
    throw new Error('No chart was drawn.')
  }

  return chart
}

const xAxis = () => lastChart().config.options!.scales!.x as unknown as XAxis

function showChart(series = SERIES) {
  return render(<LineChart series={series} colors={COLORS} xTitle="Data" yTitle="Kurs" />)
}

// Chart.js as the plugin sees it: the pixels of the x axis are the timestamps themselves.
function fakeChart() {
  const ctx = { save: vi.fn(), beginPath: vi.fn(), moveTo: vi.fn(), lineTo: vi.fn(), stroke: vi.fn(), restore: vi.fn() }
  const chart = {
    scales: { x: { getValueForPixel: (pixel: number) => pixel, getPixelForValue: (value: number) => value } },
    chartArea: { top: 10, bottom: 300 },
    ctx,
    draw: vi.fn(),
  }
  const plugin = lastChart().config.plugins![0] as Plugin<'line'>

  return {
    chart,
    ctx,
    moveTo(x: number, type = 'mousemove', inChartArea = true) {
      act(() => { plugin.afterEvent!(chart as never, { event: { type, x }, inChartArea } as never, {}) })
    },
    draw() {
      plugin.afterDatasetsDraw!(chart as never, {}, {}, false)
    },
  }
}

beforeEach(() => {
  charts.length = 0
  fakeMatchMedia(false)
})

afterEach(() => { vi.unstubAllGlobals() })

describe('LineChart', () => {
  it('draws a line per series over its dates, oldest first, the colours used again in turn', () => {
    showChart()

    expect(lastChart().config.data.datasets).toEqual([
      expect.objectContaining({
        label: 'Bank A',
        data: [{ x: at('2026-11-03'), y: 4.4 }, { x: at('2026-11-05'), y: 4.559 }],
        borderColor: 'rgb(1, 2, 3)',
        backgroundColor: 'rgba(1, 2, 3, 0.1)',
      }),
      expect.objectContaining({
        label: 'Bank B', data: [{ x: at('2026-11-04'), y: 4.5 }], borderColor: 'rgb(1, 2, 3)',
      }),
    ])
    expect(xAxis()).toMatchObject({ min: at('2026-11-03'), max: at('2026-11-05') })
  })

  it('labels the dates of the data, in the language of the app', async () => {
    showChart()
    const axis = { ticks: [] as { value: number }[] }

    xAxis().afterBuildTicks(axis)

    expect(axis.ticks).toEqual([{ value: at('2026-11-03') }, { value: at('2026-11-04') }, { value: at('2026-11-05') }])
    expect(xAxis().ticks.callback(at('2026-11-03'))).toBe('03.11.26')
    try {
      await act(() => i18n.changeLanguage('en'))

      expect(xAxis().ticks.callback(at('2026-11-03'))).toBe('11/03/26')
    } finally {
      await act(() => i18n.changeLanguage('pl'))
    }
  })

  // The dark: classes do not reach a canvas.
  it('gives the texts and the grid colours of their own in the dark style, at once when it changes', () => {
    const media = fakeMatchMedia(false)
    showChart()
    expect(xAxis().ticks.color).toBeUndefined()
    const light = lastChart()

    act(() => { media.change(true) })

    expect(light.destroy).toHaveBeenCalled()
    expect(xAxis().ticks.color).toBe('rgb(148, 163, 184)')
    expect(xAxis().grid.color).toBe('rgba(255, 255, 255, 0.1)')
  })

  it('shows the values of the date nearest to the cursor, and a line at that date', () => {
    showChart()
    const chart = fakeChart()

    chart.moveTo(at('2026-11-04') + 3_600_000)

    expect(screen.getByText('04.11.2026')).toBeInTheDocument()
    expect(screen.getByText(`Bank B: ${formatAmount('4.5000')}`)).toBeInTheDocument()
    // Bank A has no rate that day
    expect(screen.queryByText(`Bank A: ${formatAmount('4.5590')}`)).not.toBeInTheDocument()
    chart.draw()
    expect(chart.ctx.moveTo).toHaveBeenCalledWith(at('2026-11-04'), 10)
    expect(chart.ctx.lineTo).toHaveBeenCalledWith(at('2026-11-04'), 300)
    expect(chart.ctx.stroke).toHaveBeenCalledOnce()

    // the same date again: nothing to draw anew
    chart.moveTo(at('2026-11-04'))

    expect(chart.chart.draw).toHaveBeenCalledOnce()
  })

  it('hides the values and the line when the cursor leaves the chart', () => {
    showChart()
    const chart = fakeChart()
    chart.moveTo(at('2026-11-04'))

    chart.moveTo(at('2026-11-04'), 'mouseout')

    expect(screen.queryByText(`Bank B: ${formatAmount('4.5000')}`)).not.toBeInTheDocument()
    chart.draw()
    expect(chart.ctx.stroke).not.toHaveBeenCalled()
  })

  it('waits for its x axis before following the cursor', () => {
    showChart()
    const chart = fakeChart()
    Reflect.deleteProperty(chart.chart.scales, 'x')

    chart.moveTo(at('2026-11-04'))

    expect(chart.chart.draw).not.toHaveBeenCalled()
  })

  it('tells a screen reader what it shows and every value', () => {
    showChart()

    expect(screen.getByRole('img')).toHaveAccessibleName('Kurs / Data: Bank A, Bank B')
    expect(screen.getByText('Bank A: 2026-11-05').nextSibling).toHaveTextContent(formatAmount('4.5590'))
  })

  it('draws anew for new lines, and is gone with the page', () => {
    const { rerender, unmount } = showChart()
    const first = lastChart()

    rerender(<LineChart series={[SERIES[1]!]} colors={COLORS} xTitle="Data" yTitle="Kurs" />)

    expect(first.destroy).toHaveBeenCalled()
    expect(lastChart().config.data.datasets).toHaveLength(1)

    unmount()

    expect(lastChart().destroy).toHaveBeenCalled()
  })
})
