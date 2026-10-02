export class ApiError extends Error {
  constructor(status, message, path, body = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.path = path
    this.body = body
  }
}

const API_BASE = String(window.EXPLORER_API_BASE || '').replace(/\/+$/, '')

function url(path) {
  if (!API_BASE) throw new ApiError(0, 'Explorer API base URL is not configured.', path)
  return API_BASE + path
}

export async function getJson(path) {
  let res
  try {
    res = await fetch(url(path), { headers: { Accept: 'application/json' } })
  } catch (e) {
    throw new ApiError(0, 'Network error', path, e?.message || null)
  }
  const text = await res.text()
  let data = null
  if (text) {
    try { data = JSON.parse(text) } catch { data = text }
  }
  if (!res.ok) throw new ApiError(res.status, `API ${res.status}`, path, data)
  return data
}
