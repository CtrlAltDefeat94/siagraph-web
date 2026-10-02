import { esc } from '../formatters/index.js'
import { copyButton } from './view.js'

// Renders the full id; CSS ellipsizes it only when the row genuinely lacks space.
export function ledgerIdCell(href, rawId) {
  const raw = String(rawId || '').trim()
  if (!raw) return ''
  return `<span class="ldgr-id-cell"><a href="${href}"><span class="ldgr-id-text" title="${esc(raw)}">${esc(raw)}</span></a>${copyButton(raw, 'ldgr-inline-copy')}</span>`
}

export function ledgerAmountCell(primaryText, subText) {
  return `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${primaryText}</div>${subText ? `<div class="ldgr-amount-sub">${subText}</div>` : ''}</div>`
}

export function ledgerPill(label, variant) {
  return `<span class="ldgr-pill${variant ? ` ldgr-pill--${variant}` : ''}">${esc(label)}</span>`
}

export function ledgerStateStack(label, variant, small) {
  return `<span class="ldgr-state-stack">${ledgerPill(label, variant)}${small ? `<small>${esc(small)}</small>` : ''}</span>`
}

// One self-contained grid row: type badge | title + meta | amount | status. Same geometry for every row.
export function ledgerRow({ kind, title, meta = [], amount = '', status = '', pending = false, immature = false, href = '' }) {
  const metaHtml = meta.filter(Boolean).join('<span class="ldgr-meta-separator" aria-hidden="true">·</span>')
  const classes = ['ldgr-item', href ? 'ldgr-item--linked' : '', pending ? 'ldgr-item--pending' : '', immature ? 'ldgr-item--immature' : ''].filter(Boolean).join(' ')
  return `
    <article class="${classes}">
      ${href ? `<a class="ldgr-row-link" href="${esc(href)}" aria-label="Open"></a>` : ''}
      <div class="ldgr-kind" aria-hidden="true">${esc(kind)}</div>
      <div class="ldgr-main">
        <div class="ldgr-lead">
          <div class="ldgr-copy">
            <div class="ldgr-title">${title}</div>
            ${metaHtml ? `<div class="ldgr-meta">${metaHtml}</div>` : ''}
          </div>
        </div>
      </div>
      <div class="ldgr-amount">${amount}</div>
      <div class="ldgr-status">${status}</div>
    </article>
  `
}

export function ledgerFeed(rows, ariaLabel, emptyMessage = 'No rows.', className = '') {
  if (!rows.length) return `<div class="ldgr-empty">${esc(emptyMessage)}</div>`
  return `<div class="ldgr-feed${className ? ` ${esc(className)}` : ''}" aria-label="${esc(ariaLabel)}">${rows.map(ledgerRow).join('')}</div>`
}

export const RESOLUTION_OPERATION_LABELS = { storage_proof: 'Storage proof submitted', renewal: 'Contract renewed', expiration: 'Contract expired' }

function resolutionOperationLabels(resolutions) {
  const labels = []
  resolutions.forEach((r) => {
    const type = r?.type || r?.parent?.resolutionType || ''
    const label = RESOLUTION_OPERATION_LABELS[type] || 'Contract resolution'
    if (!labels.includes(label)) labels.push(label)
  })
  return labels
}

// Returns every meaningful protocol operation a transaction performs, most significant
// first, so a page can show a primary label plus secondary tags instead of collapsing
// everything into one generic bucket. Host announcements and contract lifecycle events are
// "operations" in their own right even when the same transaction also happens to move
// SC/SF (e.g. to pay the miner fee) — that incidental fund movement isn't a separate
// operation worth its own label once a more specific one is found.
export function classifyTransactionOperations(tx) {
  if (!tx || typeof tx !== 'object') return ['Transaction']
  const ops = []
  const resolutions = Array.isArray(tx.fileContractResolutions) ? tx.fileContractResolutions : []
  const revisions = Array.isArray(tx.fileContractRevisions) ? tx.fileContractRevisions : []
  const formations = Array.isArray(tx.fileContracts) ? tx.fileContracts : []
  const announcements = Array.isArray(tx.hostAnnouncements) ? tx.hostAnnouncements : []
  if (resolutions.length) ops.push(...resolutionOperationLabels(resolutions))
  if (revisions.length) ops.push('Contract revision')
  if (formations.length) ops.push('Contract formation')
  if (announcements.length) ops.push('Host announcement')
  if (ops.length) return ops
  const hasSc = (Array.isArray(tx.siacoinInputs) && tx.siacoinInputs.length) || (Array.isArray(tx.siacoinOutputs) && tx.siacoinOutputs.length)
  const hasSf = (Array.isArray(tx.siafundInputs) && tx.siafundInputs.length) || (Array.isArray(tx.siafundOutputs) && tx.siafundOutputs.length)
  if (hasSc && hasSf) return ['Siacoin & siafund transfer']
  if (hasSc) return ['Siacoin transfer']
  if (hasSf) return ['Siafund transfer']
  if (tx.arbitraryData !== undefined && tx.arbitraryData !== null) return ['Arbitrary data']
  return ['Transaction']
}

export function operationTitle(tx) {
  const [primaryOp, ...secondaryOps] = classifyTransactionOperations(tx)
  return `${esc(primaryOp)}${secondaryOps.map((op) => ` <span class="ldgr-pill">${esc(op)}</span>`).join('')}`
}
