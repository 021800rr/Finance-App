import { initLineChart } from './line-chart';

// The rates of each bank, {bank: {date: rate}}, with four decimal places.
initLineChart({
    canvasId: 'ratesChart',
    toSeries: (rates) => Object.entries(rates).map(([bank, values]) => ({ label: bank, values })),
    colors: [
        'rgb(75, 192, 192)',
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(255, 205, 86)',
        'rgb(153, 102, 255)',
        'rgb(201, 203, 207)',
        'rgb(255, 159, 64)'
    ],
    fractionDigits: 4
});
