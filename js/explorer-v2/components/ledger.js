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
export function ledgerRow({ kind, title, meta = [], amount = '', status = '', pending = false, immature = false }) {
  const metaHtml = meta.filter(Boolean).join('<span class="ldgr-meta-separator" aria-hidden="true">·</span>')
  const classes = ['ldgr-item', pending ? 'ldgr-item--pending' : '', immature ? 'ldgr-item--immature' : ''].filter(Boolean).join(' ')
  return `
    <article class="${classes}">
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

// Classifies a raw v1/v2 transaction body by its most significant effect, matching how
// other Sia explorers describe transactions (siacoin transfer, contract revision, etc).
export function classifyTransactionKind(tx) {
  if (!tx || typeof tx !== 'object') return 'Transaction'
  if (Array.isArray(tx.fileContractResolutions) && tx.fileContractResolutions.length) {
    // A "renewal" resolution refreshes an existing contract rather than settling it.
    return tx.fileContractResolutions.some((r) => r?.type === 'renewal') ? 'Contract refresh' : 'Contract resolution'
  }
  if (Array.isArray(tx.fileContractRevisions) && tx.fileContractRevisions.length) return 'Contract revision'
  if (Array.isArray(tx.fileContracts) && tx.fileContracts.length) return 'Contract formation'
  const hasSc = (Array.isArray(tx.siacoinInputs) && tx.siacoinInputs.length) || (Array.isArray(tx.siacoinOutputs) && tx.siacoinOutputs.length)
  const hasSf = (Array.isArray(tx.siafundInputs) && tx.siafundInputs.length) || (Array.isArray(tx.siafundOutputs) && tx.siafundOutputs.length)
  if (hasSc && hasSf) return 'Siacoin & siafund transfer'
  if (hasSc) return 'Siacoin transfer'
  if (hasSf) return 'Siafund transfer'
  if (tx.arbitraryData !== undefined && tx.arbitraryData !== null) return 'Arbitrary data'
  return 'Transaction'
}

export function ledgerFeed(rows, ariaLabel, emptyMessage = 'No rows.') {
  if (!rows.length) return `<div class="ldgr-empty">${esc(emptyMessage)}</div>`
  return `<div class="ldgr-feed" aria-label="${esc(ariaLabel)}">${rows.map(ledgerRow).join('')}</div>`
}
