import { initLineChart } from './line-chart';

// The prices of each gold asset, [{assetId, label, prices: {date: price}}], with two decimal places. Two assets may
// have the same label, so each gets its own line.
initLineChart({
    canvasId: 'goldPricesChart',
    toSeries: (assets) => assets.map((asset) => ({ label: asset.label, values: asset.prices })),
    colors: [
        'rgb(255, 215, 0)', // Gold
        'rgb(192, 192, 192)', // Silver
        'rgb(205, 127, 50)', // Bronze/Copper
        'rgb(75, 192, 192)',
        'rgb(255, 99, 132)',
        'rgb(54, 162, 235)',
        'rgb(153, 102, 255)'
    ],
    fractionDigits: 2
});
