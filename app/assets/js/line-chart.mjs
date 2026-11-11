import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns';

/*
 * A line chart with one line per series, a vertical line under the cursor and, below the chart, the values of the date
 * nearest to it. The canvas carries the data as JSON in data-chart and the axis titles in data-x-axis-title and
 * data-y-axis-title. A series is {label, values: {date: value}}; two series may share a label.
 */

const parseDateToTimestamp = (dateString) => {
    const fullDate = Date.parse(`${dateString}T00:00:00`);
    if (Number.isFinite(fullDate)) {
        return fullDate;
    }

    return Date.parse(dateString);
};

export const buildDatasets = (series, colors) => series.map(({ label, values }, index) => {
    const color = colors[index % colors.length];
    const dataPoints = Object.entries(values).map(([date, value]) => ({
        x: date,
        y: value
    })).sort((a, b) => new Date(a.x) - new Date(b.x));

    return {
        label,
        values,
        data: dataPoints,
        borderColor: color,
        backgroundColor: color.replace('rgb', 'rgba').replace(')', ', 0.1)'),
        tension: 0.1,
        spanGaps: true
    };
});

/**
 * Every date of every series once, as sorted timestamps, and the date key of each timestamp.
 */
export const collectDates = (series) => {
    const dateKeyByTimestamp = new Map();
    const timestamps = [];
    series.forEach(({ values }) => {
        Object.keys(values).forEach((dateKey) => {
            const timestamp = parseDateToTimestamp(dateKey);
            if (!Number.isFinite(timestamp)) {
                return;
            }

            if (!dateKeyByTimestamp.has(timestamp)) {
                dateKeyByTimestamp.set(timestamp, dateKey);
                timestamps.push(timestamp);
            }
        });
    });

    timestamps.sort((a, b) => a - b);

    return { timestamps, dateKeyByTimestamp };
};

export const findNearestTimestamp = (timestamps, value) => {
    if (!timestamps.length || !Number.isFinite(value)) {
        return null;
    }

    return timestamps.reduce((nearest, current) => {
        if (nearest === null) {
            return current;
        }

        return Math.abs(current - value) < Math.abs(nearest - value) ? current : nearest;
    }, null);
};

/**
 * On a desktop every label is shown up to 10 ticks; above that the first, the last and 8 spread evenly between them.
 */
export const desktopTickIndexes = (ticksLength) => {
    const visibleIndexes = new Set();
    if (ticksLength <= 10) {
        for (let i = 0; i < ticksLength; i += 1) {
            visibleIndexes.add(i);
        }
    } else {
        visibleIndexes.add(0);
        visibleIndexes.add(ticksLength - 1);
        for (let i = 1; i <= 8; i += 1) {
            const index = Math.round((i * (ticksLength - 1)) / 9);
            visibleIndexes.add(index);
        }
    }

    return visibleIndexes;
};

/**
 * @param canvasId       the id of the canvas, the chart is skipped on pages without it
 * @param toSeries       turns the JSON of data-chart into a list of series
 * @param colors         the line colors, reused in turn when there are more series
 * @param fractionDigits the decimal places of a value below the chart
 */
