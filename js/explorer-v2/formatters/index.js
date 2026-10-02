export function esc(v) {
  return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;')
}

export function int(v, fb = 'N/A') {
  const n = Number(v)
  return Number.isFinite(n) ? n.toLocaleString(window.APP_LOCALE || undefined) : fb
}

export function id(v, head = 12, tail = 8) {
  const s = String(v || '')
  if (!s) return 'N/A'
  if (s.length <= head + tail + 3) return esc(s)
  return `<code class="explorer-code" title="${esc(s)}">${esc(s.slice(0, head))}...${esc(s.slice(-tail))}</code>`
}

export function dt(v) {
  if (!v) return 'N/A'
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return esc(v)
  return esc(d.toLocaleString(window.APP_LOCALE || undefined))
}

export function bytes(value, fb = 'N/A') {
  const n = Number(value)
  if (!Number.isFinite(n)) return fb
  const units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB']
  let scaled = Math.abs(n)
  let unitIndex = 0
  while (scaled >= 1000 && unitIndex < units.length - 1) {
    scaled /= 1000
    unitIndex += 1
  }
  const sign = n < 0 ? '-' : ''
  return `${sign}${scaled.toLocaleString(window.APP_LOCALE || undefined, { maximumFractionDigits: unitIndex === 0 ? 0 : 2 })} ${units[unitIndex]}`
}

export function hastings(v, fb = 'N/A') {
  const raw = String(v ?? '').trim()
  if (!/^\d+$/.test(raw)) return fb
  const H = BigInt(raw)
  const SC = 10n ** 24n
  const whole = H / SC
  const frac = H % SC
  const fracStr = frac > 0n ? '.' + frac.toString().padStart(24, '0').slice(0, 4).replace(/0+$/, '') : ''
  return `${whole.toLocaleString()}${fracStr === '.' ? '' : fracStr} SC`
}
