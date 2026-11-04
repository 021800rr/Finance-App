import assert from 'node:assert/strict';
import { test } from 'node:test';
import ContractorAutocompleteController from '../../assets/controllers/contractor_autocomplete_controller.js';

function prepareController(t) {
    const controller = Object.create(ContractorAutocompleteController.prototype);
    Object.defineProperty(controller, 'element', {
        value: {
            dataset: {
                createUrl: '/contractor/create',
                msgCreateFailed: 'Błąd przy tworzeniu kontrahenta.',
                msgCreated: 'Dodano kontrahenta: {name}',
                msgSessionExpired: 'Sesja wygasła. Zaloguj się ponownie.',
            },
        },
    });
    controller.inputTarget = { value: 'ACME', classList: { remove() {} } };
    controller.hiddenTarget = { value: '' };
    controller.hasHiddenTarget = true;
    t.mock.method(controller, 'getCsrfToken', () => 'csrf-token');
    t.mock.method(controller, 'showMessage', () => {});
    t.mock.method(controller, 'search', async () => {});
    t.mock.method(controller, 'clearResults', () => {});

    return controller;
}

test('one typed character already searches, a blank input does not', t => {
    const controller = prepareController(t);
    controller.clearMessage = () => {};

    controller.inputTarget.value = ' X ';
    controller.onInput();
    assert.deepEqual(controller.search.mock.calls[0].arguments, ['X']);

    controller.inputTarget.value = '   ';
    controller.onInput();
    assert.equal(controller.search.mock.calls.length, 1);
    assert.equal(controller.clearResults.mock.calls.length, 1);
});

test('422 explains the duplicate and refreshes suggestions without selecting a contractor', async t => {
    const controller = prepareController(t);
    const fetch = t.mock.method(globalThis, 'fetch', async () => Response.json(
        { errors: ['Kontrahent już istnieje — wybierz go z listy.'] },
        { status: 422 },
    ));

    await controller.createContractor('ACME');

    assert.equal(fetch.mock.calls.length, 1);
    assert.equal(fetch.mock.calls[0].arguments[1].headers['X-CSRF-Token'], 'csrf-token');
    assert.equal(controller.showMessage.mock.calls.length, 1);
    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, [
        'error',
        'Kontrahent już istnieje — wybierz go z listy.',
    ]);
    assert.equal(controller.search.mock.calls.length, 1);
    assert.deepEqual(controller.search.mock.calls[0].arguments, ['ACME']);
    assert.equal(controller.hiddenTarget.value, '');
    assert.equal(controller.inputTarget.value, 'ACME');
    assert.equal(controller.clearResults.mock.calls.length, 0);
});

test('422 displays form validation errors returned by the server', async t => {
    const controller = prepareController(t);
    t.mock.method(globalThis, 'fetch', async () => Response.json(
        { errors: ['Nazwa kontrahenta może mieć najwyżej 255 znaków.'] },
        { status: 422 },
    ));

    await controller.createContractor('A');

    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, [
        'error',
        'Nazwa kontrahenta może mieć najwyżej 255 znaków.',
    ]);
    assert.equal(controller.hiddenTarget.value, '');
});

test('422 without readable validation errors retains the general error message', async t => {
    const controller = prepareController(t);
    t.mock.method(globalThis, 'fetch', async () => new Response('Invalid response', { status: 422 }));

    await controller.createContractor('ACME');

    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, ['error', 'Błąd przy tworzeniu kontrahenta.']);
    assert.equal(controller.search.mock.calls.length, 0);
    assert.equal(controller.hiddenTarget.value, '');
});

test('successful creation still selects the returned contractor', async t => {
    const controller = prepareController(t);
    t.mock.method(globalThis, 'fetch', async () => Response.json({ id: 123, name: 'ACME' }));

    await controller.createContractor('ACME');

    assert.equal(controller.hiddenTarget.value, 123);
    assert.equal(controller.inputTarget.value, 'ACME');
    assert.equal(controller.showMessage.mock.calls.length, 1);
    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, ['success', 'Dodano kontrahenta: ACME']);
    assert.equal(controller.search.mock.calls.length, 0);
    assert.equal(controller.clearResults.mock.calls.length, 1);
});

