import { getJson } from '../services/api.js'
import { q, href, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { id, int, hastings, dt, esc } from '../formatters/index.js'
import { copyButton, renderNotFound } from '../components/view.js'

function countRows(list) {
  return Array.isArray(list) ? list.length : 0
}

// Renders the full id; CSS ellipsizes it only when the row genuinely lacks space.
function idText(raw) {
  return `<span class="addrx-id-text" title="${esc(raw)}">${esc(raw)}</span>`
}

function rowLink(idValue) {
  const raw = String(idValue || '').trim()
  if (!raw) return ''
  return `<span class="addrx-id-cell"><a href="${explorerEntityPath('search', raw)}">${idText(raw)}</a>${copyButton(raw, 'addrx-inline-copy')}</span>`
}

// Same truncated-id + inline copy treatment as ledger rows, but links to the output entity page.
function outputRowLink(idValue) {
  const raw = String(idValue || '').trim()
  if (!raw) return ''
  return `<span class="addrx-id-cell"><a href="${explorerEntityPath('output', raw)}">${idText(raw)}</a>${copyButton(raw, 'addrx-inline-copy')}</span>`
}

function renderEmptyState(message) {
  return `<div class="addrx-empty">${esc(message)}</div>`
}

function readCurrency() {
  const m = String(document.cookie || '').match(/(?:^|;\s*)currency=([^;]+)/i)
  const c = m ? decodeURIComponent(m[1]).toLowerCase() : 'eur'
  return c === 'usd' ? 'usd' : 'eur'
}

function parseHastingsToSC(value) {
  const raw = String(value ?? '').trim()
  if (!/^\d+$/.test(raw)) return null
  const H = BigInt(raw)
  const SC = 10n ** 24n
  const whole = Number(H / SC)
  const frac = Number(H % SC) / 1e24
  return whole + frac
}

function parseHastingsBigInt(value) {
  const raw = String(value ?? '').trim()
  if (!/^\d+$/.test(raw)) return null
  try {
    return BigInt(raw)
  } catch (_) {
    return null
  }
}

function getRateFromScalarPayload(payload) {
  if (typeof payload === 'number' && Number.isFinite(payload)) return payload
  if (payload && typeof payload === 'object') {
    if (typeof payload?.rate === 'number' && Number.isFinite(payload.rate)) return payload.rate
    if (typeof payload?.data?.rate === 'number' && Number.isFinite(payload.data.rate)) return payload.data.rate
  }
  return null
}

function fmtFiatFromHastings(hastingsValue, rate, currency) {
  const sc = parseHastingsToSC(hastingsValue)
  if (sc === null || !Number.isFinite(rate)) return ''
  const value = sc * rate
  const cur = String(currency || 'eur').toUpperCase()
  return `${cur} ${value.toLocaleString(window.APP_LOCALE || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function isoDateOnly(timestamp) {
  if (!timestamp) return null
  const d = new Date(timestamp)
  if (!Number.isFinite(d.getTime())) return null
  return d.toISOString().slice(0, 10)
}

async function fetchDailyRates(minDate, maxDate) {
  if (!minDate || !maxDate) return []
  try {
    const start = encodeURIComponent(`${minDate}T00:00:00Z`)
    const end = encodeURIComponent(`${maxDate}T00:00:00Z`)
    const res = await fetch(`/api/v1/daily/exchange_rate?start=${start}&end=${end}`, { headers: { Accept: 'application/json' } })
    if (!res.ok) return []
    const data = await res.json().catch(() => null)
    return Array.isArray(data) ? data : []
  } catch (_) {
    return []
  }
}

function buildDailyRateIndex(entries, currency) {
  const cur = String(currency || 'eur').toLowerCase()
  return (Array.isArray(entries) ? entries : [])
    .map((e) => ({ date: String(e?.date || '').slice(0, 10), rate: Number(e?.[cur]) }))
    .filter((e) => e.date && Number.isFinite(e.rate))
    .sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0))
}

// Historical events use the daily average rate on their own date rather than today's price.
function rateForTimestamp(index, timestamp) {
  if (!Array.isArray(index) || !index.length) return null
  const key = isoDateOnly(timestamp)
  if (!key) return null
  let lo = 0
  let hi = index.length - 1
  let match = -1
  while (lo <= hi) {
    const mid = (lo + hi) >> 1
    if (index[mid].date <= key) {
      match = mid
      lo = mid + 1
    } else {
      hi = mid - 1
    }
  }
  return match >= 0 ? index[match].rate : index[0].rate
}

function infoTip(text) {
  const t = esc(text)
  return `<span class="addrx-info-tip" tabindex="0" role="img" aria-label="${t}" title="${t}">ⓘ</span>`
}

function numberLikeToBigInt(v) {
  if (typeof v === 'bigint') return v
  if (typeof v === 'number' && Number.isFinite(v) && v >= 0) return BigInt(Math.trunc(v))
  const raw = String(v ?? '').trim()
  if (!/^\d+$/.test(raw)) return null
  try {
    return BigInt(raw)
  } catch (_) {
    return null
  }
}

function signedHastings(value, fb = '0 SC') {
  let bi = value
  if (typeof bi !== 'bigint') bi = numberLikeToBigInt(bi)
  if (bi === null) return fb
  const neg = bi < 0n
  const abs = neg ? -bi : bi
  const raw = abs.toString()
  const h = hastings(raw, fb)
  if (h === fb) return fb
  if (abs === 0n) return h
  return `${neg ? '-' : '+'}${h}`
}

function signedFiatFromHastings(value, rate, currency) {
  if (typeof value !== 'bigint') return ''
  const neg = value < 0n
  const abs = neg ? -value : value
  const fiat = fmtFiatFromHastings(abs.toString(), rate, currency)
  if (!fiat || abs === 0n) return ''
  return `${neg ? '-' : '+'}${fiat}`
}

function signedSf(value) {
  if (typeof value !== 'bigint' || value === 0n) return ''
  const neg = value < 0n
  const abs = neg ? -value : value
  return `${neg ? '-' : '+'}${int(abs.toString())} SF`
}

function extractTxFromEvent(event) {
  const data = event?.data
  if (data && typeof data === 'object' && data.transaction && typeof data.transaction === 'object') return data.transaction
  if (data && typeof data === 'object') return data
  return null
}

function eventAddressFlow(event, address) {
  const addr = String(address || '').trim().toLowerCase()
  let sc = 0n
  let sf = 0n
  const type = String(event?.type || '')
  const tx = extractTxFromEvent(event)

  if (type === 'v1Transaction' || type === 'v2Transaction') {
    if (!(tx && typeof tx === 'object')) return { amountSc: sc, amountSf: sf }
    const toKey = (a) => String(a || '').trim().toLowerCase()
    const addToMap = (map, a, delta) => {
      const k = toKey(a)
      if (!k || typeof delta !== 'bigint') return
      map.set(k, (map.get(k) || 0n) + delta)
    }
    const scByAddress = new Map()
    const sfByAddress = new Map()
    const scInputs = Array.isArray(tx.siacoinInputs) ? tx.siacoinInputs : []
    const scOutputs = Array.isArray(tx.siacoinOutputs) ? tx.siacoinOutputs : []
    const sfInputs = Array.isArray(tx.siafundInputs) ? tx.siafundInputs : []
    const sfOutputs = Array.isArray(tx.siafundOutputs) ? tx.siafundOutputs : []

    scInputs.forEach((inp) => {
      const p = inp?.parent?.siacoinOutput || inp?.siacoinOutput || inp
      const a = p?.address || inp?.address || inp?.parent?.address
      const v = numberLikeToBigInt(p?.value)
      if (v !== null) addToMap(scByAddress, a, -v)
    })
    scOutputs.forEach((out) => {
      const o = out?.siacoinOutput || out
      const a = o?.address || out?.address
      const v = numberLikeToBigInt(o?.value)
      if (v !== null) addToMap(scByAddress, a, v)
    })
    sfInputs.forEach((inp) => {
      const p = inp?.parent?.siafundOutput || inp?.siafundOutput || inp
      const a = p?.address || inp?.address || inp?.parent?.address
      const v = numberLikeToBigInt(p?.value)
      if (v !== null) addToMap(sfByAddress, a, -v)
    })
    sfOutputs.forEach((out) => {
      const o = out?.siafundOutput || out
      const a = o?.address || out?.address
      const v = numberLikeToBigInt(o?.value)
      if (v !== null) addToMap(sfByAddress, a, v)
    })
    sc = scByAddress.get(addr) || 0n
    sf = sfByAddress.get(addr) || 0n

    // For some v2 address events, relevance comes via contract resolution
    // payouts embedded in fileContractResolutions rather than direct tx IO.
    if (sc === 0n && sf === 0n && type === 'v2Transaction') {
      const fr = Array.isArray(tx.fileContractResolutions) ? tx.fileContractResolutions : []
      fr.forEach((resEntry) => {
        const resolution = resEntry?.resolution || {}
        const payoutCandidates = [resolution?.finalHostOutput, resolution?.finalRenterOutput]
        payoutCandidates.forEach((out) => {
          const a = String(out?.address || '').trim().toLowerCase()
          const v = numberLikeToBigInt(out?.value)
          if (a === addr && v !== null) sc += v
        })
      })
    }

    return { amountSc: sc, amountSf: sf }
  }

  // For payout/resolution-like events, explored already emits the relevant element
  // for this address; use it directly like the reference explorer does.
  const sco = event?.data?.siacoinElement?.siacoinOutput
  if (sco) {
    const v = numberLikeToBigInt(sco?.value)
    if (v !== null) sc += v
  }
  const sfo = event?.data?.siafundElement?.siafundOutput
  if (sfo) {
    const v = numberLikeToBigInt(sfo?.value)
    if (v !== null) sf += v
  }

  return { amountSc: sc, amountSf: sf }
}

function formatEventAmountCell(row, currentRate, histRate, currency) {
  const sc = typeof row?.amountSc === 'bigint' ? row.amountSc : 0n
  const sf = typeof row?.amountSf === 'bigint' ? row.amountSf : 0n
  const scText = signedHastings(sc)
  const sfText = signedSf(sf)
  const absSc = sc < 0n ? -sc : sc
  const effectiveRate = Number.isFinite(histRate) ? histRate : currentRate
  const fiatValue = absSc > 0n ? fmtFiatFromHastings(absSc.toString(), effectiveRate, currency) : ''
  const fiat = fiatValue ? `≈ ${sc < 0n ? '-' : ''}${fiatValue}` : ''
  return `<div class="addrx-amount-cell"><div class="addrx-amount-primary">${scText}</div>${fiat ? `<div class="addrx-amount-sub">${fiat}</div>` : ''}${sfText ? `<div class="addrx-amount-sub">${sfText}</div>` : ''}</div>`
}

function formatEventAmount(row, currentRate, histRate, currency) {
  const amount = formatEventAmountCell(row, currentRate, histRate, currency)
  return String(row?.type || '').toLowerCase().includes('transaction')
    ? `<div class="addrx-net-amount"><span class="addrx-amount-kicker">Net</span>${amount}</div>`
    : amount
}

function compactTypeLabel(type) {
  const t = String(type || '').toLowerCase()
  if (t.includes('transaction')) return 'TX'
  if (t.includes('contractresolution')) return 'CR'
  if (t.includes('contractformation')) return 'CF'
  if (t.includes('contract')) return 'CT'
  if (t.includes('output')) return 'OUT'
  return (String(type || 'EV').replace(/[^a-z0-9]/gi, '').slice(0, 2) || 'EV').toUpperCase()
}

function eventTypeLabel(type) {
  const t = String(type || '').toLowerCase()
  if (t.includes('contractformation')) return 'Contract formation'
  if (t.includes('contractresolution')) return 'Contract resolution'
  if (t.includes('transaction')) return 'Transaction'
  if (t.includes('output')) return 'Siacoin output'
  return String(type || 'Event')
}

function eventDescription(row) {
  const type = String(row?.type || '').toLowerCase()
  const sc = typeof row?.amountSc === 'bigint' ? row.amountSc : 0n
  const sf = typeof row?.amountSf === 'bigint' ? row.amountSf : 0n
  const hasIncoming = sc > 0n || sf > 0n
  const hasOutgoing = sc < 0n || sf < 0n
  if (hasIncoming && !hasOutgoing) return 'Received'
  if (hasOutgoing && !hasIncoming) return 'Spent'
  if (type.includes('transaction')) return 'Transaction'
  if (type.includes('contractresolution')) return 'Contract resolution'
  if (type.includes('contractformation')) return 'Contract formation'
  if (type.includes('output')) return 'Output'
  return 'Address event'
}

function eventTypeGroup(row) {
  const type = String(row?.type || '').toLowerCase()
  if (type.includes('contractresolution')) return 'contract-resolutions'
  if (type.includes('transaction') || type.includes('output')) return 'transfers'
  return 'other'
}

// Maturity delays only apply to siacoin outputs and contract resolution payouts, not ordinary transfers.
function maturityApplies(row) {
  const type = String(row?.type || '').toLowerCase()
  return type.includes('output') || type.includes('contractresolution')
}

function eventStatusToken(row) {
  if (row?.pending) return 'pending'
  if (!maturityApplies(row)) return 'mature'
  const maturityHeight = Number(row?.maturityHeight ?? row?.raw?.maturityHeight ?? row?.raw?.maturity_height)
  const tipHeight = Number(row?.tipHeight)
  if (Number.isFinite(maturityHeight) && maturityHeight > 0 && Number.isFinite(tipHeight) && tipHeight > 0) {
    return maturityHeight > tipHeight ? 'immature' : 'mature'
  }
  return 'mature'
}

function eventLocation(row) {
  const height = row?.raw?.index?.height ?? row?.raw?.height ?? row?.raw?.blockHeight
  const block = height === undefined || height === null
    ? `<span>${row?.pending ? 'Pending block' : 'UTXO'}</span>`
    : `<span>Block ${int(height)}</span>`
  return `<span class="addrx-location">${block}</span>`
}

function eventStatus(row) {
  const token = eventStatusToken(row)
  if (token === 'pending') return '<span class="addrx-pill">Pending</span>'
  if (!maturityApplies(row)) {
    const confirmations = Number(row?.confirmations || 0)
    return confirmations > 0 ? `${int(confirmations)} confirmations` : ''
  }
  const maturityHeight = Number(row?.maturityHeight ?? row?.raw?.maturityHeight ?? row?.raw?.maturity_height)
  const tipHeight = Number(row?.tipHeight)
  if (token === 'immature') {
    return `<span class="addrx-state-stack"><span class="addrx-pill addrx-pill--immature">Immature</span><small>${int(maturityHeight)} · ${int(maturityHeight - tipHeight)} blocks</small></span>`
  }
  if (Number.isFinite(maturityHeight) && maturityHeight > 0) {
    return `<span class="addrx-state-stack"><span class="addrx-pill addrx-pill--mature">Mature</span><small>Since block ${int(maturityHeight)}</small></span>`
  }
  const confirmations = Number(row?.confirmations || 0)
  return confirmations > 0 ? `${int(confirmations)} confirmations` : ''
}

function relativeTime(timestamp) {
  if (!timestamp) return ''
  const ts = new Date(timestamp).getTime()
  if (!Number.isFinite(ts)) return dt(timestamp)
  const diffMs = Date.now() - ts
  const diffMin = Math.max(0, Math.floor(diffMs / 60000))
  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHr = Math.floor(diffMin / 60)
  if (diffHr < 24) return `${diffHr}h ago`
  const diffDay = Math.floor(diffHr / 24)
  return `${diffDay}d ago`
}

function renderEventsTable(rows, rate, dailyRateIndex, currency) {
  if (!rows.length) return renderEmptyState('No events match the active filters.')
  const items = rows.map((r) => {
    const metadata = [
      rowLink(r.id),
      r.type ? `<span class="addrx-event-kind-label">${esc(eventTypeLabel(r.type))}</span>` : '',
      eventLocation(r),
      relativeTime(r.timestamp) ? `<span class="addrx-event-time">${relativeTime(r.timestamp)}</span>` : '',
    ].filter(Boolean).join('<span class="addrx-meta-separator" aria-hidden="true">·</span>')
    const histRate = rateForTimestamp(dailyRateIndex, r.timestamp)
    return `
      <article class="addrx-event-item ${r.pending ? 'addrx-event-item--pending' : ''}${r.immature ? ' addrx-event-item--immature' : ''}">
        <div class="addrx-event-kind" aria-hidden="true">${compactTypeLabel(r.type)}</div>
        <div class="addrx-event-main">
          <div class="addrx-event-lead">
            <div class="addrx-event-copy">
              <div class="addrx-event-title">${esc(eventDescription(r))}</div>
              <div class="addrx-event-meta">${metadata}</div>
            </div>
          </div>
        </div>
        <div class="addrx-event-amount">${formatEventAmount(r, rate, histRate, currency)}</div>
        <div class="addrx-event-status">${eventStatus(r)}</div>
      </article>
    `
  }).join('')

  return `<div class="addrx-event-feed" aria-label="Address activity">${items}</div>`
}

function renderScUtxoFeed(utxos, rate, currency, tipHeight) {
  if (!utxos.length) return ''
  const items = utxos.map((o) => {
    const hv = o?.siacoinOutput?.value || o?.value
    const fiatValue = fmtFiatFromHastings(hv, rate, currency)
    const fiat = fiatValue ? `≈ ${fiatValue}` : ''
    const maturityHeight = Number(o?.maturityHeight || o?.maturity_height || 0)
    const isImmature = maturityHeight > 0 && Number.isFinite(tipHeight) && tipHeight > 0 && maturityHeight > tipHeight
    // Every siacoin UTXO is either immature or mature; unlike ordinary transactions, this column is never empty here.
    const status = isImmature
      ? `<span class="addrx-state-stack"><span class="addrx-pill addrx-pill--immature">Immature</span><small>${int(maturityHeight)} · ${int(maturityHeight - tipHeight)} blocks</small></span>`
      : (maturityHeight > 0
        ? `<span class="addrx-state-stack"><span class="addrx-pill addrx-pill--mature">Mature</span><small>Since block ${int(maturityHeight)}</small></span>`
        : '<span class="addrx-pill addrx-pill--mature">Mature</span>')
    return `
      <article class="addrx-event-item${isImmature ? ' addrx-event-item--immature' : ''}">
        <div class="addrx-event-kind" aria-hidden="true">SC</div>
        <div class="addrx-event-main">
          <div class="addrx-event-lead">
            <div class="addrx-event-copy">
              <div class="addrx-event-title">Unspent output</div>
              <div class="addrx-event-meta">${outputRowLink(o.id)}</div>
            </div>
          </div>
        </div>
        <div class="addrx-event-amount"><div class="addrx-amount-cell"><div class="addrx-amount-primary">${hastings(hv)}</div>${fiat ? `<div class="addrx-amount-sub">${fiat}</div>` : ''}</div></div>
        <div class="addrx-event-status">${status}</div>
      </article>
    `
  }).join('')
  return `<div class="addrx-event-feed" aria-label="Siacoin UTXOs">${items}</div>`
}

function renderSfUtxoFeed(utxos) {
  if (!utxos.length) return ''
  const items = utxos.map((o) => `
    <article class="addrx-event-item">
      <div class="addrx-event-kind" aria-hidden="true">SF</div>
      <div class="addrx-event-main">
        <div class="addrx-event-lead">
          <div class="addrx-event-copy">
            <div class="addrx-event-title">Unspent output</div>
            <div class="addrx-event-meta">${outputRowLink(o.id)}</div>
          </div>
        </div>
      </div>
      <div class="addrx-event-amount"><div class="addrx-amount-cell"><div class="addrx-amount-primary">${int(o?.siafundOutput?.value || o?.value)} SF</div></div></div>
      <div class="addrx-event-status"></div>
    </article>
  `).join('')
  return `<div class="addrx-event-feed" aria-label="Siafund UTXOs">${items}</div>`
}

export async function renderAddress() {
  const addr = String(q('id') || entityIdFromPath('address', '')).trim()
  if (!addr) return renderNotFound('Address')

  const offset = Math.max(0, Number(q('offset', '0')) || 0)
  const limit = Math.max(1, Number(q('limit', '25')) || 25)
  const requestedFilter = String(q('filter', 'transactions')).toLowerCase()
  const filter = ['transactions', 'utxos'].includes(requestedFilter) ? requestedFilter : 'transactions'
  const requestedType = String(q('type', 'all')).toLowerCase()
  const typeFilter = ['all', 'transfers', 'contract-resolutions', 'other'].includes(requestedType) ? requestedType : 'all'
  const requestedStatus = String(q('status', 'all')).toLowerCase()
  const statusFilter = ['all', 'mature', 'immature', 'pending'].includes(requestedStatus) ? requestedStatus : 'all'
  const sort = 'newest'

  const currency = readCurrency()
  const [balance, events, unconfirmed, scUtxosPage, sfUtxosPage, scUtxosAll, sfUtxosAll, scalarRatePayload, tip] = await Promise.all([
    getJson(`/addresses/${encodeURIComponent(addr)}/balance`),
    getJson(`/addresses/${encodeURIComponent(addr)}/events?offset=${offset}&limit=${limit}`),
    getJson(`/addresses/${encodeURIComponent(addr)}/events/unconfirmed`),
    getJson(`/addresses/${encodeURIComponent(addr)}/utxos/siacoin?offset=${offset}&limit=${limit}`),
    getJson(`/addresses/${encodeURIComponent(addr)}/utxos/siafund?offset=${offset}&limit=${limit}`),
    // Wider fetch for table rendering + unlock detection so UTXOs are not tied to event page slice.
    getJson(`/addresses/${encodeURIComponent(addr)}/utxos/siacoin?offset=0&limit=500`).catch(() => []),
    getJson(`/addresses/${encodeURIComponent(addr)}/utxos/siafund?offset=0&limit=500`).catch(() => []),
    getJson(`/exchange-rate/siacoin/${encodeURIComponent(currency)}`).catch(() => null),
    getJson('/consensus/tip').catch(() => null),
  ])

  const eventCount = countRows(events)
  const unconfirmedCount = countRows(unconfirmed)
  const scUtxos = Array.isArray(scUtxosAll) && scUtxosAll.length ? scUtxosAll : (Array.isArray(scUtxosPage) ? scUtxosPage : [])
  const sfUtxos = Array.isArray(sfUtxosAll) && sfUtxosAll.length ? sfUtxosAll : (Array.isArray(sfUtxosPage) ? sfUtxosPage : [])
  const scCount = countRows(scUtxos)
  const sfCount = countRows(sfUtxos)
  const rate = getRateFromScalarPayload(scalarRatePayload)

  const confirmedRows = (Array.isArray(events) ? events : []).map((e) => ({
    amountSc: 0n,
    amountSf: 0n,
    id: e?.id,
    type: String(e?.type || ''),
    timestamp: e?.timestamp,
    confirmations: Number(e?.confirmations || 0),
    pending: false,
    raw: e,
  }))
  const pendingRows = (Array.isArray(unconfirmed) ? unconfirmed : []).map((e) => ({
    amountSc: 0n,
    amountSf: 0n,
    id: e?.id,
    type: String(e?.type || ''),
    timestamp: e?.timestamp,
    confirmations: 0,
    pending: true,
    raw: e,
  }))

  const detailCandidates = [...confirmedRows, ...pendingRows].filter((r) => {
    const ev = r?.raw
    if (!ev || typeof ev !== 'object') return false
    if (!r?.id) return false
    return !(ev.data && typeof ev.data === 'object')
  })
  const uniqueDetailIds = Array.from(new Set(detailCandidates.map((r) => String(r.id || '')).filter(Boolean)))
  const detailedEvents = await Promise.all(
    uniqueDetailIds.map((eid) => getJson(`/events/${encodeURIComponent(eid)}`).catch(() => null)),
  )
  const detailMap = new Map()
  detailedEvents.forEach((ev) => {
    if (ev && typeof ev === 'object' && ev.id) detailMap.set(String(ev.id), ev)
  })
  const hydrateAndCompute = (row) => {
    const detailed = detailMap.get(String(row?.id || ''))
    const raw = detailed || row.raw
    const flow = eventAddressFlow(raw, addr)
    return { ...row, ...flow, raw }
  }
  const computedConfirmedRows = confirmedRows.map(hydrateAndCompute)
  const computedPendingRows = pendingRows.map(hydrateAndCompute)

  const mergedById = new Map()
  ;[...computedConfirmedRows, ...computedPendingRows].forEach((r) => {
    const key = String(r.id || `${r.type}:${r.timestamp}`)
    if (!mergedById.has(key) || (!r.pending && mergedById.get(key).pending)) mergedById.set(key, r)
  })

  let mergedEvents = Array.from(mergedById.values())
  mergedEvents.sort((a, b) => {
    const av = new Date(a.timestamp || 0).getTime()
    const bv = new Date(b.timestamp || 0).getTime()
    return sort === 'oldest' ? av - bv : bv - av
  })

  const unconfirmedRows = computedPendingRows
    .sort((a, b) => {
      const av = new Date(a.timestamp || 0).getTime()
      const bv = new Date(b.timestamp || 0).getTime()
      return bv - av
    })
    .map((e) => [rowLink(e.id), e.type || '', e.timestamp ? dt(e.timestamp) : ''])

  const tipHeight = Number(tip?.height || 0)
  const immatureUnlockRows = (Array.isArray(scUtxos) ? scUtxos : [])
    .filter((o) => {
      const mh = Number(o?.maturityHeight || o?.maturity_height || 0)
      if (!Number.isFinite(mh) || mh <= 0) return false
      const hv = o?.siacoinOutput?.value || o?.value
      const hb = parseHastingsBigInt(hv)
      if (hb === null || hb <= 0n) return false
      // Only immature if maturity is still in the future.
      return mh > tipHeight
    })
    .map((o) => {
      const mh = Number(o?.maturityHeight || o?.maturity_height || 0)
      const hv = o?.siacoinOutput?.value || o?.value
      const fiat = fmtFiatFromHastings(hv, rate, currency)
      const amount = fiat ? `${hastings(hv)} <span class="addrx-fiat">(${fiat})</span>` : hastings(hv)
      const remaining = Math.max(0, mh - tipHeight)
      return [`<a href="${explorerEntityPath('output', o.id)}">${id(o.id)}</a>`, `${int(mh)} <span class="addrx-fiat">(+${int(remaining)} blocks)</span>`, amount]
    })

  const immatureEventRows = (Array.isArray(scUtxos) ? scUtxos : [])
    .filter((o) => {
      const mh = Number(o?.maturityHeight || o?.maturity_height || 0)
      if (!Number.isFinite(mh) || mh <= tipHeight) return false
      const hv = o?.siacoinOutput?.value || o?.value
      const hb = parseHastingsBigInt(hv)
      return hb !== null && hb > 0n
    })
    .map((o) => {
      const mh = Number(o?.maturityHeight || o?.maturity_height || 0)
      const hv = o?.siacoinOutput?.value || o?.value
      const hb = parseHastingsBigInt(hv) || 0n
      const remaining = Math.max(0, mh - tipHeight)
      return {
        amountSc: hb,
        amountSf: 0n,
        id: o?.id || '',
        type: 'Siacoin Output',
        timestamp: null,
        confirmations: 0,
        pending: false,
        immature: true,
        when: `Matures ${int(mh)} (+${int(remaining)})`,
        raw: o,
      }
    })
    .sort((a, b) => {
      const ah = Number(a?.raw?.maturityHeight || a?.raw?.maturity_height || 0)
      const bh = Number(b?.raw?.maturityHeight || b?.raw?.maturity_height || 0)
      return sort === 'oldest' ? ah - bh : bh - ah
    })

  const prevOffset = Math.max(0, offset - limit)
  const nextOffset = offset + limit
  const baseAddressPath = explorerEntityPath('address', addr)
  const rangeStart = eventCount > 0 ? offset + 1 : 0
  const rangeEnd = offset + eventCount
  const commonRouteState = { limit, filter, type: typeFilter === 'all' ? undefined : typeFilter, status: statusFilter === 'all' ? undefined : statusFilter }

  const pager = `<div class="explorer-pager"><a class="btn btn-sm btn-outline-light ${offset === 0 ? 'disabled' : ''}" href="${href(baseAddressPath, { offset: prevOffset, ...commonRouteState })}">Previous</a><a class="btn btn-sm btn-outline-light" href="${href(baseAddressPath, { offset: nextOffset, ...commonRouteState })}">Next</a></div><div class="addrx-pageinfo">Showing ${int(rangeStart)}-${int(rangeEnd)} confirmed events on this page</div>`

  const showImmatureOnThisPage = offset === 0
  let eventsWithImmature = mergedEvents
  if (showImmatureOnThisPage) {
    const immatureIds = new Set(immatureEventRows.map((r) => String(r?.id || '')).filter(Boolean))
    const filteredConfirmed = mergedEvents.filter((e) => !immatureIds.has(String(e?.id || '')))
    eventsWithImmature = [...immatureEventRows, ...filteredConfirmed]
  }
  eventsWithImmature = eventsWithImmature.map((row) => ({ ...row, tipHeight }))
  if (typeFilter !== 'all') eventsWithImmature = eventsWithImmature.filter((row) => eventTypeGroup(row) === typeFilter)
  if (statusFilter !== 'all') eventsWithImmature = eventsWithImmature.filter((row) => eventStatusToken(row) === statusFilter)

  let dailyRateIndex = []
  if (filter === 'transactions') {
    const stampedDates = eventsWithImmature.map((r) => isoDateOnly(r.timestamp)).filter(Boolean).sort()
    if (stampedDates.length) {
      const dailyRates = await fetchDailyRates(stampedDates[0], stampedDates[stampedDates.length - 1])
      dailyRateIndex = buildDailyRateIndex(dailyRates, currency)
    }
  }

  const primaryTabs = [['transactions', 'Transactions'], ['utxos', 'UTXOs']]
  const filterForm = `<nav class="addrx-filter" aria-label="Address explorer views">${primaryTabs.map(([value, label]) => `<a class="addrx-filter-link${filter === value ? ' is-active' : ''}" href="${href(baseAddressPath, { limit, filter: value })}"${filter === value ? ' aria-current="page"' : ''}>${label}</a>`).join('')}</nav>`

  const typeOptions = [['all', 'All'], ['transfers', 'Transfers'], ['contract-resolutions', 'Contract resolutions'], ['other', 'Other']]
  const statusOptions = [['all', 'All'], ['mature', 'Mature'], ['immature', 'Immature'], ['pending', 'Pending']]
  const buildFilterSelect = (label, name, options, current) => {
    const opts = options.map(([value, text]) => {
      const params = {
        limit,
        filter,
        type: name === 'type' ? (value === 'all' ? undefined : value) : (typeFilter === 'all' ? undefined : typeFilter),
        status: name === 'status' ? (value === 'all' ? undefined : value) : (statusFilter === 'all' ? undefined : statusFilter),
      }
      return `<option value="${href(baseAddressPath, params)}"${current === value ? ' selected' : ''}>${esc(text)}</option>`
    }).join('')
    return `<label class="addrx-compact-filter"><span>${esc(label)}</span><select data-filter-nav aria-label="${esc(label)} filter">${opts}</select></label>`
  }

  const scopedStyle = `
  <style>
    .addrx-card{background:transparent;border:0;border-radius:0;padding:0}
    .addrx-hero{background:transparent;border-bottom:1px solid var(--sg-border-muted);padding:0 0 1.25rem}
    .addrx-hero-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .addrx-title{margin:.05rem 0 0;color:var(--sg-text-strong);font-size:1.25rem;line-height:1.1}
    .addrx-id{padding:.4rem .55rem;border:1px solid var(--sg-border-control);border-radius:8px;background:var(--sg-control-bg);margin-top:.35rem}
    .addrx-id-label{display:block;color:var(--sg-text-faint);font-size:.72rem;margin-bottom:.2rem}
    .addrx-id-row{display:flex;justify-content:space-between;align-items:center;gap:10px}
    .addrx-id-value{min-width:0;overflow-wrap:anywhere;word-break:break-word}
    .addrx-copy[data-copy-value]{width:1.6rem;height:1.6rem;padding:0;border:1px solid transparent;background:transparent;color:var(--sg-text-muted);border-radius:6px;font-size:0;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:background 120ms ease,border-color 120ms ease,color 120ms ease}
    .addrx-copy[data-copy-value]::before{content:'⧉';font-size:.85rem}
    .addrx-copy[data-copy-value]:hover,.addrx-copy[data-copy-value]:focus-visible{background:var(--sg-control-bg);border-color:var(--sg-border-control);color:var(--sg-text-strong)}
    .addrx-copy[data-copy-value][data-state="done"]::before{content:'✓ '}
    .addrx-copy[data-copy-value][data-state="done"]{width:auto;padding:0 .5rem;font-size:.72rem;border-color:rgba(74,222,128,.45);color:#bbf7d0}
    .addrx-copy[data-copy-value][data-state="error"]{width:auto;padding:0 .5rem;font-size:.72rem;border-color:rgba(248,113,113,.5);color:#fca5a5}
    .addrx-copy[data-copy-value][data-state="error"]::before{content:'! '}
    .addrx-inline-copy[data-copy-value]{width:auto;height:auto;padding:.05rem .2rem;display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent;background:transparent;color:var(--sg-text-faint);border-radius:4px;font-size:0;line-height:1;cursor:pointer}
    .addrx-inline-copy[data-copy-value]::before{content:'⧉';font-size:.66rem}
    .addrx-inline-copy[data-copy-value]:hover,.addrx-inline-copy[data-copy-value]:focus-visible{background:var(--sg-control-bg);border-color:var(--sg-border-control);color:var(--sg-text-strong)}
    .addrx-inline-copy[data-copy-value][data-state="done"]::before{content:'✓'}
    .addrx-inline-copy[data-copy-value][data-state="done"]{background:var(--sg-control-bg);border-color:rgba(74,222,128,.55);color:#bbf7d0}
    .addrx-inline-copy[data-copy-value][data-state="error"]::before{content:'!'}
    .addrx-inline-copy[data-copy-value][data-state="error"]{background:var(--sg-control-bg);border-color:rgba(248,113,113,.5);color:#fca5a5}
    .addrx-id-cell{display:inline-flex;align-items:center;gap:2px;min-width:0}
    .addrx-id-cell a{min-width:0;overflow:hidden}
    .addrx-id-text{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom}
    .addrx-id-cell .addrx-inline-copy{opacity:.5;transition:opacity 120ms ease}
    .addrx-id-cell:hover .addrx-inline-copy,.addrx-id-cell:focus-within .addrx-inline-copy{opacity:1}
    .addrx-info-tip{display:inline-flex;align-items:center;justify-content:center;width:1rem;height:1rem;margin-left:.3rem;border-radius:50%;color:var(--sg-text-faint);font-size:.72rem;line-height:1;cursor:help;vertical-align:middle}
    .addrx-info-tip:hover,.addrx-info-tip:focus-visible{color:var(--sg-text-strong)}
    .addrx-subfilters{display:flex;align-items:center;gap:.9rem;margin:0 0 .65rem;flex-wrap:wrap}
    .addrx-compact-filter{display:inline-flex;align-items:center;gap:.4rem;color:var(--sg-text-muted);font-size:.78rem}
    .addrx-compact-filter select{background:var(--sg-control-bg);color:var(--sg-text-strong);border:1px solid var(--sg-border-control);border-radius:6px;padding:.25rem .5rem;font-size:.78rem}
    .addrx-subfilters-count{margin-left:auto;color:var(--sg-text-muted);font-size:.8rem;white-space:nowrap}
    .addrx-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem;margin-top:14px;padding:.85rem 1rem;border:1px solid var(--sg-card-border);border-radius:var(--sg-radius-lg);background:var(--sg-surface-1);box-shadow:var(--sg-surface-1-edge)}
    .addrx-kpi{padding:.35rem .75rem;border:0;border-left:2px solid var(--sg-border-accent);border-radius:0;background:transparent}
    .addrx-kpi small{color:var(--sg-text-muted);text-transform:uppercase;font-size:.69rem;letter-spacing:.03em}
    .addrx-kpi b{display:block;color:var(--sg-text-strong);font-size:1.15rem;margin-top:4px;line-height:1.15}
    .addrx-meta{margin-top:12px;color:var(--sg-text-muted);font-size:.84rem;line-height:1.45}
    .addrx-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:9px}
    .addrx-head h3{margin:0;color:var(--sg-heading-accent);font-size:1.06rem}
    .addrx-head span{color:var(--sg-text-muted);font-size:.8rem}
    .addrx-subhead{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:2px 0 6px}
    .addrx-filter{display:flex;align-items:center;gap:.35rem;margin:0 0 .7rem;border-bottom:1px solid var(--sg-border-muted)}
    .addrx-filter-link{padding:.45rem .7rem;border-bottom:2px solid transparent;color:var(--sg-text-muted);font-size:.78rem;text-decoration:none}
    .addrx-filter-link:hover,.addrx-filter-link.is-active{border-bottom-color:var(--sg-heading-accent-strong);color:var(--sg-text-strong)}
    .addrx-location{display:flex;flex-direction:column;gap:.15rem;color:var(--sg-text-soft);text-align:right}
    .addrx-location span + span{color:var(--sg-text-muted);font-size:.78rem}
    .addrx-empty{padding:.7rem .8rem;border:1px dashed rgba(225,120,100,.35);border-radius:10px;color:var(--sg-text-muted);background:var(--sg-panel-bg-soft);font-size:.88rem}
    .addrx-side{display:flex;flex-direction:column;gap:18px}
    .addrx-card .table{margin-bottom:0;border:1px solid var(--sg-border-subtle)}
    .addrx-card .table thead th{background:var(--sg-control-bg-strong);color:var(--sg-text-strong);border-bottom:1px solid var(--sg-border-subtle);font-size:.8rem;letter-spacing:.02em;padding:.5rem .65rem}
    .addrx-card .table tbody td{border-top:1px solid var(--sg-border-nested);padding:.62rem .65rem}
    .addrx-card .table th:nth-child(1),.addrx-card .table td:nth-child(1){width:28%}
    .addrx-card .table th:nth-child(2),.addrx-card .table td:nth-child(2){width:19%}
    .addrx-card .table th:nth-child(3),.addrx-card .table td:nth-child(3){width:20%}
    .addrx-card .table th:nth-child(4),.addrx-card .table td:nth-child(4){width:19%}
    .addrx-card .table th:nth-child(5),.addrx-card .table td:nth-child(5){width:14%}
    .addrx-card .table tbody tr:hover{background:var(--sg-surface-2)}
    .addrx-card .explorer-code{max-width:300px}
    .addrx-card .explorer-pager{justify-content:flex-end;margin-top:.65rem}
    .addrx-pageinfo{margin-top:.45rem;color:var(--sg-text-faint);font-size:.8rem;text-align:right}
    .addrx-card .btn-outline-light{border-color:var(--sg-border-control);color:var(--sg-text-strong)}
    .addrx-fiat{color:var(--sg-text-muted);font-size:.82em}
    .addrx-amount-cell{display:flex;flex-direction:column;gap:1px;line-height:1.15}
    .addrx-amount-primary{font-weight:700;color:var(--sg-text-strong)}
    .addrx-amount-sub{color:var(--sg-text-muted)}
    .addrx-net-amount{display:flex;justify-content:flex-end;align-items:center;gap:.35rem}
    .addrx-amount-kicker{color:var(--sg-text-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.04em}
    .addrx-row--pending{background:rgba(192,57,43,.08)!important}
    .addrx-row--immature{background:rgba(251,191,36,.08)!important}
    .addrx-pill{display:inline-block;border:1px solid var(--sg-border-accent);color:var(--sg-action-text);background:var(--sg-action-bg);padding:0 .4rem;border-radius:999px;font-size:.7rem;vertical-align:middle}
    .addrx-pill--immature{border-color:rgba(251,191,36,.45);color:#fde68a;background:rgba(251,191,36,.12)}
    .addrx-pill--mature{border-color:rgba(74,222,128,.4);color:#bbf7d0;background:rgba(20,83,45,.25)}
    .addrx-state-stack{display:flex;flex-direction:column;align-items:flex-end;gap:.2rem}
    .addrx-state-stack small{color:var(--sg-text-muted);font-size:.72rem;white-space:nowrap}
    .addrx-event-item{min-width:0}
    .addrx-event-feed{border-top:1px solid var(--sg-border-nested)}
    .addrx-event-feed .addrx-event-item{display:grid;grid-template-columns:38px minmax(0,1fr) 190px 170px;grid-template-areas:"type main amount status";gap:1rem;align-items:center;min-height:64px;box-sizing:border-box;border:0;border-bottom:1px solid var(--sg-border-nested);border-radius:0;background:transparent;padding:10px .35rem}
    .addrx-event-feed .addrx-event-item:hover{background:var(--sg-surface-2)}
    .addrx-event-feed .addrx-event-main{grid-area:main;min-width:0}
    .addrx-event-feed .addrx-event-lead{min-width:0}
    .addrx-event-feed .addrx-event-copy{min-width:0}
    .addrx-event-feed .addrx-event-kind{grid-area:type;display:inline-flex;align-items:center;justify-content:center;align-self:center;width:38px;box-sizing:border-box;padding:.2rem .3rem;border:1px solid var(--sg-border-control);background:var(--sg-panel-bg-soft);border-radius:.25rem;color:var(--sg-text-soft);font-size:.68rem;line-height:1;text-transform:uppercase}
    .addrx-event-feed .addrx-event-amount{grid-area:amount;text-align:right;white-space:nowrap;font-weight:700;color:var(--sg-text-strong)}
    .addrx-event-feed .addrx-event-meta{display:flex;align-items:center;gap:.35rem;margin-top:.2rem;color:var(--sg-text-muted);font-size:.72rem;line-height:1.2;overflow:hidden}
    .addrx-event-feed .addrx-event-meta .addrx-id-cell{flex:0 1 auto;min-width:24px}
    .addrx-event-feed .addrx-meta-separator{color:var(--sg-text-faint);flex:0 0 auto}
    .addrx-event-feed .addrx-location{display:inline-flex;align-items:center;gap:.35rem;flex:0 0 auto;white-space:nowrap}
    .addrx-event-feed .addrx-event-time{color:var(--sg-text-muted);flex:0 0 auto;white-space:nowrap}
    .addrx-event-feed .addrx-event-kind-label{color:var(--sg-text-muted);font-size:.72rem;flex:0 0 auto;white-space:nowrap}
    .addrx-event-feed .addrx-event-status{grid-area:status;text-align:right;color:var(--sg-text-muted);font-size:.78rem;white-space:nowrap}
    .addrx-event-feed .addrx-event-title{min-width:0;font-weight:700;color:var(--sg-text-strong);line-height:1.2}
    .addrx-event-item--pending .addrx-event-kind{background:var(--sg-action-bg);color:var(--sg-action-text)}
    .addrx-event-item--immature .addrx-event-kind{background:rgba(251,191,36,.2);color:#fde68a}
    .addrx-event-item--pending .addrx-event-conf{color:var(--sg-action-text)}
    .addrx-event-item--immature .addrx-event-conf{color:#fde68a}
    @media (max-width:1100px){.addrx-kpis{grid-template-columns:1fr}}
    @media (max-width:760px){
      .addrx-hero-head{flex-direction:column;align-items:flex-start}
      .addrx-filter{overflow-x:auto}
      .addrx-filter-link{white-space:nowrap}
      .addrx-event-feed .addrx-event-item{grid-template-columns:34px minmax(0,1fr) auto auto;grid-template-areas:"type main amount status";gap:.3rem .7rem;min-height:64px;padding:10px .15rem}
      .addrx-event-feed .addrx-event-amount{align-self:start}
      .addrx-event-feed .addrx-event-status{font-size:.75rem}
      .addrx-state-stack{align-items:flex-end}
      .addrx-card .table-responsive{overflow:visible}
      .addrx-card .table.addrx-table{display:block}
      .addrx-card .table.addrx-table thead{display:none}
      .addrx-card .table.addrx-table tbody{display:block}
      .addrx-card .table.addrx-table tr{display:block;border:1px solid var(--sg-border-subtle);border-radius:8px;margin:0 0 6px;background:var(--sg-panel-bg-soft)}
      .addrx-card .table.addrx-table td{display:grid;grid-template-columns:84px minmax(0,1fr);column-gap:8px;align-items:start;border:0!important;padding:.34rem .52rem;white-space:normal;text-align:right;line-height:1.2}
      .addrx-card .table.addrx-table td::before{content:attr(data-label);font-weight:700;color:var(--sg-text-muted);text-transform:uppercase;font-size:.64rem;letter-spacing:.035em;text-align:left}
      .addrx-card .table.addrx-table td > *:not(:first-child){min-width:0}
      .addrx-card .table.addrx-table td a,
      .addrx-card .table.addrx-table td .explorer-code{
        max-width:52vw;
        overflow-wrap:anywhere;
        word-break:break-word;
        white-space:normal;
        text-align:right;
        display:inline-block;
      }
      .addrx-card .table.addrx-table td .addrx-pill{padding:0 .3rem;font-size:.64rem}
    }
  </style>`

  const hero = `
  <section class="addrx-card addrx-hero">
    <div class="addrx-hero-head">
      <div><h2 class="addrx-title">Address</h2></div>
    </div>
    <div class="addrx-id">
      <span class="addrx-id-label">Identifier</span>
      <div class="addrx-id-row">
        <div class="addrx-id-value">${esc(addr)}</div>
        ${copyButton(addr, 'addrx-copy', 'Copy')}
      </div>
    </div>
    <div class="addrx-kpis">
      <article class="addrx-kpi"><small>Unspent SC</small><b>${hastings(balance?.unspentSiacoins)}${fmtFiatFromHastings(balance?.unspentSiacoins, rate, currency) ? `<br><span class="addrx-fiat">${fmtFiatFromHastings(balance?.unspentSiacoins, rate, currency)}</span>` : ''}</b></article>
      <article class="addrx-kpi"><small>Immature SC</small><b>${hastings(balance?.immatureSiacoins)}${fmtFiatFromHastings(balance?.immatureSiacoins, rate, currency) ? `<br><span class="addrx-fiat">${fmtFiatFromHastings(balance?.immatureSiacoins, rate, currency)}</span>` : ''}</b></article>
      <article class="addrx-kpi"><small>Unspent SF</small><b>${int(balance?.unspentSiafunds)}</b></article>
    </div>
    <div class="addrx-meta"></div>
  </section>`

  const utxosContent = (scUtxos.length > 0 || sfUtxos.length > 0)
    ? `${renderScUtxoFeed(scUtxos, rate, currency, tipHeight)}${renderSfUtxoFeed(sfUtxos)}`
    : renderEmptyState('No UTXOs on this page.')
  const displayCount = filter === 'utxos' ? scCount + sfCount : eventsWithImmature.length
  const displayLabel = filter === 'utxos' ? 'UTXOs' : 'events'
  const countText = `${int(displayCount)} ${displayLabel}`
  const activityContent = filter === 'utxos' ? utxosContent : `${renderEventsTable(eventsWithImmature, rate, dailyRateIndex, currency)}${pager}`
  const activityToolbar = filter === 'transactions'
    ? `<div class="addrx-subfilters">${buildFilterSelect('Type', 'type', typeOptions, typeFilter)}${buildFilterSelect('Status', 'status', statusOptions, statusFilter)}<span class="addrx-subfilters-count">${countText}</span></div>`
    : `<div class="addrx-subhead"><span>${countText}</span></div>`
  const overviewPanel = `
  <section class="addrx-card addrx-card--events" aria-labelledby="addrx-activity-title">
    <div class="addrx-head">
      <h3 id="addrx-activity-title">Activity${infoTip('Fiat equivalents use the average exchange rate on the date of each transaction. Address balances use the current exchange rate.')}</h3>
      <span>${int(eventCount)} confirmed · ${int(unconfirmedCount)} pending</span>
    </div>
    ${filterForm}
    ${activityToolbar}
    ${activityContent}
  </section>`
  return `
  ${scopedStyle}
  <div class="explorer-address-page redesign-address-page">
    ${hero}
    ${overviewPanel}
  </div>`
}
