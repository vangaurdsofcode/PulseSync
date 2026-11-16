(() => {
  const widget = document.querySelector('[data-pulsesync-widget]');
  if (!widget || !window.pulseSyncWidget) return;

  const toggle = widget.querySelector('.pulsesync-toggle');
  const panel = widget.querySelector('.pulsesync-panel');
  const close = widget.querySelector('.pulsesync-close');
  const status = widget.querySelector('.pulsesync-status');
  const submit = widget.querySelector('.pulsesync-submit');

  fetch(window.pulseSyncWidget.endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.pulseSyncWidget.nonce,
    },
    body: JSON.stringify({
      type: 'page_view',
      page_url: window.location.href,
      browser: navigator.userAgent.slice(0, 120),
      fields: {},
    }),
    keepalive: true,
  }).catch((error) => console.warn('PulseSync could not queue this page view.', error));

  const setOpen = (open) => {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (open) panel.querySelector('input').focus();
    else toggle.focus();
  };

  toggle.addEventListener('click', () => setOpen(panel.hidden));
  close.addEventListener('click', () => setOpen(false));

  panel.addEventListener('submit', async (event) => {
    event.preventDefault();
    submit.disabled = true;
    status.textContent = window.pulseSyncWidget.labels.sending;
    status.classList.remove('is-error');
    const form = new FormData(panel);
    try {
      const response = await fetch(window.pulseSyncWidget.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.pulseSyncWidget.nonce,
        },
        body: JSON.stringify({
          type: 'lead',
          page_url: window.location.href,
          browser: navigator.userAgent.slice(0, 120),
          fields: {
            name: form.get('name'),
            email: form.get('email'),
            message: form.get('message'),
          },
        }),
      });
      if (!response.ok) throw new Error('Request failed');
      panel.reset();
      status.textContent = window.pulseSyncWidget.labels.success;
    } catch {
      status.textContent = window.pulseSyncWidget.labels.error;
      status.classList.add('is-error');
    } finally {
      submit.disabled = false;
    }
  });
})();
