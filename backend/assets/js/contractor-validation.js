document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('operation_form');
    const contractorId = document.getElementById('contractor_id');
    const contractorInput = document.getElementById('contractor_input');
    if (!form || !contractorId || !contractorInput) {
        return;
    }

    form.addEventListener('submit', function (event) {
        if (!contractorId.value) {
            event.preventDefault();
            contractorInput.classList.add('is-invalid');
        }
    });

    function clearContractorError() {
        if (contractorId.value) {
            contractorInput.classList.remove('is-invalid');
        }
    }

    contractorInput.addEventListener('input', clearContractorError);
    contractorInput.addEventListener('change', clearContractorError);
});
