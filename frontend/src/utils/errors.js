export function extractErrorMessage(error, fallback) {
  const data = error?.response?.data;
  if (!data) return fallback;

  if (data.errors) {
    const firstField = Object.values(data.errors)[0];
    if (Array.isArray(firstField) && firstField.length > 0) {
      return firstField[0];
    }
  }

  return data.message || fallback;
}
