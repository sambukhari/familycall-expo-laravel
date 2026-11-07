export async function cameraRequest(path, body, options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 8000);
  try {
  const response = await fetch('/admin/cameras' + path, {
    method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
    signal: controller.signal,
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
    ...(body === undefined ? {} : {body: JSON.stringify(body)}), ...options,
  });
  const result = response.status === 204 ? {} : await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(result.message || 'Camera request failed (' + response.status + ').');
    error.status = response.status;
    throw error;
  }
  if (result.csrf_token) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = result.csrf_token;
  }
  return result;
  } finally {clearTimeout(timeout);}
}
