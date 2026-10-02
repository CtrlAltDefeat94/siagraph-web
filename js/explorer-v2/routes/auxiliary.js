import { getJson } from '../services/api.js'
import { q, entityIdFromPath, explorerEntityPath } from '../state/router.js'
import { section, kv, table, rawJson, renderNotFound } from '../components/view.js'
import { esc, id, int, dt } from '../formatters/index.js'

function jsonBlock(title, payload) {
  return section(title, rawJson('Raw JSON', payload))
}

function link(kind, value) {
  const raw = String(value || '').trim()
  return raw ? `<a href="${explorerEntityPath(kind, raw)}">${id(raw)}</a>` : 'N/A'
}

function countTx(txpool) {
  const transactions = Array.isArray(txpool?.v2transactions) ? txpool.v2transactions : []
  return { total: transactions.length }
}

function txpoolKpi(label, value, meta) {
  return `
    <div class="explorer-kpi-card explorer-kpi-card--dashboard explorer-txpool-kpi">
      <div class="explorer-kpi-label">${esc(label)}</div>
      <div class="explorer-kpi-value">${esc(String(value))}</div>
      ${meta ? `<div class="explorer-kpi-meta">${esc(meta)}</div>` : ''}
    </div>
  `
}

function txpoolTransactionLink(value) {
  const raw = String(value || '').trim()
  if (!raw) return 'N/A'
  return `<a class="explorer-txpool-row__id" href="${explorerEntityPath('tx', raw)}">${id(raw, 18, 12)}</a>`
}

function txpoolTransactionList(transactions) {
  if (!transactions.length) {
    return `
      <div class="explorer-state explorer-state--empty">
        <h3>No pending transactions</h3>
        <p>The transaction pool is currently clear.</p>
      </div>
    `
  }

  const rows = transactions.map((tx) => {
    const scInputs = Array.isArray(tx?.siacoinInputs) ? tx.siacoinInputs.length : 0
    const scOutputs = Array.isArray(tx?.siacoinOutputs) ? tx.siacoinOutputs.length : 0
    const sfInputs = Array.isArray(tx?.siafundInputs) ? tx.siafundInputs.length : 0
    const sfOutputs = Array.isArray(tx?.siafundOutputs) ? tx.siafundOutputs.length : 0
    const siafundText = sfInputs || sfOutputs ? `<span>${esc(String(sfInputs))} SF in · ${esc(String(sfOutputs))} SF out</span>` : ''
    return `
      <article class="explorer-txpool-row">
        <div class="explorer-txpool-row__main">
          ${txpoolTransactionLink(tx?.id)}
        </div>
        <div class="explorer-txpool-row__stats">
          <span>${esc(String(scInputs))} SC in</span>
          <span>${esc(String(scOutputs))} SC out</span>
          ${siafundText}
        </div>
      </article>
    `
  }).join('')

  return `<div class="explorer-txpool-list">${rows}</div>`
}

function txpoolPanel(transactions) {
  return `
    <section class="explorer-section explorer-txpool-panel">
      <div class="explorer-section-head">
        <div>
          <h2 class="explorer-section-title">Pending Transactions</h2>
        </div>
        <span class="explorer-badge explorer-badge--neutral">${esc(String(transactions.length))} pending</span>
      </div>
      ${txpoolTransactionList(transactions)}
    </section>
  `
}

export async function renderHeight() {
  const height = String(q('height') || entityIdFromPath('height', '')).trim()
  if (!/^\d+$/.test(height)) return renderNotFound('Block height')
  const index = await getJson(`/consensus/tip/${encodeURIComponent(height)}`)
  if (index?.id) {
    window.location.replace(explorerEntityPath('block', index.id))
    return section('Block Height', '<p>Redirecting to block...</p>')
  }
  return renderNotFound('Block height')
}

export async function renderEvent() {
  const eventId = String(q('id') || entityIdFromPath('event', '')).trim()
  if (!eventId) return renderNotFound('Event')
  const event = await getJson(`/events/${encodeURIComponent(eventId)}`)
  return `
    ${section('Event', kv({
      'Event ID': event?.id || eventId,
      Type: event?.type || 'N/A',
      Height: event?.index?.height ?? event?.height ?? 'N/A',
      Timestamp: event?.timestamp ? dt(event.timestamp) : 'N/A',
    }))}
    ${jsonBlock('Event Payload', event)}
  `
}

