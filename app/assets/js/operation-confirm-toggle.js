const findCsrfToken = () => {
  const meta = document.querySelector('meta[name="csrf-token-operation-toggle-confirmed"]');
  return meta ? meta.content : '';
};

const updateButtonState = (button, confirmed) => {
  button.dataset.operationConfirmed = confirmed ? '1' : '0';
  button.classList.toggle('is-confirmed', confirmed);
  button.classList.toggle('is-unconfirmed', !confirmed);

  const icon = button.querySelector('img');
  if (icon) {
    const okSrc = button.dataset.okSrc || '/images/ok.png';
    const questionSrc = button.dataset.questionSrc || '/images/question.png';
    icon.src = confirmed ? okSrc : questionSrc;
  }
};

const handleToggle = async (button) => {
  const url = button.dataset.toggleUrl;
  if (!url) return;

  button.disabled = true;

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': findCsrfToken(),
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
    });

    if (!res.ok) {
      return;
    }

    const data = await res.json().catch(() => null);
    if (!data || typeof data.confirmed !== 'boolean') {
      return;
    }

    updateButtonState(button, data.confirmed);
  } catch (err) {
    console.error(err);
  } finally {
    button.disabled = false;
  }
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.op-confirm-toggle').forEach((button) => {
    button.addEventListener('click', () => handleToggle(button));
  });
});
