// fetch wrapper for the PHP endpoints in backend/api (served at /api/<name>.php).
// Errors carry the HTTP status, the endpoint, and the start of the reply when PHP did not return JSON.
import { ref } from 'vue'

class ApiError extends Error {
  constructor(message, { status = 0, endpoint = '', fields = {}, rows = {} } = {}) {
    super(message)
    this.rows = rows // per-row field errors of activities-bulk
    this.status = status
    this.endpoint = endpoint
    this.fields = fields
  }
}

// set by useAuth: what to do when the session has expired (401)
export const onSignedOut = ref(() => {})

async function request(method, name, { params, body } = {}) {
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params || {})) if (v !== '' && v !== null && v !== undefined) qs.set(k, v)
  const endpoint = `/api/${name}.php${qs.size ? '?' + qs : ''}`
  let res
  try {
    res = await fetch(endpoint, {
      method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body === undefined ? undefined : JSON.stringify(body)
    })
  } catch {
    throw new ApiError('Cannot reach the server. Check that the WFP server window is still running.', { endpoint })
  }
  const text = await res.text()
  let data = null
  try { data = text ? JSON.parse(text) : null } catch {
    throw new ApiError(`The server sent an unexpected reply (HTTP ${res.status}, ${endpoint}): ${text.slice(0, 160)}`, { status: res.status, endpoint })
  }
  if (!res.ok) {
    if (res.status === 401 && name !== 'login') onSignedOut.value()
    throw new ApiError(data?.error || `Request failed (HTTP ${res.status}, ${endpoint})`, { status: res.status, endpoint, fields: data?.fields, rows: data?.rows })
  }
  return data
}

export function useApi() {
  return {
    get: (name, params) => request('GET', name, { params }),
    post: (name, body = {}, params) => request('POST', name, { body, params }),
    put: (name, body = {}, params) => request('PUT', name, { body, params }),
    del: (name, params) => request('DELETE', name, { params })
  }
}