export async function renderTxpool() {
  const txpool = await getJson('/txpool/transactions')
  const counts = countTx(txpool)
  const transactions = Array.isArray(txpool?.v2transactions) ? txpool.v2transactions : []
  const scInputs = transactions.reduce((sum, tx) => sum + (Array.isArray(tx?.siacoinInputs) ? tx.siacoinInputs.length : 0), 0)
  const scOutputs = transactions.reduce((sum, tx) => sum + (Array.isArray(tx?.siacoinOutputs) ? tx.siacoinOutputs.length : 0), 0)
  return `
    <div class="explorer-txpool-page">
      <section class="explorer-section explorer-txpool-hero">
        <div class="explorer-section-head explorer-txpool-hero__head">
          <div>
            <p class="explorer-subheading mb-1">Blockchain settlement queue</p>
            <h2 class="explorer-section-title">Transaction Pool</h2>
            <p class="explorer-txpool-copy">Pending Sia transactions waiting to be confirmed in upcoming blocks.</p>
          </div>
          <span class="explorer-badge explorer-badge--neutral">Live explorer data</span>
        </div>
        <div class="explorer-kpi-strip explorer-kpi-strip--dashboard explorer-txpool-kpis">
          ${txpoolKpi('Pending Transactions', int(counts.total), 'Transactions waiting for confirmation')}
          ${txpoolKpi('Siacoin Inputs', int(scInputs), 'Across pending transactions')}
          ${txpoolKpi('Siacoin Outputs', int(scOutputs), 'Across pending transactions')}
        </div>
      </section>
      ${txpoolPanel(transactions)}
    </div>
  `
}

export async function renderConsensus() {
  const tip = await getJson('/consensus/tip')
  return section('Current Consensus Tip', kv({
    Height: int(tip?.height),
    'Block ID': tip?.id || 'N/A',
    'Observed at': dt(tip?.timestamp),
    Parent: tip?.parentID ? link('block', tip.parentID) : 'N/A',
  }))
}

export async function renderMetrics() {
  const [tip, txpool] = await Promise.all([
    getJson('/consensus/tip'),
    getJson('/txpool/transactions').catch(() => null),
  ])
  const counts = countTx(txpool)
  return section('Current Network State', kv({
    'Current Height': int(tip?.height),
    'Tip Block': tip?.id ? link('block', tip.id) : 'N/A',
    'Observed at': dt(tip?.timestamp),
    'Pending Transactions': int(counts.total),
  }))
}

export async function renderPeers() {
  const candidates = ['/syncer/peers', '/peers']
  let peers = null
  let lastError = null
  for (const path of candidates) {
    try {
      peers = await getJson(path)
      break
    } catch (e) {
      lastError = e
    }
  }
  if (!peers) throw lastError
  const list = Array.isArray(peers) ? peers : (Array.isArray(peers?.peers) ? peers.peers : [])
  const rows = list.map((peer) => [
    esc(peer?.address || peer?.addr || peer?.netAddress || peer?.net_address || String(peer || 'N/A')),
    esc(peer?.version || peer?.protocolVersion || 'N/A'),
    peer?.inbound === undefined ? 'N/A' : esc(String(peer.inbound)),
  ])
  return section('Connected Peers', table(['Address', 'Version', 'Inbound'], rows))
}

export async function renderExchange() {
  const currency = String(q('currency') || entityIdFromPath('exchange', 'eur')).trim().toLowerCase() || 'eur'
  const payload = await getJson(`/exchange-rate/siacoin/${encodeURIComponent(currency)}`)
  const rate = typeof payload === 'number' ? payload : (payload?.rate ?? payload?.data?.rate ?? payload)
  return section('Current Siacoin Exchange Rate', kv({
    Currency: currency.toUpperCase(),
    '1 SC': typeof rate === 'number' ? rate : JSON.stringify(rate),
  }))
}
