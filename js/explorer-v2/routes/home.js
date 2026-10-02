import { getJson } from '../services/api.js'
import { explorerEntityPath } from '../state/router.js'
import { dt, id, int } from '../formatters/index.js'

function relTime(value) {
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return 'unknown time'
  const s = Math.floor((Date.now() - d.getTime()) / 1000)
  if (s < 60) return `${s}s ago`
  if (s < 3600) return `${Math.floor(s / 60)}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return `${Math.floor(s / 86400)}d ago`
}

function rateBadge(label, state = 'info') {
  return `<span class="explorer-badge explorer-badge--${state}">${label}</span>`
}

function renderErrorState(title, message) {
  return `<div class="explorer-state explorer-state--error"><h3>${title}</h3><p>${message}</p></div>`
}

function renderEmptyState(title, message) {
  return `<div class="explorer-state explorer-state--empty"><h3>${title}</h3><p>${message}</p></div>`
}

function sectionWrap(title, actionHtml = '', bodyHtml = '', sectionClass = '') {
  return `
    <section class="explorer-section ${sectionClass}">
      <header class="explorer-section-head">
        <h2 class="explorer-section-title">${title}</h2>
        ${actionHtml}
      </header>
      <div class="explorer-section-body">${bodyHtml}</div>
    </section>
  `
}

async function resolveSearch(value) {
  if (/^\d+$/.test(value)) {
    const idx = await getJson(`/consensus/tip/${encodeURIComponent(value)}`)
    return explorerEntityPath('block', idx.id)
  }

  const type = await getJson(`/search/${encodeURIComponent(value)}`)
  const map = {
    block: explorerEntityPath('block', value),
    transaction: explorerEntityPath('tx', value),
    v2Transaction: explorerEntityPath('tx', value),
    address: explorerEntityPath('address', value),
    contract: explorerEntityPath('contract', value),
    v2Contract: explorerEntityPath('contract', value),
    siacoinElement: explorerEntityPath('output', value),
    siafundElement: explorerEntityPath('output', value),
    host: `/host?public_key=${encodeURIComponent(value)}`,
  }
  return map[type] || null
}

function wireSearchUX() {
  const form = document.getElementById('explorer-home-search-form')
  const input = document.getElementById('explorer-home-search-input')
  const status = document.getElementById('explorer-home-search-status')
  const submit = document.getElementById('explorer-home-search-submit')

  if (!form || !input || !status || !submit) return

  const setStatus = (message, state = 'idle') => {
    status.textContent = message
    status.dataset.state = state
  }

  setStatus('Tip: Press / to focus, Enter to search, and Esc to clear.', 'idle')

  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement !== input) {
      e.preventDefault()
      input.focus()
      input.select()
    }
  })

  input.addEventListener('input', () => {
    setStatus('Search supports tx IDs, block heights, addresses, contracts, hosts, and outputs.', 'idle')
  })

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      input.value = ''
      setStatus('Search cleared. Enter a value to begin.', 'idle')
    }
  })

  form.addEventListener('submit', async (e) => {
    e.preventDefault()
    const query = String(input.value || '').trim()
    if (!query) {
      setStatus('Enter a search value first.', 'error')
      return
    }

    submit.disabled = true
    setStatus('Searching explorer…', 'loading')

    try {
      const target = await resolveSearch(query)
      if (!target) {
        setStatus('No result found for that query.', 'error')
        submit.disabled = false
        return
      }
      setStatus('Found. Redirecting…', 'success')
      window.location.href = target
    } catch (_err) {
      setStatus('Search request failed. Please try again.', 'error')
      submit.disabled = false
    }
  })
}

function renderHero(tip, fee) {
  return `
    <section class="explorer-home-hero" aria-labelledby="explorer-home-hero-title">
      <div class="explorer-home-hero__main">
        <p class="explorer-home-eyebrow">Blockchain Explorer</p>
        <h2 id="explorer-home-hero-title" class="explorer-home-hero-title">Blockchain Explorer</h2>
        <p class="explorer-home-hero-copy">Search transactions, blocks, addresses, contracts, hosts, outputs, and wallet-related activity across the Sia network.</p>
      </div>
      <div class="explorer-home-hero__side">
        <form id="explorer-home-search-form" class="explorer-search explorer-search--hero" autocomplete="off" role="search" aria-label="Explorer Search">
          <span class="explorer-search-icon" aria-hidden="true">⌕</span>
          <input id="explorer-home-search-input" class="form-control" type="text" name="id" placeholder="Search by transaction ID, block height, address, contract, host, or wallet…" required>
          <button id="explorer-home-search-submit" class="btn btn-brand" type="submit" aria-label="Run explorer search">Search</button>
        </form>
        <p class="explorer-search-helper">Supported lookups: transaction ID, block height, address, contract ID, host public key, siacoin output, or wallet-related address.</p>
        <p id="explorer-home-search-status" class="explorer-search-status" aria-live="polite"></p>
      </div>
      <div class="explorer-home-hero__metrics">
        <div class="explorer-home-meta"><span>Current block height</span><span>${int(tip.height)}</span></div>
        <div class="explorer-home-meta"><span>Latest block</span><span>${id(tip.id, 16, 12)}</span></div>
        <div class="explorer-home-meta"><span>Last block time</span><span>${dt(tip.timestamp)} (${relTime(tip.timestamp)})</span></div>
        <div class="explorer-home-meta"><span>Recommended fee</span><span>${int(fee)} H</span></div>
      </div>
    </section>
  `
}

function renderKpis({ tip, blockMetrics, txpool, hostMetrics, recentBlocks }) {
  const poolV1 = Array.isArray(txpool?.transactions) ? txpool.transactions.length : 0
  const poolV2 = Array.isArray(txpool?.v2transactions) ? txpool.v2transactions.length : 0

  const totalTxpool = poolV1 + poolV2
  const txState = totalTxpool > 250 ? 'warn' : 'good'
  const txCount = (recentBlocks || []).reduce((sum, b) => {
    const v1 = Array.isArray(b?.transactions) ? b.transactions.length : 0
    const v2 = Array.isArray(b?.v2?.transactions) ? b.v2.transactions.length : 0
    return sum + v1 + v2
  }, 0)
  const avgTxPerBlock = recentBlocks?.length ? Math.round(txCount / recentBlocks.length) : 0

  const cards = [
    ['Current Block Height', int(tip.height), rateBadge('Live', 'good')],
    ['Latest Block Time', relTime(tip.timestamp), dt(tip.timestamp)],
    ['Network Difficulty', int(tip?.difficulty), rateBadge('Consensus', 'info')],
    ['Total Hosts', int(blockMetrics?.totalHosts), rateBadge('Network', 'info')],
    ['Active Hosts', int(hostMetrics?.activeHosts), rateBadge('Monitored', 'neutral')],
    ['Active Contracts', int(blockMetrics?.activeContracts), rateBadge('On-chain', 'info')],
    ['Pending Transactions', int(totalTxpool), rateBadge(totalTxpool === 0 ? 'Idle' : 'Pending', txState)],
    ['Recent Tx Throughput', int(avgTxPerBlock), 'Avg tx / block (last 8)'],
  ]

  return `
    <div class="explorer-kpi-strip explorer-kpi-strip--compact explorer-kpi-strip--dashboard">
      ${cards.map(([label, value, meta]) => `
        <article class="explorer-kpi-card explorer-kpi-card--dashboard">
          <div class="explorer-kpi-label">${label}</div>
          <div class="explorer-kpi-value">${value}</div>
          <div class="explorer-kpi-meta">${meta}</div>
        </article>
      `).join('')}
    </div>
  `
}

function renderBlocksPanel(blocks) {
  if (!blocks.length) return renderEmptyState('Latest Blocks', 'No recent block data available.')

  const rows = blocks.map((b) => `
    <a class="explorer-activity-row" href="${explorerEntityPath('block', b.id)}">
      <div class="explorer-activity-main">
        <span class="explorer-activity-id">${id(b.id, 14, 10)}</span>
        <span class="explorer-activity-meta">Height ${int(b.height)} • ${relTime(b.timestamp)}</span>
      </div>
      <div class="explorer-activity-side">
        ${rateBadge(`TX ${int((b?.v2?.transactions?.length || 0) + (b?.transactions?.length || 0))}`, 'info')}
      </div>
    </a>
  `).join('')

  return `<div class="explorer-activity-list">${rows}</div>`
}

function renderTransactionsPanel(transactions) {
  if (!transactions.length) return renderEmptyState('Latest Transactions', 'No transaction activity found.')

  const rows = transactions.slice(0, 12).map((tx) => `
    <a class="explorer-activity-row" href="${explorerEntityPath('tx', tx.id)}">
      <div class="explorer-activity-main">
        <span class="explorer-activity-id">${id(tx.id, 14, 10)}</span>
        <span class="explorer-activity-meta">${relTime(tx.timestamp)} • Block ${int(tx.height)}</span>
      </div>
      <div class="explorer-activity-side">
        ${rateBadge(tx.version.toUpperCase(), tx.version === 'v2' ? 'good' : 'neutral')}
      </div>
    </a>
  `).join('')

  return `<div class="explorer-activity-list">${rows}</div>`
}

function renderQuickLinks() {
  const links = [
    ['Blocks', '/search'],
    ['Transactions', '/search'],
    ['Contracts', '/search'],
    ['Hosts', '/host_explorer'],
    ['Wallets', '/search'],
    ['Analytics', '/block-metrics'],
  ]

  return `<div class="explorer-quicklinks-grid">
    ${links.map(([label, target]) => `<a class="explorer-quicklink-card" href="${target}"><span class="explorer-quicklink-card__label">${label}</span><span class="explorer-quicklink-card__hint">Open</span></a>`).join('')}
  </div>`
}

async function latestBlocksFromTip(tip, count = 8) {
  const blocks = []
  let current = tip.id

  for (let i = 0; i < count; i++) {
    const b = await getJson(`/blocks/${encodeURIComponent(current)}`)
    blocks.push(b)
    if (!b?.parentID) break
    current = b.parentID
  }

  return blocks
}

function flattenTx(blocks) {
  const out = []
  blocks.forEach((b) => {
    ;(b.transactions || []).forEach((tx) => out.push({ id: tx.id, version: 'v1', timestamp: b.timestamp, height: b.height }))
    ;(b?.v2?.transactions || []).forEach((tx) => out.push({ id: tx.id, version: 'v2', timestamp: b.timestamp, height: b.height }))
  })
  return out
}

export async function renderHome() {
  const heroSlot = document.getElementById('explorer-home-hero')
  const kpiSlot = document.getElementById('explorer-home-kpis')
  const activitySlot = document.getElementById('explorer-home-activity')
  const linksSlot = document.getElementById('explorer-home-quicklinks')

  if (!heroSlot || !kpiSlot || !activitySlot || !linksSlot) {
    return renderErrorState('Explorer Home', 'Home layout slots are missing.')
  }

  try {
    const [tip, blockMetrics, hostMetrics, txpool, fee] = await Promise.all([
      getJson('/consensus/tip'),
      getJson('/metrics/block'),
      getJson('/metrics/host'),
      getJson('/txpool/transactions'),
      getJson('/txpool/fee'),
    ])

    const blocks = await latestBlocksFromTip(tip, 8)
    const tx = flattenTx(blocks)

    heroSlot.innerHTML = renderHero(tip, fee)

    const blocksPanel = renderBlocksPanel(blocks)
    const txPanel = renderTransactionsPanel(tx)
    const statsContent = renderKpis({ tip, blockMetrics, txpool, hostMetrics, recentBlocks: blocks })
    kpiSlot.innerHTML = sectionWrap('Network Stats', '', statsContent, 'explorer-section--stats')
    const latestBlockTarget = blocks[0]?.id ? explorerEntityPath('block', blocks[0].id) : '/search'
    activitySlot.innerHTML = `
      <div class="explorer-home-zone explorer-home-zone--table">${sectionWrap('Latest Blocks', `<a class="explorer-home-quicklink" href="${latestBlockTarget}">View tip block</a>`, blocksPanel, 'explorer-section--activity')}</div>
      <div class="explorer-home-zone explorer-home-zone--table">${sectionWrap('Latest Transactions', `<a class="explorer-home-quicklink" href="/search">View all</a>`, txPanel, 'explorer-section--activity')}</div>
    `
    linksSlot.innerHTML = sectionWrap('Quick Access', '', renderQuickLinks(), 'explorer-section--quicklinks')
    wireSearchUX()

    return ''
  } catch (e) {
    heroSlot.innerHTML = renderErrorState('Explorer Unavailable', 'Unable to load explorer data right now.')
    kpiSlot.innerHTML = sectionWrap('Network Stats', '', renderEmptyState('Network Stats', 'Metrics temporarily unavailable.'), 'explorer-section--stats')
    activitySlot.innerHTML = `
      <div class="explorer-home-zone explorer-home-zone--table">${sectionWrap('Latest Blocks', '', renderEmptyState('Latest Blocks', 'Unable to load latest blocks.'), 'explorer-section--activity')}</div>
      <div class="explorer-home-zone explorer-home-zone--table">${sectionWrap('Latest Transactions', '', renderEmptyState('Latest Transactions', 'Unable to load latest transactions.'), 'explorer-section--activity')}</div>
    `
    linksSlot.innerHTML = sectionWrap('Quick Access', '', renderQuickLinks(), 'explorer-section--quicklinks')
    return ''
  }
}