export function initLineChart({ canvasId, toSeries, colors, fractionDigits }) {
    document.addEventListener('DOMContentLoaded', () => {
        const chartContainer = document.getElementById(canvasId);
        if (!chartContainer) {
            return;
        }

        const chartDataRaw = chartContainer.dataset.chart;
        if (!chartDataRaw) {
            return;
        }

        const series = toSeries(JSON.parse(chartDataRaw));
        const datasets = buildDatasets(series, colors);
        const { timestamps, dateKeyByTimestamp } = collectDates(series);

        const getOrCreateExternalTooltip = (chart) => {
            const chartWrapper = chart.canvas.parentNode;
            chartWrapper.style.position = 'relative';
            chartWrapper.style.overflow = 'visible';

            let tooltipEl = chartWrapper.querySelector('.line-chart-tooltip');
            if (tooltipEl) {
                return tooltipEl;
            }

            tooltipEl = document.createElement('div');
            tooltipEl.className = 'line-chart-tooltip border rounded px-2 py-1 mt-2 small';
            tooltipEl.style.minHeight = '3.25rem';
            tooltipEl.style.position = 'absolute';
            tooltipEl.style.left = '0';
            tooltipEl.style.top = 'calc(100% + 8px)';
            tooltipEl.style.zIndex = '20';
            tooltipEl.style.pointerEvents = 'none';
            tooltipEl.style.visibility = 'hidden';
            tooltipEl.style.backgroundColor = getComputedStyle(document.body).backgroundColor;
            tooltipEl.style.color = getComputedStyle(document.body).color;

            chartWrapper.appendChild(tooltipEl);
            return tooltipEl;
        };

        const renderExternalTooltip = (chart, nearestTimestamp) => {
            const tooltipEl = getOrCreateExternalTooltip(chart);
            if (!Number.isFinite(nearestTimestamp)) {
                tooltipEl.style.visibility = 'hidden';
                tooltipEl.innerHTML = '';
                return;
            }

            tooltipEl.innerHTML = '';

            const dateTitle = new Intl.DateTimeFormat('pl-PL', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(new Date(nearestTimestamp));
            const titleEl = document.createElement('div');
            titleEl.className = 'fw-semibold mb-1';
            titleEl.textContent = dateTitle;
            tooltipEl.appendChild(titleEl);

            const selectedDateKey = dateKeyByTimestamp.get(nearestTimestamp);
            let visibleRows = 0;
            datasets.forEach((dataset) => {
                const datasetLabel = dataset.label ?? '';
                const valueRaw = selectedDateKey ? dataset.values[selectedDateKey] : undefined;
                if (valueRaw === undefined) {
                    return;
                }

                const value = Number(valueRaw);
                if (!Number.isFinite(value)) {
                    return;
                }

                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2';

                const marker = document.createElement('span');
                marker.style.width = '8px';
                marker.style.height = '8px';
                marker.style.borderRadius = '50%';
                marker.style.display = 'inline-block';
                marker.style.background = dataset.borderColor ?? '#6c757d';

                const text = document.createElement('span');
                const formattedValue = value.toLocaleString('pl-PL', {
                    minimumFractionDigits: fractionDigits,
                    maximumFractionDigits: fractionDigits
                });
                text.textContent = `${datasetLabel}: ${formattedValue}`;

                row.appendChild(marker);
                row.appendChild(text);
                tooltipEl.appendChild(row);
                visibleRows += 1;
            });

            if (visibleRows === 0) {
                tooltipEl.style.visibility = 'hidden';
                return;
            }

            tooltipEl.style.visibility = 'visible';
        };

        const ctx = chartContainer.getContext('2d');
        const isDesktop = window.matchMedia('(min-width: 992px)').matches;
        const tickDateFormatter = new Intl.DateTimeFormat('pl-PL', {
            year: '2-digit',
            month: '2-digit',
            day: '2-digit'
        });
        const desktopTickIndexCache = new Map();
        const getDesktopVisibleTickIndexes = (ticksLength) => {
            if (!desktopTickIndexCache.has(ticksLength)) {
                desktopTickIndexCache.set(ticksLength, desktopTickIndexes(ticksLength));
            }

            return desktopTickIndexCache.get(ticksLength);
        };

        const verticalHoverLinePlugin = {
            id: 'verticalHoverLine',
            afterEvent(chart, args) {
                const tooltipEl = getOrCreateExternalTooltip(chart);
                const event = args.event;

                if (!args.inChartArea || event.type === 'mouseout') {
                    chart.$hoverLineX = null;
                    chart.$hoverTimestamp = null;
                    tooltipEl.style.visibility = 'hidden';
                    tooltipEl.innerHTML = '';
                    chart.draw();
                    return;
                }

                const xScale = chart.scales.x;
                const hoveredRaw = xScale.getValueForPixel(event.x);
                const hoveredTimestamp = hoveredRaw instanceof Date ? hoveredRaw.getTime() : Number(hoveredRaw);
                const nearestTimestamp = findNearestTimestamp(timestamps, hoveredTimestamp);

                if (!Number.isFinite(nearestTimestamp)) {
                    chart.$hoverLineX = null;
                    chart.$hoverTimestamp = null;
                    tooltipEl.style.visibility = 'hidden';
                    tooltipEl.innerHTML = '';
                    chart.draw();
                    return;
                }

                chart.$hoverTimestamp = nearestTimestamp;
                chart.$hoverLineX = xScale.getPixelForValue(nearestTimestamp);
                renderExternalTooltip(chart, nearestTimestamp);
                chart.draw();
            },
            afterDatasetsDraw(chart) {
                const x = chart.$hoverLineX;
                if (!Number.isFinite(x)) {
                    return;
                }
                const {ctx, chartArea} = chart;

                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, chartArea.top);
                ctx.lineTo(x, chartArea.bottom);
                ctx.lineWidth = 1;
                ctx.strokeStyle = 'rgba(108, 117, 125, 0.6)';
                ctx.stroke();
                ctx.restore();
            }
        };

        new Chart(ctx, {
            type: 'line',
            data: {
                datasets: datasets
            },
            plugins: [verticalHoverLinePlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: chartContainer.dataset.yAxisTitle ?? ''
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'dd.MM.yy'
                            },
                            tooltipFormat: 'yyyy-MM-dd'
                        },
                        ticks: {
                            source: 'data',
                            autoSkip: !isDesktop,
                            autoSkipPadding: 16,
                            maxTicksLimit: isDesktop ? undefined : 5,
                            maxRotation: 45,
                            minRotation: 45,
                            callback(value, index, ticks) {
                                if (!isDesktop) {
                                    return this.getLabelForValue(value);
                                }

                                const visibleIndexes = getDesktopVisibleTickIndexes(ticks.length);
                                if (!visibleIndexes.has(index)) {
                                    return '';
                                }

                                const rawValue = value instanceof Date ? value : new Date(value);
                                return tickDateFormatter.format(rawValue).replace(/\//g, '.');
                            }
                        },
                        title: {
                            display: true,
                            text: chartContainer.dataset.xAxisTitle ?? ''
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        enabled: false
                    }
                }
            }
        });
    });
}
