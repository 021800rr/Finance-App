import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    buildDatasets,
    collectDates,
    desktopTickIndexes,
    findNearestTimestamp,
} from '../../assets/js/line-chart.mjs';

const colors = ['rgb(1, 2, 3)', 'rgb(4, 5, 6)'];

test('each series becomes its own line, even when two share a label', () => {
    const datasets = buildDatasets([
        { label: 'Gold bar', values: { '2026-10-26': 100 } },
        { label: 'Gold bar', values: { '2026-10-26': 200 } },
    ], colors);

    assert.equal(datasets.length, 2);
    assert.deepEqual(datasets.map(dataset => dataset.values['2026-10-26']), [100, 200]);
});

test('the points of a line are sorted by date', () => {
    const [dataset] = buildDatasets([{ label: 'Bank1', values: { '2026-10-27': 2, '2026-10-25': 1 } }], colors);

    assert.deepEqual(dataset.data, [{ x: '2026-10-25', y: 1 }, { x: '2026-10-27', y: 2 }]);
});

test('the colors are reused in turn and the fill is the line color made transparent', () => {
    const datasets = buildDatasets([1, 2, 3].map(i => ({ label: `S${i}`, values: {} })), colors);

    assert.deepEqual(datasets.map(dataset => dataset.borderColor), ['rgb(1, 2, 3)', 'rgb(4, 5, 6)', 'rgb(1, 2, 3)']);
    assert.equal(datasets[0].backgroundColor, 'rgba(1, 2, 3, 0.1)');
});

test('every date of every series is collected once and sorted', () => {
    const { timestamps, dateKeyByTimestamp } = collectDates([
        { label: 'A', values: { '2026-10-27': 1, '2026-10-25': 1 } },
        { label: 'B', values: { '2026-10-25': 2, '2026-10-26': 2 } },
    ]);

    assert.deepEqual(timestamps.map(timestamp => dateKeyByTimestamp.get(timestamp)), [
        '2026-10-25',
        '2026-10-26',
        '2026-10-27',
    ]);
});

test('the nearest date is found, and none for an empty chart or a missing position', () => {
    const timestamps = [100, 200, 300];

    assert.equal(findNearestTimestamp(timestamps, 240), 200);
    assert.equal(findNearestTimestamp(timestamps, 260), 300);
    assert.equal(findNearestTimestamp([], 240), null);
    assert.equal(findNearestTimestamp(timestamps, Number.NaN), null);
});

test('up to 10 ticks every label is shown, above that the first, the last and 8 between them', () => {
    assert.deepEqual([...desktopTickIndexes(4)], [0, 1, 2, 3]);

    const shown = [...desktopTickIndexes(28)].sort((a, b) => a - b);
    assert.equal(shown.length, 10);
    assert.equal(shown[0], 0);
    assert.equal(shown[9], 27);
});
