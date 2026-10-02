import { getJson } from '../services/api.js'
import { q, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { int, hastings, esc } from '../formatters/index.js'
import { rawJson, renderNotFound } from '../components/view.js'
import { ledgerIdCell } from '../components/ledger.js'
import { entityHero } from '../components/entity.js'

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
  return `${cur} ${value.toLocaleString(window.APP_LOCALE || undefined, { maximumFractionDigits: 2 })}`
}

function linkId(kind, value) {
  const raw = String(value || '').trim()
  if (!raw) return ''
  return ledgerIdCell(explorerEntityPath(kind, raw), raw)
}

function renderInfoRow(label, value) {
  return `<div class="outx-meta-row"><span>${esc(label)}</span><span>${value}</span></div>`
}

function renderStateBadge(spent) {
  return spent ? '<span class="ldgr-pill ldgr-pill--bad">Spent</span>' : '<span class="ldgr-pill ldgr-pill--good">Unspent</span>'
}

export async function renderOutput() {
  const oid = String(q('id') || entityIdFromPath('output', '')).trim()
  if (!oid) return renderNotFound('Output')

  const type = await getJson(`/search/${encodeURIComponent(oid)}`)
  let outputType = ''
  let output
  if (type === 'siacoinElement') {
    outputType = 'siacoin'
    output = await getJson(`/outputs/siacoin/${encodeURIComponent(oid)}`)
  } else if (type === 'siafundElement') {
    outputType = 'siafund'
    output = await getJson(`/outputs/siafund/${encodeURIComponent(oid)}`)
  } else {
    return renderNotFound('Output')
  }

  const currency = readCurrency()
  const ratePayload = outputType === 'siacoin' ? await getJson(`/exchange-rate/siacoin/${encodeURIComponent(currency)}`).catch(() => null) : null
  const rate = getRateFromScalarPayload(ratePayload)

  const isSc = outputType === 'siacoin'
  const isSpent = !!output?.spentIndex
  const outputId = String(output?.id || oid)
  const address = output?.siacoinOutput?.address || output?.siafundOutput?.address || output?.address || ''
  const source = String(output?.source || 'N/A')
  const maturityHeight = Number(output?.maturityHeight || 0)
  const spentIndexId = output?.spentIndex?.id || ''
  const spendHeight = output?.spentIndex?.height

  const rawValue = isSc ? (output?.siacoinOutput?.value || output?.value || '0') : (output?.siafundOutput?.value || output?.value || '0')
  const formattedValue = isSc ? hastings(rawValue, '0 SC') : `${int(rawValue, '0')} SF`
  const fiat = isSc ? fmtFiatFromHastings(rawValue, rate, currency) : ''

  return `
    <style>
      .outx-body{margin-top:1.5rem;display:grid;grid-template-columns:1.2fr .8fr;gap:1.5rem}
      .outx-fiat{font-size:.95rem;color:var(--sg-text-muted);opacity:.88;font-weight:500}
      .outx-meta-row{display:grid;grid-template-columns:170px 1fr;gap:10px;padding:7px 0;border-bottom:1px solid var(--sg-border-subtle)}
      .outx-meta-row:last-child{border-bottom:0}
      .outx-meta-row span:first-child{color:var(--sg-text-faint);font-size:.86rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600}
      .outx-meta-row span:last-child{color:var(--sg-text-strong);word-break:break-word}
      @media (max-width: 1100px){.outx-body{grid-template-columns:1fr}}
      @media (max-width: 700px){.outx-meta-row{grid-template-columns:1fr}}
    </style>

    <section>
      ${entityHero({
        title: isSc ? 'Siacoin Output' : 'Siafund Output',
        idLabel: 'Output ID',
        idValue: outputId,
        kpis: [
          ['Value', `${formattedValue}${fiat ? `<br><span class="outx-fiat">\u2248 ${fiat}</span>` : ''}`],
          ['State', renderStateBadge(isSpent)],
          ['Maturity Height', int(maturityHeight, '0')],
        ],
      })}

      <div class="outx-body">
        <section class="entx-section">
          <div class="entx-section-head"><h3>Output</h3></div>
          ${renderInfoRow('Output ID', linkId('output', outputId) || esc(outputId))}
          ${renderInfoRow('Address', address ? linkId('address', address) : 'N/A')}
          ${renderInfoRow('Source', esc(source))}
        </section>

        <section class="entx-section">
          <div class="entx-section-head"><h3>Spend Status</h3></div>
          ${renderInfoRow('Spent Block', spentIndexId ? linkId('block', spentIndexId) : 'Unspent')}
          ${renderInfoRow('Spend Height', spendHeight === undefined || spendHeight === null ? 'N/A' : int(spendHeight))}
        </section>
      </div>

      <section class="entx-section">${rawJson('Raw Output JSON', output)}</section>
    </section>
  `
}
