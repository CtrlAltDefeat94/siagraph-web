import { getJson } from '../services/api.js'
import { q, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { dt, int, hastings, bytes, esc } from '../formatters/index.js'
import { rawJson, renderNotFound } from '../components/view.js'
import { ledgerIdCell, ledgerFeed, classifyTransactionKind } from '../components/ledger.js'
import { entityHero } from '../components/entity.js'

function formatMetricValue(key, value) {
  if (value === undefined || value === null) return 'N/A'
  const k = String(key || '').toLowerCase()
  if (k === 'contractrevenue') return hastings(value, esc(String(value)))
  if (k === 'storageutilization') {
    return bytes(value, esc(String(value)))
  }
  if (typeof value === 'number' || /^\d+$/.test(String(value))) return int(value)
  return esc(String(value))
}

export async function renderBlock() {
  const rawId = String(q('id') || entityIdFromPath('block', '')).trim()
  if (!rawId) return renderNotFound('Block')

  let blockId = rawId
  if (/^\d+$/.test(rawId)) {
    const index = await getJson(`/consensus/tip/${encodeURIComponent(rawId)}`)
    blockId = index?.id || rawId
  }

  const [block, tip, metrics] = await Promise.all([
    getJson(`/blocks/${encodeURIComponent(blockId)}`),
    getJson('/consensus/tip').catch(() => null),
    getJson(`/metrics/block/${encodeURIComponent(blockId)}`).catch(() => null),
  ])

  const height = Number(block?.height || 0)
  const tipHeight = Number(tip?.height || 0)
  const hasPrev = Number.isFinite(height) && height > 0
  const hasNext = Number.isFinite(height) && Number.isFinite(tipHeight) && height < tipHeight

  const prevHref = hasPrev ? explorerEntityPath('block', String(height - 1)) : ''
  const nextHref = hasNext ? explorerEntityPath('block', String(height + 1)) : ''

  const v1Tx = Array.isArray(block?.transactions) ? block.transactions : []
  const v2Tx = Array.isArray(block?.v2?.transactions) ? block.v2.transactions : []
  const payouts = Array.isArray(block?.minerPayouts) ? block.minerPayouts : []
  const txCount = v1Tx.length + v2Tx.length

  const v1Rows = v1Tx.map((tx) => ({
    kind: 'V1',
    title: classifyTransactionKind(tx),
    meta: [tx?.id ? ledgerIdCell(explorerEntityPath('tx', tx.id), tx.id) : '', Array.isArray(tx?.hostAnnouncements) && tx.hostAnnouncements.length ? `${int(tx.hostAnnouncements.length)} host announcements` : ''],
  }))

  const v2Rows = v2Tx.map((tx) => ({
    kind: 'V2',
    title: classifyTransactionKind(tx),
    meta: [tx?.id ? ledgerIdCell(explorerEntityPath('tx', tx.id), tx.id) : '', Array.isArray(tx?.hostAnnouncements) && tx.hostAnnouncements.length ? `${int(tx.hostAnnouncements.length)} host announcements` : ''],
  }))

  const payoutRows = payouts.map((p, i) => {
    const address = p?.siacoinOutput?.address || p?.address || p?.unlockHash || p?.unlockhash
    return {
      kind: 'OUT',
      title: `Miner payout #${i + 1}`,
      meta: [address ? ledgerIdCell(explorerEntityPath('address', address), address) : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${hastings(p?.siacoinOutput?.value || p?.value || '0', '0 SC')}</div></div>`,
    }
  })

  const keyMetrics = []
  if (metrics && typeof metrics === 'object') {
    if (metrics.difficulty !== undefined && metrics.difficulty !== null) keyMetrics.push(['Difficulty', formatMetricValue('difficulty', metrics.difficulty)])
    if (metrics.storageUtilization !== undefined && metrics.storageUtilization !== null) keyMetrics.push(['Storage Utilization', formatMetricValue('storageUtilization', metrics.storageUtilization)])
    if (metrics.contractRevenue !== undefined && metrics.contractRevenue !== null) keyMetrics.push(['Contract Revenue', formatMetricValue('contractRevenue', metrics.contractRevenue)])
    if (metrics.activeContracts !== undefined && metrics.activeContracts !== null) keyMetrics.push(['Active Contracts', formatMetricValue('activeContracts', metrics.activeContracts)])
    if (metrics.totalHosts !== undefined && metrics.totalHosts !== null) keyMetrics.push(['Total Hosts', formatMetricValue('totalHosts', metrics.totalHosts)])
  }

  const transactionPanels = []
  if (v1Rows.length) {
    transactionPanels.push(`
        <section class="entx-section">
          <div class="entx-section-head"><h3>V1 Transactions</h3><span>${int(v1Rows.length)}</span></div>
          ${ledgerFeed(v1Rows, 'V1 transactions')}
        </section>
    `)
  }
  if (v2Rows.length) {
    transactionPanels.push(`
        <section class="entx-section">
          <div class="entx-section-head"><h3>V2 Transactions</h3><span>${int(v2Rows.length)}</span></div>
          ${ledgerFeed(v2Rows, 'V2 transactions')}
        </section>
    `)
  }

  const nav = `<div class="entx-nav">
    ${hasPrev ? `<a href="${prevHref}">Previous</a>` : `<span class="is-disabled">Previous</span>`}
    ${hasNext ? `<a href="${nextHref}">Next</a>` : `<span class="is-disabled">Next</span>`}
  </div>`

  return `
    <style>
      .blx-wrap{display:grid;gap:1.15rem}
      .blx-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem}
      .blx-panel--wide{grid-column:1 / -1}
      .blx-empty{border:1px dashed rgba(225,120,100,.28);border-radius:.85rem;padding:.85rem;color:var(--sg-text-muted);background:rgba(76,53,53,.40)}
      .blx-metric-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.75rem}
      .blx-kv{display:grid;gap:.32rem;padding:.75rem;border:1px solid var(--sg-border-subtle);border-radius:.5rem;background:transparent}
      .blx-kv .k{color:var(--sg-text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
      .blx-kv .v{color:var(--sg-text-strong);word-break:break-word}
      .blx-raw{margin-top:1.5rem}
      .blx-raw summary{cursor:pointer;color:var(--sg-text-muted)}
      .blx-raw pre{margin-top:.75rem;max-height:420px;overflow:auto;border:1px solid var(--sg-border-subtle);border-radius:.5rem;padding:.85rem;background:var(--sg-control-bg-strong);color:var(--sg-text-muted)}
      @media (max-width: 1200px){.blx-panels{grid-template-columns:1fr}.blx-metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media (max-width: 700px){.blx-metric-grid{grid-template-columns:1fr}}
    </style>

    <section class="blx-wrap">
      ${entityHero({
        title: 'Block',
        idLabel: 'Block ID',
        idValue: blockId,
        nav,
        kpis: [['Height', int(height)], ['Timestamp', dt(block?.timestamp)], ['Transactions', int(txCount)]],
      })}

      <div class="blx-panels">
        ${transactionPanels.join('')}

        <section class="entx-section">
          <div class="entx-section-head"><h3>Miner Payouts</h3><span>${int(payoutRows.length)}</span></div>
          ${ledgerFeed(payoutRows, 'Miner payouts')}
        </section>

        <section class="entx-section blx-panel--wide">
          <div class="entx-section-head"><h3>Block Metrics</h3><span>${keyMetrics.length ? 'Available' : 'N/A'}</span></div>
          ${keyMetrics.length
            ? `<div class="blx-metric-grid">${keyMetrics.map(([k, v]) => `<div class="blx-kv"><span class="k">${esc(k)}</span><span class="v">${v}</span></div>`).join('')}</div>`
            : '<div class="blx-empty">No metrics found for this block ID.</div>'}
        </section>
      </div>

      <div class="blx-raw">${rawJson('Raw Block JSON', block)}</div>
    </section>
  `
}
