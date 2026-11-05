import assert from 'node:assert/strict';
import { test } from 'node:test';

/**
 * The smallest DOM sort-table.js needs: elements that keep their children, text, attributes and click handlers.
 */
function element(text = '') {
    return {
        children: [],
        dataset: {},
        style: {},
        attributes: {},
        textContent: text,
        className: '',
        onClick: null,
        appendChild(child) {
            this.children = this.children.filter(existing => existing !== child);
            this.children.push(child);
        },
        addEventListener(type, handler) {
            if ('click' === type) {
                this.onClick = handler;
            }
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
        querySelectorAll() {
            return this.children;
        },
    };
}

function row(bank, balance) {
    const tr = element();
    tr.children = [element(bank), element(balance), element('')];

    return tr;
}

function accountsTable() {
    const bank = element('Bank');
    bank.dataset.sortKey = 'text';
    const balance = element('Balance');
    balance.dataset.sortKey = 'number';
    const actions = element('');
    const tbody = element();
    tbody.children = [row('mBank', '1 200,00'), row('Alior', '-50,00'), row('PKO', '300,00')];

    return {
        headers: [bank, balance, actions],
        tbody,
        dataset: { sortAscLabel: 'Sort ascending', sortDescLabel: 'Sort descending' },
        querySelector: () => tbody,
        querySelectorAll: () => [bank, balance, actions],
    };
}

let table;
let onReady;
globalThis.document = {
    querySelector: () => table,
    createElement: () => element(),
    addEventListener: (type, handler) => {
        onReady = handler;
    },
};
await import('../../assets/js/sort-table.js');

function prepareTable() {
    table = accountsTable();
    onReady();

    return table;
}

const arrows = header => header.children[0].children;
const shown = header => arrows(header).map(button => button.textContent).join('');
const banks = () => table.tbody.children.map(tr => tr.children[0].textContent);
const click = button => button.onClick({ stopPropagation() { button.stopped = true; } });

test('every sortable column gets two empty arrows, a column without a sort key none', () => {
    const { headers } = prepareTable();

    assert.equal(shown(headers[0]), '△▽');
    assert.equal(shown(headers[1]), '△▽');
    assert.equal(headers[2].children.length, 0);
    assert.equal(arrows(headers[0])[0].attributes['aria-label'], 'Sort ascending');
    assert.equal(arrows(headers[0])[1].attributes['aria-label'], 'Sort descending');
});

test('the up arrow sorts ascending and is the only filled one', () => {
    const { headers } = prepareTable();

    click(arrows(headers[0])[0]);

    assert.deepEqual(banks(), ['Alior', 'mBank', 'PKO']);
    assert.equal(shown(headers[0]), '▲▽');
    assert.equal(shown(headers[1]), '△▽');
    assert.equal(arrows(headers[0])[0].attributes['aria-pressed'], 'true');
});

test('the down arrow sorts descending, by number for a number column, and empties the other column', () => {
    const { headers } = prepareTable();
    click(arrows(headers[0])[0]);

    click(arrows(headers[1])[1]);

    assert.deepEqual(banks(), ['mBank', 'PKO', 'Alior']);
    assert.equal(shown(headers[0]), '△▽');
    assert.equal(shown(headers[1]), '△▼');
});

test('an arrow does not pass its click on to the header', () => {
    const { headers } = prepareTable();
    const up = arrows(headers[0])[0];

    click(up);

    assert.equal(up.stopped, true);
});

test('clicking the header still toggles the order and the arrows follow it', () => {
    const { headers } = prepareTable();

    headers[0].onClick();
    assert.deepEqual(banks(), ['PKO', 'mBank', 'Alior']);
    assert.equal(shown(headers[0]), '△▼');

    headers[0].onClick();
    assert.deepEqual(banks(), ['Alior', 'mBank', 'PKO']);
    assert.equal(shown(headers[0]), '▲▽');
});
