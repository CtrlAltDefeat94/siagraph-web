import { getJson } from '../services/api.js'
import { q, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { int, hastings, esc } from '../formatters/index.js'
import { copyButton, rawJson, renderNotFound } from '../components/view.js'
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
  return `<span class="ldgr-pill ldgr-pill--${spent.variant}">${esc(spent.label)}</span>`
}

function chainIndexRow(index) {
  if (!index || (index.height === undefined && !index.id)) return ''
  const height = index.height === undefined || index.height === null ? '' : `Block ${linkId('block', String(index.height))}`
  const blockId = index.id ? linkId('block', index.id) : ''
  const value = [height, blockId].filter(Boolean).join(' <span class="outx-separator">·</span> ')
  return value
}

function transactionIdFrom(value) {
  if (!value || typeof value !== 'object') return ''
  return value.transactionId || value.transactionID || value.txid || value.txId || value.transaction?.id || ''
}

function proofSection(stateElement) {
  const leafIndex = stateElement?.leafIndex
  const proof = Array.isArray(stateElement?.merkleProof) ? stateElement.merkleProof : []
  if (leafIndex === undefined && !proof.length) return ''
  const summary = [leafIndex === undefined ? '' : `Leaf index ${int(leafIndex)}`, proof.length ? 'Merkle proof available' : 'Merkle proof unavailable'].filter(Boolean).join(' <span class="outx-separator">·</span> ')
  const representation = JSON.stringify({ leafIndex, merkleProof: proof })
  return `
    <section class="entx-section outx-proof">
      <details>
        <summary><span>State / Merkle Proof</span><span class="outx-proof-summary">${summary}</span></summary>
        <div class="outx-proof-actions">${copyButton(representation, 'entx-copy', 'Copy state')}</div>
        ${proof.length ? `<ol>${proof.map((hash) => `<li><code class="explorer-code" title="${esc(hash)}">${esc(hash)}</code></li>`).join('')}</ol>` : '<p class="outx-muted">No Merkle proof returned.</p>'}
      </details>
    </section>`
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
  const [ratePayload, tip] = await Promise.all([
    outputType === 'siacoin' ? getJson(`/exchange-rate/siacoin/${encodeURIComponent(currency)}`).catch(() => null) : null,
    getJson('/consensus/tip').catch(() => null),
  ])
  const rate = getRateFromScalarPayload(ratePayload)

  const isSc = outputType === 'siacoin'
  const maturityHeight = Number(output?.maturityHeight || 0)
  const tipHeight = Number(tip?.height)
  const isImmature = maturityHeight > 0 && Number.isFinite(tipHeight) && maturityHeight > tipHeight
  const isSpent = !!output?.spentIndex
  const outputId = String(output?.id || oid)
  const address = output?.siacoinOutput?.address || output?.siafundOutput?.address || output?.address || ''
  const spentTxId = transactionIdFrom(output?.spentIndex) || transactionIdFrom(output?.spentBy)

  const rawValue = isSc ? (output?.siacoinOutput?.value || output?.value || '0') : (output?.siafundOutput?.value || output?.value || '0')
  const formattedValue = isSc ? hastings(rawValue, '0 SC') : `${int(rawValue, '0')} SF`
  const fiat = isSc ? fmtFiatFromHastings(rawValue, rate, currency) : ''
  const state = isSpent ? { label: 'Spent', variant: 'bad' } : (isImmature ? { label: 'Immature', variant: 'immature' } : { label: 'Unspent', variant: 'good' })
  const maturity = maturityHeight > 0 ? int(maturityHeight) : 'None'
  const spendRelationship = [
    spentTxId ? linkId('tx', spentTxId) : '',
    chainIndexRow(output?.spentIndex),
  ].filter(Boolean).join(' <span class="outx-separator">·</span> ')

  return `
    <style>
      .outx-page .entx-compact-id{max-width:min(100%, 420px);color:var(--sg-text-strong)}
      .outx-page .entx-compact-id-text{color:var(--sg-text-strong)}
      .outx-body{margin-top:1rem}
      .outx-fiat{font-size:.95rem;color:var(--sg-text-muted);opacity:.88;font-weight:500}
      .outx-meta-row{display:grid;grid-template-columns:170px 1fr;gap:10px;padding:5px 0;border-bottom:1px solid var(--sg-border-subtle)}
      .outx-meta-row:last-child{border-bottom:0}
      .outx-meta-row span:first-child{color:var(--sg-text-faint);font-size:.86rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600}
      .outx-meta-row span:last-child{color:var(--sg-text-strong);word-break:break-word}
      .outx-separator{color:var(--sg-text-faint);padding:0 .25rem}
      .outx-muted{color:var(--sg-text-muted)}
      .outx-body .entx-section{margin-top:.75rem}
      .outx-page details>summary{list-style:none}
      .outx-page details>summary::-webkit-details-marker{display:none}
      .outx-page details>summary::before{content:'▸';display:inline-block;width:1rem;color:var(--sg-text-faint)}
      .outx-page details[open]>summary::before{content:'▾'}
      .outx-proof details{border-top:1px solid var(--sg-border-subtle);padding-top:.75rem}
      .outx-proof summary{display:flex;align-items:center;gap:.75rem;cursor:pointer;color:var(--sg-heading-accent);font-weight:600}
      .outx-proof-summary{color:var(--sg-text-muted);font-size:.82rem;font-weight:500}
      .outx-proof-actions{margin:.75rem 0}
      .outx-proof ol{margin:.5rem 0 0;padding-left:2rem;display:grid;gap:.35rem}
      .outx-proof li{min-width:0;color:var(--sg-text-faint)}
      .outx-proof code{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      @media (max-width: 700px){.outx-meta-row{grid-template-columns:1fr}.outx-proof summary{align-items:flex-start;flex-direction:column;gap:.25rem}}
    </style>

    <section class="outx-page">
      ${entityHero({
        title: isSc ? 'Siacoin Output' : 'Siafund Output',
        idLabel: '',
        idValue: outputId,
        compactId: true,
        kpis: [
          ['Value', `${formattedValue}${fiat ? `<br><span class="outx-fiat">\u2248 ${fiat}</span>` : ''}`],
          ['State', renderStateBadge(state)],
          ['Maturity', maturityHeight > 0 && isImmature ? `${maturity}<br><span class="outx-fiat">Future block</span>` : maturity],
        ],
      })}

      <div class="outx-body">
        <section class="entx-section">
          <div class="entx-section-head"><h3>Owner</h3></div>
          ${renderInfoRow('Address', address ? linkId('address', address) : 'N/A')}
        </section>

        ${isSpent ? `<section class="entx-section outx-relationship">${renderInfoRow('Spent By', spendRelationship)}</section>` : ''}
      </div>

      ${proofSection(output?.stateElement)}
      <section class="entx-section">${rawJson('Raw Output JSON', output)}</section>
    </section>
  `
}
