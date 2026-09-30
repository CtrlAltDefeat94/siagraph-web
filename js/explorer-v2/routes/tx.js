import { transactionRenterLinks } from '../components/renter.js'
import { getJson } from '../services/api.js'
import { q, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { int, hastings, esc } from '../formatters/index.js'
import { rawJson, renderNotFound } from '../components/view.js'
import { ledgerIdCell, ledgerFeed, classifyTransactionKind } from '../components/ledger.js'
import { entityHero } from '../components/entity.js'

function amountSc(value) {
  return hastings(value ?? '0', '0 SC')
}

function amountSf(value) {
  return `${int(value ?? 0, '0')} SF`
}

function table(headers, rows) {
  if (!rows.length) return '<div class="ldgr-empty">No rows.</div>'
  const h = headers.map((x) => `<th>${esc(x)}</th>`).join('')
  const b = rows.map((r) => `<tr>${r.map((c, i) => `<td data-label="${esc(headers[i] || '')}">${c}</td>`).join('')}</tr>`).join('')
  return `<div class="table-responsive"><table class="table table-dark table-clean text-white explorer-table-compact"><thead><tr>${h}</tr></thead><tbody>${b}</tbody></table></div>`
}

function normalizeScInput(inp) {
  const parent = inp?.parent?.siacoinOutput || inp?.siacoinOutput || inp
  const address = parent?.address || inp?.address || inp?.parent?.address || ''
  const value = parent?.value || inp?.value || '0'
  const parentId = inp?.parentID || inp?.parentId || inp?.parent?.id || inp?.id || ''
  return { address, value, parentId }
}

function normalizeScOutput(out) {
  const o = out?.siacoinOutput || out
  const address = o?.address || out?.address || ''
  const value = o?.value || out?.value || '0'
  const outputId = out?.id || o?.id || ''
  return { address, value, outputId }
}

function normalizeSfInput(inp) {
  const parent = inp?.parent?.siafundOutput || inp?.siafundOutput || inp
  const address = parent?.address || inp?.address || inp?.parent?.address || ''
  const value = parent?.value || inp?.value || '0'
  const claimAddress = inp?.claimAddress || inp?.claim_address || ''
  const parentId = inp?.parentID || inp?.parentId || inp?.parent?.id || inp?.id || ''
  return { address, value, claimAddress, parentId }
}

function normalizeSfOutput(out) {
  const o = out?.siafundOutput || out
  const address = o?.address || out?.address || ''
  const value = o?.value || out?.value || '0'
  const outputId = out?.id || o?.id || ''
  return { address, value, outputId }
}

export async function renderTx() {
  const txid = String(q('id') || entityIdFromPath('tx', '')).trim()
  if (!txid) return renderNotFound('Transaction')

  const type = await getJson(`/search/${encodeURIComponent(txid)}`)
  const isV2 = type === 'v2Transaction'
  if (!isV2 && type !== 'transaction') return renderNotFound('Transaction')

  const base = isV2 ? '/v2/transactions' : '/transactions'
  const [tx, indices, tip] = await Promise.all([
    getJson(`${base}/${encodeURIComponent(txid)}`),
    getJson(`${base}/${encodeURIComponent(txid)}/indices?offset=0&limit=100`).catch(() => []),
    getJson('/consensus/tip').catch(() => null),
  ])

  const hostAnnouncements = Array.isArray(tx?.hostAnnouncements) ? tx.hostAnnouncements : []
  const indexList = Array.isArray(indices) ? indices : []
  const confirmed = indexList.length > 0 && !tx?.unconfirmed
  const confirmationHeight = indexList.length ? Math.min(...indexList.map((idx) => Number(idx?.height)).filter(Number.isFinite)) : null
  const tipHeight = Number(tip?.height)
  const confirmations = confirmed && confirmationHeight !== null && Number.isFinite(tipHeight) ? Math.max(1, tipHeight - confirmationHeight + 1) : null
  const feeValue = isV2
    ? (tx?.minerFee || tx?.minerFees?.[0] || '0')
    : ((Array.isArray(tx?.minerFees) && tx.minerFees.length ? tx.minerFees[0] : tx?.minerFee) || '0')

  const scIn = Array.isArray(tx?.siacoinInputs) ? tx.siacoinInputs : []
  const scOut = Array.isArray(tx?.siacoinOutputs) ? tx.siacoinOutputs : []
  const sfIn = Array.isArray(tx?.siafundInputs) ? tx.siafundInputs : []
  const sfOut = Array.isArray(tx?.siafundOutputs) ? tx.siafundOutputs : []

  const inputRows = []
  const outputRows = []
  scIn.forEach((inp) => {
    const n = normalizeScInput(inp)
    inputRows.push({
      kind: 'SC IN',
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.parentId ? ledgerIdCell(explorerEntityPath('output', n.parentId), n.parentId) : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${amountSc(n.value)}</div></div>`,
    })
  })
  scOut.forEach((out) => {
    const n = normalizeScOutput(out)
    outputRows.push({
      kind: 'SC OUT',
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.outputId ? ledgerIdCell(explorerEntityPath('output', n.outputId), n.outputId) : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${amountSc(n.value)}</div></div>`,
    })
  })
  sfIn.forEach((inp) => {
    const n = normalizeSfInput(inp)
    inputRows.push({
      kind: 'SF IN',
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.parentId ? ledgerIdCell(explorerEntityPath('output', n.parentId), n.parentId) : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${amountSf(n.value)}</div></div>`,
    })
    if (n.claimAddress) {
      inputRows.push({
        kind: 'CLAIM',
        title: ledgerIdCell(explorerEntityPath('address', n.claimAddress), n.claimAddress),
        meta: ['Claim address'],
      })
    }
  })
  sfOut.forEach((out) => {
    const n = normalizeSfOutput(out)
    outputRows.push({
      kind: 'SF OUT',
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.outputId ? ledgerIdCell(explorerEntityPath('output', n.outputId), n.outputId) : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${amountSf(n.value)}</div></div>`,
    })
  })

  const indexRows = indexList.slice(0, 100).map((idx, i) => ({
    kind: 'BLK',
    title: `Block #${int(i + 1)}`,
    meta: [Number.isFinite(Number(idx?.height)) ? `Height ${int(idx.height)}` : '', idx?.id ? ledgerIdCell(explorerEntityPath('block', idx.id), idx.id) : ''],
  }))

  const renterRows = transactionRenterLinks(tx).map(link => [link])

  const hostRows = hostAnnouncements.map((h) => {
    const pk = h?.publicKey || h?.public_key || ''
    const addr = h?.netAddress || h?.net_address || ''
    return {
      kind: 'HOST',
      title: addr ? esc(addr) : 'N/A',
      meta: [pk ? ledgerIdCell(explorerEntityPath('search', pk), pk) : ''],
    }
  })

  return `
    <style>
      .txx-pill{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:.8rem;font-weight:600;border:1px solid transparent;white-space:nowrap}
      .txx-pill--good{color:#86efac;border-color:rgba(22,163,74,.45);background:rgba(22,163,74,.16)}
      .txx-pill--warn{color:#fde68a;border-color:rgba(251,191,36,.45);background:rgba(251,191,36,.14)}
      .txx-io-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.5rem}
      @media (max-width: 1200px){
        .txx-io-grid{grid-template-columns:1fr}
      }
    </style>

    <section>
      ${entityHero({
        title: 'Transaction',
        idLabel: 'Transaction ID',
        idValue: tx?.id || txid,
        kpis: [
          ['Type', esc(classifyTransactionKind(tx))],
          ['Version', isV2 ? 'V2' : 'V1'],
          ['Status', confirmed ? '<span class="txx-pill txx-pill--good">Confirmed</span>' : '<span class="txx-pill txx-pill--warn">Pending</span>'],
          ['Confirmations', confirmations !== null ? int(confirmations) : 'N/A'],
          ['Miner Fee', amountSc(feeValue)],
        ],
      })}

      <section class="entx-section">
        <div class="entx-section-head"><h3>Chain Indices</h3><span>${int(indexList.length)} total</span></div>
        ${ledgerFeed(indexRows, 'Chain indices')}
      </section>

      <div class="txx-io-grid">
        <section class="entx-section">
          <div class="entx-section-head"><h3>Inputs</h3><span>${int(inputRows.length)} rows</span></div>
          ${ledgerFeed(inputRows, 'Transaction inputs')}
        </section>
        <section class="entx-section">
          <div class="entx-section-head"><h3>Outputs</h3><span>${int(outputRows.length)} rows</span></div>
          ${ledgerFeed(outputRows, 'Transaction outputs')}
        </section>
      </div>

      <section class="entx-section">
        <div class="entx-section-head"><h3>Host Announcements</h3><span>${int(hostRows.length)}</span></div>
        ${ledgerFeed(hostRows, 'Host announcements')}
      </section>

      ${renterRows.length ? `<section class="entx-section"><div class="entx-section-head"><h3>Renters</h3></div>${table(['Renter public key / wallet'], renterRows)}</section>` : ''}

      <section class="entx-section">${rawJson('Raw Transaction JSON', tx)}</section>
    </section>
  `
}
