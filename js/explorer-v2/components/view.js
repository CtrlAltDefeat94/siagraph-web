import { esc } from '../formatters/index.js'

export function section(title, body) {
  return `<section class="mb-3"><h2 class="explorer-subheading">${esc(title)}</h2>${body}</section>`
}

export function renderLoading(message = 'Loading explorer data…') {
  return `<div class="explorer-state explorer-state--loading" role="status" aria-live="polite"><p>${esc(message)}</p></div>`
}

export function renderEmpty(title = 'No records found.', message = '') {
  return `<div class="explorer-state explorer-state--empty"><h2>${esc(title)}</h2>${message ? `<p>${esc(message)}</p>` : ''}</div>`
}

export function renderNotFound(entity = 'record') {
  return `<div class="explorer-state explorer-state--not-found"><h2>${esc(entity)} not found</h2><p>No matching ${esc(entity.toLowerCase())} was returned by the explorer API.</p></div>`
}

export function kv(obj) {
  const rows = Object.entries(obj || {}).map(([k, v]) => `<tr><th>${esc(k)}</th><td>${typeof v === 'string' ? esc(v) : esc(JSON.stringify(v))}</td></tr>`).join('')
  return `<div class="table-responsive"><table class="table table-dark table-clean explorer-kv-table"><tbody>${rows}</tbody></table></div>`
}

export function table(headers, rows) {
  const h = headers.map((x) => `<th>${esc(x)}</th>`).join('')
  const b = rows.map((r) => `<tr>${r.map((c, index) => `<td data-label="${esc(headers[index] || '')}">${c}</td>`).join('')}</tr>`).join('')
  return `<div class="table-responsive"><table class="table table-dark table-clean text-white explorer-table-compact"><thead><tr>${h}</tr></thead><tbody>${b || '<tr><td colspan="99" class="text-muted">No records.</td></tr>'}</tbody></table></div>`
}

export function renderError(err) {
  const message = err?.message || 'Request failed'
  return `<div class="explorer-state explorer-state--error" role="alert"><h2>Explorer request failed</h2><p>${esc(message)}</p><button type="button" class="btn btn-brand" data-explorer-retry>Retry</button></div>`
}

export function copyButton(value, className = 'explorer-copy-button', label = 'Copy') {
  const raw = String(value || '').trim()
  if (!raw) return ''
  return `<button class="${esc(className)}" type="button" data-copy-value="${esc(raw)}" aria-label="Copy identifier" title="Copy identifier">${esc(label)}</button>`
}

export function rawJson(title, payload, open = false) {
  const state = open ? ' open' : ''
  return `<details class="explorer-raw"${state}><summary>${esc(title)}</summary><pre>${esc(JSON.stringify(payload, null, 2))}</pre></details>`
}
