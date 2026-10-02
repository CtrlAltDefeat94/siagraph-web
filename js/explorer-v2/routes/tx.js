import { renterLink, transactionRenterEntries } from '../components/renter.js'
import { getJson } from '../services/api.js'
import { q, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { int, hastings, esc, bytes, dt } from '../formatters/index.js'
import { copyButton, rawJson, renderNotFound } from '../components/view.js'
import { ledgerIdCell, ledgerAmountCell, ledgerFeed, operationTitle, RESOLUTION_OPERATION_LABELS } from '../components/ledger.js'
import { entityHero } from '../components/entity.js'

function amountSc(value) {
  return hastings(value ?? '0', '0 SC')
}
function amountSf(value) {
  return `${int(value ?? 0, '0')} SF`
}

function compactIdCell(raw, href) {
  const s = String(raw || '').trim()
  if (!s) return ''
  const text = `<span class="ldgr-id-text" title="${esc(s)}">${esc(s)}</span>`
  const inner = href ? `<a href="${esc(href)}">${text}</a>` : text
  return `<span class="ldgr-id-cell txx-id-cell" title="${esc(s)}">${inner}${copyButton(s, 'ldgr-inline-copy')}</span>`
}

// v1 and v2 file contracts expose the same facts under different field names.
function normalizeFileContract(entry, isV2) {
  const fc = entry?.v2FileContract || entry?.fileContract || entry || {}
  return {
    filesize: fc?.filesize,
    proofEnd: isV2 ? fc?.expirationHeight : (entry?.proofWindowEnd ?? fc?.windowEnd),
    hostPublicKey: fc?.hostPublicKey || fc?.hostkey || '',
    revisionNumber: fc?.revisionNumber,
  }
}

// v2 host announcements carry one or more protocol/address pairs; fall back to a flat
// netAddress for any legacy/v1 shape. Returns [] (never a placeholder) when undecodable.
function hostAnnouncementEndpoints(h) {
  const list = h?.V2HostAnnouncement || h?.v2HostAnnouncement
  if (Array.isArray(list) && list.length) {
    return list.map((e) => ({ protocol: e?.protocol || '', address: String(e?.address || '').trim() })).filter((e) => e.address)
  }
  const flat = h?.netAddress || h?.net_address || h?.address
  return flat ? [{ protocol: '', address: String(flat).trim() }] : []
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
  const primaryIndex = indexList.length ? indexList.reduce((min, idx) => (Number(idx?.height) < Number(min?.height) ? idx : min), indexList[0]) : null
  const confirmationHeight = Number.isFinite(Number(primaryIndex?.height)) ? Number(primaryIndex.height) : null
  const tipHeight = Number(tip?.height)
  const confirmations = confirmed && confirmationHeight !== null && Number.isFinite(tipHeight) ? Math.max(1, tipHeight - confirmationHeight + 1) : null
  // One extra lookup (only when confirmed) so the summary can show when the transaction happened, not just where.
  const primaryBlock = confirmed && primaryIndex?.id ? await getJson(`/blocks/${encodeURIComponent(primaryIndex.id)}`).catch(() => null) : null
  const feeValue = isV2
    ? (tx?.minerFee || tx?.minerFees?.[0] || '0')
    : ((Array.isArray(tx?.minerFees) && tx.minerFees.length ? tx.minerFees[0] : tx?.minerFee) || '0')

  const scIn = Array.isArray(tx?.siacoinInputs) ? tx.siacoinInputs : []
  const scOut = Array.isArray(tx?.siacoinOutputs) ? tx.siacoinOutputs : []
  const sfIn = Array.isArray(tx?.siafundInputs) ? tx.siafundInputs : []
  const sfOut = Array.isArray(tx?.siafundOutputs) ? tx.siafundOutputs : []
  // Only disambiguate with an asset prefix when both assets actually appear together.
  const mixedAssets = (scIn.length || scOut.length) > 0 && (sfIn.length || sfOut.length) > 0
  const scInKind = mixedAssets ? 'SC IN' : 'IN'
  const scOutKind = mixedAssets ? 'SC OUT' : 'OUT'
  const sfInKind = mixedAssets ? 'SF IN' : 'IN'
  const sfOutKind = mixedAssets ? 'SF OUT' : 'OUT'

  const inputRows = []
  const outputRows = []
  scIn.forEach((inp) => {
    const n = normalizeScInput(inp)
    inputRows.push({
      kind: scInKind,
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.parentId ? ledgerIdCell(explorerEntityPath('output', n.parentId), n.parentId) : ''],
      amount: ledgerAmountCell(amountSc(n.value)),
    })
  })
  scOut.forEach((out) => {
    const n = normalizeScOutput(out)
    outputRows.push({
      kind: scOutKind,
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.outputId ? ledgerIdCell(explorerEntityPath('output', n.outputId), n.outputId) : ''],
      amount: ledgerAmountCell(amountSc(n.value)),
    })
  })
  sfIn.forEach((inp) => {
    const n = normalizeSfInput(inp)
    inputRows.push({
      kind: sfInKind,
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.parentId ? ledgerIdCell(explorerEntityPath('output', n.parentId), n.parentId) : ''],
      amount: ledgerAmountCell(amountSf(n.value)),
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
      kind: sfOutKind,
      title: n.address ? ledgerIdCell(explorerEntityPath('address', n.address), n.address) : 'N/A',
      meta: [n.outputId ? ledgerIdCell(explorerEntityPath('output', n.outputId), n.outputId) : ''],
      amount: ledgerAmountCell(amountSf(n.value)),
    })
  })

  const renterRows = transactionRenterEntries(tx).map(({ href, raw }) => ({ kind: 'RENTER', title: ledgerIdCell(href, raw) }))

  // Decode the actual announced endpoint rather than showing only the public key; an
  // undecodable announcement gets an explicit "couldn't decode" row, never a silent N/A.
  const hostRows = hostAnnouncements.map((h) => {
    const pk = h?.publicKey || h?.public_key || ''
    const endpoints = hostAnnouncementEndpoints(h)
    const primary = endpoints.find((e) => e.protocol === 'siamux') || endpoints[0] || null
    const secondary = endpoints.filter((e) => e !== primary)
    const meta = [
      pk ? ledgerIdCell(`/host?public_key=${encodeURIComponent(pk)}`, pk) : '',
      ...secondary.map((e) => esc(`${e.protocol ? `${e.protocol} ` : ''}${e.address}`)),
    ]
    return {
      kind: 'HOST',
      title: primary ? esc(primary.address) : (pk ? 'Endpoint could not be decoded' : 'N/A'),
      meta,
    }
  })

  const formationRows = (Array.isArray(tx?.fileContracts) ? tx.fileContracts : []).map((entry) => {
    const f = normalizeFileContract(entry, isV2)
    const contractId = entry?.id || ''
    const meta = [
      f.hostPublicKey ? ledgerIdCell(`/host?public_key=${encodeURIComponent(f.hostPublicKey)}`, f.hostPublicKey) : '',
      renterLink(entry),
      Number.isFinite(Number(f.proofEnd)) ? `Expires at ${int(f.proofEnd)}` : '',
    ]
    return {
      kind: 'FORM',
      title: contractId ? ledgerIdCell(explorerEntityPath('contract', contractId), contractId) : 'N/A',
      meta,
      amount: ledgerAmountCell(bytes(f.filesize, '0 bytes')),
    }
  })

  const revisionRows = (Array.isArray(tx?.fileContractRevisions) ? tx.fileContractRevisions : []).map((entry) => {
    const parent = entry?.parent || {}
    const revisionEntry = entry?.revision || entry
    const f = normalizeFileContract(revisionEntry, isV2)
    const contractId = parent?.id || revisionEntry?.id || ''
    const meta = [
      Number.isFinite(Number(f.revisionNumber)) ? `Revision #${int(f.revisionNumber)}` : '',
      renterLink(revisionEntry),
    ]
    return {
      kind: 'REV',
      title: contractId ? ledgerIdCell(explorerEntityPath('contract', contractId), contractId) : 'N/A',
      meta,
      amount: ledgerAmountCell(bytes(f.filesize, '0 bytes')),
    }
  })

  const resolutionRows = (Array.isArray(tx?.fileContractResolutions) ? tx.fileContractResolutions : []).map((entry) => {
    const parent = entry?.parent || {}
    const contractId = parent?.id || ''
    const resType = entry?.type || parent?.resolutionType || ''
    const meta = [
      RESOLUTION_OPERATION_LABELS[resType] || (resType ? esc(String(resType)) : ''),
      parent?.renewedTo ? ledgerIdCell(explorerEntityPath('contract', parent.renewedTo), parent.renewedTo) : '',
    ]
    return {
      kind: 'RES',
      title: contractId ? ledgerIdCell(explorerEntityPath('contract', contractId), contractId) : 'N/A',
      meta,
    }
  })

  const kpis = [
    ['Type', operationTitle(tx)],
    ['Version', isV2 ? 'V2' : 'V1'],
    ['Status', confirmed ? '<span class="txx-pill txx-pill--good">Confirmed</span>' : '<span class="txx-pill txx-pill--warn">Pending</span>'],
    ['Timestamp', primaryBlock?.timestamp ? dt(primaryBlock.timestamp) : 'Pending'],
    ['Confirmations', confirmations !== null ? int(confirmations) : 'N/A'],
    ['Miner Fee', amountSc(feeValue)],
  ]

  const inclusionRows = indexList.map((idx) => {
    const height = Number(idx?.height)
    const heightHtml = Number.isFinite(height) ? `<a href="${explorerEntityPath('height', height)}">${int(height)}</a>` : 'N/A'
    const idCell = idx?.id ? compactIdCell(idx.id, explorerEntityPath('block', idx.id)) : ''
    return `<div class="txx-inclusion-row">Block ${heightHtml}${idCell ? ` <span class="txx-inclusion-sep" aria-hidden="true">\u00b7</span> ${idCell}` : ''}</div>`
  })

  const operationSection = (title, rows, ariaLabel) => (rows.length ? `
      <section class="entx-section">
        <div class="entx-section-head"><h3>${esc(title)}</h3><span>${int(rows.length)}</span></div>
        ${ledgerFeed(rows, ariaLabel)}
      </section>` : '')

  return `
    <style>
      .txx-pill{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:.8rem;font-weight:600;border:1px solid transparent;white-space:nowrap}
      .txx-pill--good{color:#86efac;border-color:rgba(22,163,74,.45);background:rgba(22,163,74,.16)}
      .txx-pill--warn{color:#fde68a;border-color:rgba(251,191,36,.45);background:rgba(251,191,36,.14)}
      .txx-io-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.5rem}
      .txx-inclusion-row{padding:.3rem 0;color:var(--sg-text-strong);font-size:.88rem}
      .txx-inclusion-row a{color:var(--sg-heading-accent)}
      .txx-inclusion-sep{color:var(--sg-text-faint);margin:0 .1rem}
      @media (max-width: 1200px){
        .txx-io-grid{grid-template-columns:1fr}
      }
    </style>

    <section>
      ${entityHero({ title: 'Transaction', nav: compactIdCell(tx?.id || txid, ''), kpis })}

      <section class="entx-section">
        <div class="entx-section-head"><h3>Chain Inclusion</h3>${indexList.length ? `<span>${int(indexList.length)} block${indexList.length === 1 ? '' : 's'}</span>` : ''}</div>
        ${inclusionRows.length ? inclusionRows.join('') : '<div class="ldgr-empty">Not yet confirmed \u2014 waiting in the mempool.</div>'}
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

      ${operationSection('Contract Formation', formationRows, 'Contract formation')}
      ${operationSection('Contract Revision', revisionRows, 'Contract revision')}
      ${operationSection('Contract Resolution', resolutionRows, 'Contract resolution')}
      ${operationSection('Host Announcements', hostRows, 'Host announcements')}
      ${operationSection('Renters', renterRows, 'Renters')}

      <section class="entx-section">${rawJson('Raw Transaction JSON', tx)}</section>
    </section>
  `
}
