export function pageKey() {
  const el = document.getElementById('explorer-content')
  return el?.dataset?.page || 'home'
}

export function query() {
  return new URLSearchParams(window.location.search || '')
}

export function q(name, fb = '') {
  const v = query().get(name)
  return v === null ? fb : v
}

export function href(path, params = {}) {
  const qs = new URLSearchParams()
  Object.entries(params).forEach(([k, v]) => {
    if (v === null || v === undefined || v === '') return
    qs.set(k, String(v))
  })
  const s = qs.toString()
  return s ? `${path}?${s}` : path
}

export function explorerEntityPath(kind, id) {
  const cleanKind = String(kind || '').replace(/^\/+|\/+$/g, '')
  const value = String(id || '').trim()
  if (!cleanKind || !value) return '/explorer'
  return `/${cleanKind}/${encodeURIComponent(value)}`
}

export function routeParam(index, fallback = '') {
  const parts = String(window.location.pathname || '').split('/').filter(Boolean)
  const i = Number(index)
  if (!Number.isInteger(i) || i < 0 || i >= parts.length) return fallback
  try {
    return decodeURIComponent(parts[i])
  } catch (_) {
    return parts[i]
  }
}

export function entityIdFromPath(kind, fallback = '') {
  const cleanKind = String(kind || '').replace(/^\/+|\/+$/g, '')
  if (!cleanKind) return fallback
  const parts = String(window.location.pathname || '').split('/').filter(Boolean)
  const kindIndex = parts.findIndex((p) => p === cleanKind)
  if (kindIndex < 0 || kindIndex + 1 >= parts.length) return fallback
  try {
    return decodeURIComponent(parts[kindIndex + 1] || fallback)
  } catch (_) {
    return parts[kindIndex + 1] || fallback
  }
}
