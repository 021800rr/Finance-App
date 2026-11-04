import assert from 'node:assert/strict';
import { test } from 'node:test';
import TransferAmountsController from '../../assets/controllers/transfer_amounts_controller.js';

/**
 * An account select as the controller reads it: the chosen option carries the account currency in data-currency,
 * the empty "choose an account" option carries none.
 */
function accountSelect(currency = null) {
    return { selectedOptions: [{ dataset: currency === null ? {} : { currency } }] };
}

function prepareController(fromCurrency, toCurrency, typedAmount) {
    const controller = Object.create(TransferAmountsController.prototype);
    controller.fromTarget = accountSelect(fromCurrency);
    controller.toTarget = accountSelect(toCurrency);
    controller.amountToTarget = { value: typedAmount, disabled: false, placeholder: '' };
    controller.sameCurrencyPlaceholderValue = 'Not needed for one currency';

    return controller;
}

test('with one currency the second amount is cleared, blocked and explained', () => {
    const controller = prepareController('PLN', 'PLN', '450.00');

    controller.sync();

    assert.equal(controller.amountToTarget.value, '');
    assert.equal(controller.amountToTarget.disabled, true);
    assert.equal(controller.amountToTarget.placeholder, 'Not needed for one currency');
});

test('with two currencies the second amount stays as typed', () => {
    const controller = prepareController('PLN', 'CHF', '100.00');

    controller.sync();

    assert.equal(controller.amountToTarget.value, '100.00');
    assert.equal(controller.amountToTarget.disabled, false);
    assert.equal(controller.amountToTarget.placeholder, '');
});

test('the typed amount comes back when the currencies differ again', () => {
    const controller = prepareController('PLN', 'CHF', '100.00');

    controller.toTarget = accountSelect('PLN');
    controller.sync();
    controller.toTarget = accountSelect('CHF');
    controller.sync();

    assert.equal(controller.amountToTarget.value, '100.00');
    assert.equal(controller.amountToTarget.disabled, false);
});

test('accounts not chosen yet do not block the second amount', () => {
    const controller = prepareController(null, null, '');

    controller.sync();

    assert.equal(controller.amountToTarget.disabled, false);
});
