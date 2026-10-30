import { Controller } from '@hotwired/stimulus';

function currencyOf(select) {
    return select.selectedOptions[0]?.dataset.currency ?? null;
}

/**
 * With one currency the second amount of a transfer plays no role (the server ignores it), so the field is blocked.
 * What was typed comes back when the currencies differ again.
 */
export default class extends Controller {
    static targets = ['from', 'to', 'amountTo'];
    static values = { sameCurrencyPlaceholder: String };

    connect() {
        this.sync();
    }

    sync() {
        const from = currencyOf(this.fromTarget);
        const sameCurrency = from !== null && from === currencyOf(this.toTarget);
        const field = this.amountToTarget;

        if (sameCurrency && !field.disabled) {
            this.typedAmount = field.value;
            field.value = '';
        } else if (!sameCurrency && field.disabled) {
            field.value = this.typedAmount ?? '';
        }

        field.disabled = sameCurrency;
        field.placeholder = sameCurrency ? this.sameCurrencyPlaceholderValue : '';
    }
}
