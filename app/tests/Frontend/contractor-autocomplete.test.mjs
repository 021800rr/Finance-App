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
