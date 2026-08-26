export function formatDate(value) {
  if (!value) return '—';
  return value.slice(0, 10);
}

export function formatTime(value) {
  if (!value) return '—';
  return new Date(value).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}