test('an expired session asks to log in again instead of reporting success', async t => {
    const controller = prepareController(t);
    const loginPage = new Response('<html>login form</html>', { status: 200 });
    Object.defineProperty(loginPage, 'redirected', { value: true });
    t.mock.method(globalThis, 'fetch', async () => loginPage);

    await controller.createContractor('ACME');

    assert.equal(controller.showMessage.mock.calls.length, 1);
    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, ['error', 'Sesja wygasła. Zaloguj się ponownie.']);
    assert.equal(controller.hiddenTarget.value, '');
    assert.equal(controller.search.mock.calls.length, 0);
});

test('a successful response without an id is an error, not a success', async t => {
    const controller = prepareController(t);
    t.mock.method(globalThis, 'fetch', async () => new Response('<html>not JSON</html>', { status: 200 }));

    await controller.createContractor('ACME');

    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, ['error', 'Błąd przy tworzeniu kontrahenta.']);
    assert.equal(controller.hiddenTarget.value, '');
});

test('other failures retain the general error message', async t => {
    const controller = prepareController(t);
    t.mock.method(globalThis, 'fetch', async () => new Response('Unexpected error', { status: 500 }));
    t.mock.method(console, 'warn', () => {});

    await controller.createContractor('ACME');

    assert.equal(controller.showMessage.mock.calls.length, 1);
    assert.deepEqual(controller.showMessage.mock.calls[0].arguments, ['error', 'Błąd przy tworzeniu kontrahenta.']);
    assert.equal(controller.search.mock.calls.length, 0);
    assert.equal(controller.hiddenTarget.value, '');
});

/**
 * The smallest DOM renderResults() needs: elements that keep their children, text and markup.
 */
function fakeDocument() {
    const element = () => ({
        children: [],
        style: {},
        dataset: {},
        innerHTML: '',
        textContent: '',
        appendChild(child) { this.children.push(child); },
        addEventListener() {},
    });

    return { createElement: element };
}

function addOptions(controller) {
    return controller.resultsTarget.children[0].children
        .filter(li => li.innerHTML.startsWith('➕'))
        .map(li => li.innerHTML);
}

function prepareResults(t) {
    const controller = prepareController(t);
    controller.hasResultsTarget = true;
    controller.resultsTarget = fakeDocument().createElement('div');
    // Node has no DOM, so the fake exists only for this test.
    globalThis.document = fakeDocument();
    t.after(() => { delete globalThis.document; });

    return controller;
}

test('a name differing only in letter case can still be added, as the server accepts it', t => {
    const controller = prepareResults(t);

    controller.renderResults([{ id: 6, name: 'Netflix' }], 'netflix');

    assert.deepEqual(addOptions(controller), ['➕ Dodaj „netflix”']);
});

test('the exact name of a listed contractor is not offered to be added again', t => {
    const controller = prepareResults(t);

    controller.renderResults([{ id: 6, name: 'Netflix' }, { id: 7, name: 'Netflix Kids' }], 'Netflix');

    assert.deepEqual(addOptions(controller), []);
});

test('with no suggestions, the typed name is offered to be added', t => {
    const controller = prepareResults(t);

    controller.renderResults([], 'Nowy kontrahent');

    assert.deepEqual(addOptions(controller), ['➕ Dodaj „Nowy kontrahent”']);
});

function prepareSearch(t, typed) {
    const controller = prepareController(t);
    controller.element.dataset.searchUrl = '/contractor/search';
    controller.inputTarget.value = typed;
    t.mock.method(controller, 'renderResults', () => {});
    t.mock.method(globalThis, 'fetch', async () => Response.json([{ id: 6, name: 'Netflix' }]));

    return controller;
}

test('an answer to an earlier query does not replace the list for what is typed now', async t => {
    const controller = prepareSearch(t, 'net');

    // prepareController() replaces search() with a mock, so the real one is called directly.
    await ContractorAutocompleteController.prototype.search.call(controller, 'ne');

    assert.equal(controller.renderResults.mock.calls.length, 0);
});

test('the answer to what is typed now is shown', async t => {
    const controller = prepareSearch(t, ' net ');

    await ContractorAutocompleteController.prototype.search.call(controller, 'net');

    assert.deepEqual(controller.renderResults.mock.calls[0].arguments, [[{ id: 6, name: 'Netflix' }], 'net']);
});
