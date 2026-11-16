const token = document.querySelector('meta[name="csrf-token"]')?.content;

window.pulseFetch = (url, options = {}) => fetch(url, {
  ...options,
  headers: {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    ...options.headers,
  },
  credentials: 'same-origin',
});
