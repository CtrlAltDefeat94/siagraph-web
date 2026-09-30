import { renterLink } from '../components/renter.js'
import { getJson } from '../services/api.js'
import { copyButton, rawJson, renderNotFound } from '../components/view.js'
import { q, href, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { id, int, bytes, esc, hastings, dt } from '../formatters/index.js'
import { ledgerIdCell, ledgerFeed } from '../components/ledger.js'

function revisionNumberOf(r) {
  return r?.revisionNumber ?? r?.v2FileContract?.revisionNumber ?? r?.fileContract?.revisionNumber
}

function addHastings(a, b) {
  const toBig = (v) => {
    const raw = String(v ?? '').trim()
    return /^\d+$/.test(raw) ? BigInt(raw) : 0n
  }
  return (toBig(a) + toBig(b)).toString()
}

function metaRow(label, value) {
  return `<div class="entx-meta-row"><span>${esc(label)}</span><span>${value}</span></div>`
}

// Consistent truncated-id rendering for Participants & References: short display text,
// full value on hover/title and via the same subtle copy icon used elsewhere on the page.
function refCell(raw, linkHref, head = 12, tail = 8) {
  const s = String(raw || '').trim()
  if (!s) return ''
  const short = id(s, head, tail)
  const inner = linkHref ? `<a href="${esc(linkHref)}">${short}</a>` : short
  return `<span class="ldgr-id-cell">${inner}${copyButton(s, 'ldgr-inline-copy')}</span>`
}

// Sorted chronologically so an actual resolution slots in correctly relative to expiration.
// A renewal resolves the contract early, so its original proof/expiration/payout schedule
// is superseded and omitted; other resolutions (or an active contract) keep the full schedule.
function buildLifecycleStages({ formationHeight, proofStart, proofEnd, payoutHeight, resolutionHeight, resolutionType, resolutionVariant, isRenewal, tipHeight, estimateTimeForHeight }) {
  const stages = []
  if (Number.isFinite(formationHeight)) stages.push({ label: 'Confirmation', height: formationHeight })
  if (!isRenewal && Number.isFinite(proofStart)) stages.push({ label: 'Proof Window Start', height: proofStart })
  if (resolutionType && Number.isFinite(resolutionHeight)) stages.push({ label: 'Resolved', height: resolutionHeight, variant: resolutionVariant })
  if (!isRenewal && Number.isFinite(proofEnd)) stages.push({ label: 'Expiration', height: proofEnd })
  if (!isRenewal && Number.isFinite(payoutHeight)) stages.push({ label: 'Payout', height: payoutHeight })
  stages.sort((a, b) => a.height - b.height)

  if (!resolutionType && Number.isFinite(tipHeight)) {
    const insertAt = stages.findIndex((s) => s.height > tipHeight)
    const current = { label: 'Current', height: tipHeight, variant: 'current' }
    if (insertAt === -1) stages.push(current)
    else stages.splice(insertAt, 0, current)
  }

  return stages.map((s) => ({ ...s, time: estimateTimeForHeight(s.height), done: Number.isFinite(tipHeight) ? tipHeight >= s.height : false }))
}

export async function renderContract() {
  const cid = String(q('id') || entityIdFromPath('contract', '')).trim()
  if (!cid) return renderNotFound('Contract')

  const type = await getJson(`/search/${encodeURIComponent(cid)}`)
  const isV2 = type === 'v2Contract'
  if (!isV2 && type !== 'contract') return renderNotFound('Contract')

  const base = isV2 ? '/v2/contracts' : '/contracts'
  const [contract, revisions, tip] = await Promise.all([
    getJson(`${base}/${encodeURIComponent(cid)}`),
    getJson(`${base}/${encodeURIComponent(cid)}/revisions`),
    getJson('/consensus/tip').catch(() => null),
  ])
  const tipHeight = Number(tip?.height)
  const tipBlock = tip?.id ? await getJson(`/blocks/${encodeURIComponent(tip.id)}`).catch(() => null) : null
  const tipTimestamp = tipBlock?.timestamp ? new Date(tipBlock.timestamp).getTime() : null
  // Future heights have no real block yet; estimate using the ~10-minute Sia block time.
  const estimateTimeForHeight = (height) => {
    const h = Number(height)
    if (!Number.isFinite(h) || !Number.isFinite(tipHeight) || tipTimestamp === null) return ''
    return dt(tipTimestamp + (h - tipHeight) * 600000)
  }

  const basePath = explorerEntityPath('contract', cid)
  const sortedRevisions = [...(revisions || [])].sort((a, b) => (Number(revisionNumberOf(a)) || 0) - (Number(revisionNumberOf(b)) || 0))
  const latestRevisionEntry = sortedRevisions[sortedRevisions.length - 1]
  const latestRevisionNumber = revisionNumberOf(latestRevisionEntry)
  const requestedRevision = String(q('revision', '')).trim()
  const selectedRevisionEntry = requestedRevision
    ? sortedRevisions.find((r) => String(revisionNumberOf(r)) === requestedRevision) || null
    : null
  const isViewingOutdated = !!selectedRevisionEntry && String(revisionNumberOf(selectedRevisionEntry)) !== String(latestRevisionNumber)

  // The live contract object already reflects its latest revision; an outdated
  // revision instead uses that historical revision entry's own facts.
  const factsSource = isViewingOutdated ? selectedRevisionEntry : contract
  const fc = factsSource?.v2FileContract || factsSource?.fileContract || factsSource || {}
  const filesize = fc?.filesize ?? factsSource?.filesize
  const revisionNumber = fc?.revisionNumber ?? factsSource?.revisionNumber
  const proofStart = isV2 ? fc?.proofHeight : (factsSource?.proofWindowStart ?? factsSource?.resolutionWindowStart)
  const proofEnd = isV2 ? fc?.expirationHeight : (factsSource?.proofWindowEnd ?? factsSource?.resolutionWindowEnd)
  const resolutionType = contract?.resolutionType
  const isRenewal = resolutionType === 'renewal' || (!resolutionType && !!contract?.renewedTo)
  const resolutionLabels = { storage_proof: 'Storage proof submitted', renewal: 'Renewed (contract refresh)' }
  let status = 'Active'
  let statusPillVariant = 'mature'
  if (isRenewal) { status = 'Renewed'; statusPillVariant = 'immature' }
  else if (resolutionType === 'storage_proof') { status = 'Resolved \u00b7 Proof Submitted'; statusPillVariant = 'mature' }
  else if (resolutionType) { status = 'Resolved \u00b7 Missed'; statusPillVariant = 'bad' }

  const formationHeight = contract?.confirmationIndex?.height
  const resolutionHeight = contract?.resolutionIndex?.height
  // Contract funds mature 144 blocks after the proof window closes, whether the proof succeeds or not.
  const payoutHeight = Number.isFinite(proofEnd) ? proofEnd + 144 : null
  const renterAddress = fc?.renterOutput?.address
  const merkleRoot = fc?.fileMerkleRoot
  const onChainRevisionCount = sortedRevisions.length
  const totalPayout = addHastings(fc?.renterOutput?.value, fc?.hostOutput?.value)

  const lifecycleStages = buildLifecycleStages({
    formationHeight, proofStart, proofEnd, payoutHeight, resolutionHeight, resolutionType, resolutionVariant: statusPillVariant, isRenewal, tipHeight, estimateTimeForHeight,
  })

  const outdatedNotice = isViewingOutdated
    ? `<div class="entx-notice"><span class="ldgr-pill ldgr-pill--immature">Outdated revision</span> You're viewing revision #${esc(int(revisionNumberOf(selectedRevisionEntry), '0'))}, not the contract's current state. <a href="${basePath}">View latest revision</a></div>`
    : ''

  // Revisions rarely exceed a handful of on-chain entries, so resolving each one's block
  // height/time (for the revision ledger rows) with a couple of small parallel fetches is cheap.
  const txBase = isV2 ? '/v2/transactions' : '/transactions'
  const reversedRevisions = [...sortedRevisions].reverse()
  const revisionTxIds = [...new Set(reversedRevisions.map((r) => r.transactionID || r?.transaction?.id).filter(Boolean))]
  const revisionIndexByTx = {}
  await Promise.all(revisionTxIds.map(async (txId) => {
    try {
      const list = await getJson(`${txBase}/${encodeURIComponent(txId)}/indices?offset=0&limit=1`)
      revisionIndexByTx[txId] = Array.isArray(list) && list[0] ? list[0] : null
    } catch { revisionIndexByTx[txId] = null }
  }))
  const revisionBlockIds = [...new Set(Object.values(revisionIndexByTx).map((idx) => idx?.id).filter(Boolean))]
  const revisionBlockById = {}
  await Promise.all(revisionBlockIds.map(async (bid) => {
    try { revisionBlockById[bid] = await getJson(`/blocks/${encodeURIComponent(bid)}`) } catch { revisionBlockById[bid] = null }
  }))

  const revisionRows = reversedRevisions.map((r, idx) => {
    const txId = r.transactionID || r?.transaction?.id || ''
    const rNumber = revisionNumberOf(r)
    const rFc = r?.v2FileContract || r?.fileContract || {}
    const rMerkle = rFc?.fileMerkleRoot
    const isLatestRow = String(rNumber) === String(latestRevisionNumber)
    const isSelectedRow = isViewingOutdated && String(rNumber) === String(revisionNumberOf(selectedRevisionEntry))
    const statusHtml = isSelectedRow
      ? '<span class="ldgr-pill ldgr-pill--immature">Viewing</span>'
      : (isLatestRow ? '<span class="ldgr-pill ldgr-pill--mature">Latest</span>' : '')
    const revIndex = revisionIndexByTx[txId]
    const revBlock = revIndex?.id ? revisionBlockById[revIndex.id] : null
    const revWhen = revIndex ? `${int(revIndex.height, '')}${revBlock?.timestamp ? ` \u00b7 ${dt(revBlock.timestamp)}` : ''}` : ''
    return {
      kind: 'REV',
      title: `<a href="${href(basePath, isLatestRow ? {} : { revision: rNumber })}">Revision #${int(rNumber, String(sortedRevisions.length - idx))}</a>`,
      meta: [txId ? ledgerIdCell(explorerEntityPath('tx', txId), txId) : '', revWhen, rMerkle ? `Merkle ${esc(rMerkle.slice(0, 10))}...` : ''],
      amount: `<div class="ldgr-amount-cell"><div class="ldgr-amount-primary">${bytes(rFc?.filesize, 'N/A')}</div></div>`,
      status: statusHtml,
    }
  })

  const formationTx = contract?.confirmationTransactionID || revisions?.[0]?.transactionID
  const resolutionTx = contract?.resolutionTransactionID
  const renewedFrom = contract?.renewedFrom
  const hpk = fc.hostPublicKey || fc.hostkey || contract.hostPublicKey || contract.hostkey

  const participantRows = []
  if (hpk) participantRows.push(metaRow('Host', refCell(hpk, `/host?public_key=${encodeURIComponent(hpk)}`, 16, 10)))
  const renter = renterLink(contract)
  if (renter) participantRows.push(metaRow('Renter', renter))

  const referenceRows = []
  if (formationTx) referenceRows.push(metaRow('Formation Transaction', refCell(formationTx, explorerEntityPath('tx', formationTx))))
  if (renewedFrom) referenceRows.push(metaRow('Renewed From', refCell(renewedFrom, explorerEntityPath('contract', renewedFrom))))
  if (renterAddress) referenceRows.push(metaRow('Renter Address', refCell(renterAddress, explorerEntityPath('address', renterAddress))))
  if (merkleRoot) referenceRows.push(metaRow('Merkle Root', refCell(merkleRoot, '')))
  if (resolutionType) {
    const resolutionLabel = resolutionLabels[resolutionType] || esc(String(resolutionType))
    referenceRows.push(metaRow('Resolution', `${resolutionLabel}${resolutionTx ? ` &middot; ${refCell(resolutionTx, explorerEntityPath('tx', resolutionTx))}` : ''}`))
  }

  const renterPayoutValue = fc?.renterOutput?.value
  const hostPayoutValid = fc?.hostOutput?.value
  const hostPayoutMissed = fc?.missedHostValue

  return `
  <section class="cntx-page">
    <style>
      .entx-notice{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;padding:.6rem .8rem;border:1px solid rgba(251,191,36,.35);border-radius:8px;background:rgba(251,191,36,.08);color:var(--sg-text-muted);font-size:.86rem}
      .entx-notice a{color:var(--sg-heading-accent);font-weight:600}
      .cntx-header{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}
      .cntx-header .entx-title{margin:0}
      .cntx-id-cell{display:inline-flex;align-items:center;gap:4px;min-width:0;color:var(--sg-text-muted)}
      .cntx-id-cell .ldgr-inline-copy{opacity:.5;transition:opacity 120ms ease}
      .cntx-id-cell:hover .ldgr-inline-copy,.cntx-id-cell:focus-within .ldgr-inline-copy{opacity:1}
      .entx-two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem}
      .entx-two-col h4{margin:0 0 .5rem;color:var(--sg-text-muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.03em;font-weight:600}
      .entx-two-col--proof h4{margin-bottom:.2rem}
      .entx-proof-shared{margin:.35rem 0 1.1rem;color:var(--sg-text-muted);font-size:.82rem}
      .entx-proof-desc{margin:.5rem 0 0;color:var(--sg-text-faint);font-size:.78rem}
      .entx-meta-row{display:grid;grid-template-columns:170px minmax(0,1fr);gap:10px;padding:7px 0;border-bottom:1px solid var(--sg-border-subtle)}
      .entx-meta-row:last-child{border-bottom:0}
      .entx-meta-row > span:first-child{color:var(--sg-text-faint);font-size:.86rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600}
      .entx-meta-row > span:last-child{display:block;min-width:0;color:var(--sg-text-strong);word-break:break-word}
      .entx-meta-row .ldgr-id-cell{display:flex;max-width:100%;min-width:0}
      .entx-meta-row .explorer-code{max-width:100%}
      .cntx-lifecycle{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;margin:1.25rem 0}
      .cntx-stage{display:flex;flex-direction:column;justify-content:center;padding:.35rem .65rem;border-left:2px solid var(--sg-border-subtle);min-width:130px}
      .cntx-stage--done{border-left-color:var(--sg-border-accent)}
      .cntx-stage--good{border-left-color:#4ade80}
      .cntx-stage--bad{border-left-color:#f87171}
      .cntx-stage--immature{border-left-color:#fbbf24}
      .cntx-stage--current{border-left-color:#fbbf24;background:rgba(251,191,36,.08);border-radius:0 4px 4px 0}
      .cntx-stage-label{font-size:.66rem;text-transform:uppercase;letter-spacing:.03em;color:var(--sg-text-muted)}
      .cntx-stage-value{font-size:.84rem;color:var(--sg-text-strong);margin-top:2px;white-space:nowrap}
      .cntx-arrow{align-self:center;color:var(--sg-text-faint);font-size:.8rem}
      .cntx-page .entx-section{margin-top:2.25rem;padding-top:1.25rem;border-top:1px solid var(--sg-border-subtle)}
      @media (max-width: 700px){.entx-two-col{grid-template-columns:1fr}.cntx-lifecycle{flex-direction:column;align-items:stretch}.cntx-arrow{display:none}}
    </style>
    ${outdatedNotice}

    <div class="cntx-header">
      <h2 class="entx-title">Contract</h2>
      <span class="cntx-id-cell">${id(cid)}${copyButton(cid, 'ldgr-inline-copy')}</span>
      <span class="ldgr-pill ldgr-pill--${esc(statusPillVariant)}">${esc(status)}</span>
      <span class="ldgr-pill">${isV2 ? 'V2' : 'V1'}</span>
    </div>

    <div class="entx-kpis">
      <article class="entx-kpi"><small>Current Filesize</small><b>${bytes(filesize, 'N/A')}</b></article>
      <article class="entx-kpi"><small>Payout (Valid)</small><b>${hastings(totalPayout, '0 SC')}</b></article>
      <article class="entx-kpi"><small>Revision Number</small><b>${int(revisionNumber, '0')}</b></article>
      <article class="entx-kpi" title="Confirmed revision transactions found on-chain; the protocol revision counter advances far more often off-chain between renter and host."><small>On-chain Revisions</small><b>${int(onChainRevisionCount, '0')}</b></article>
    </div>

    <div class="cntx-lifecycle">
      ${lifecycleStages.map((s, i) => {
        const arrow = i > 0 ? '<span class="cntx-arrow">\u2192</span>' : ''
        const variantClass = s.variant === 'current' ? 'cntx-stage--current' : (s.variant === 'mature' ? 'cntx-stage--good' : (s.variant === 'bad' ? 'cntx-stage--bad' : (s.variant === 'immature' ? 'cntx-stage--immature' : (s.done ? 'cntx-stage--done' : ''))))
        return `${arrow}<div class="cntx-stage ${variantClass}"><span class="cntx-stage-label">${esc(s.label)}</span><span class="cntx-stage-value">${int(s.height, 'N/A')}${s.time ? ` \u00b7 ${s.time}` : ''}</span></div>`
      }).join('')}
    </div>

    <section class="entx-section">
      <div class="entx-section-head"><h3>Participants &amp; References</h3></div>
      <div class="entx-two-col">
        <div>
          <h4>Participants</h4>
          ${participantRows.join('') || '<div class="ldgr-empty">No participant data.</div>'}
        </div>
        <div>
          <h4>References</h4>
          ${referenceRows.join('') || '<div class="ldgr-empty">No reference data.</div>'}
        </div>
      </div>
    </section>

    <section class="entx-section">
      <div class="entx-section-head"><h3>Proof Outputs</h3></div>
      <p class="entx-proof-shared">Remaining renter allowance is returned in either outcome; host payout depends on successful proof.</p>
      <div class="entx-two-col entx-two-col--proof">
        <div>
          <h4>Valid &mdash; storage proof succeeds</h4>
          ${metaRow('Renter payout', hastings(renterPayoutValue, '0 SC'))}
          ${metaRow('Host payout', hastings(hostPayoutValid, '0 SC'))}
          <p class="entx-proof-desc">Full contract payout to the host.</p>
        </div>
        <div>
          <h4>Missed &mdash; storage proof fails or expires</h4>
          ${metaRow('Renter payout', hastings(renterPayoutValue, '0 SC'))}
          ${metaRow('Host payout', hastings(hostPayoutMissed, '0 SC'))}
          <p class="entx-proof-desc">Payout minus the collateral the host risked and forfeits.</p>
        </div>
      </div>
    </section>

    <section class="entx-section">
      <div class="entx-section-head"><h3>Revisions</h3><span>${int(revisionRows.length)}</span></div>
      ${ledgerFeed(revisionRows, 'Contract revisions')}
    </section>

    <section class="entx-section">${rawJson('Raw Contract JSON', contract)}</section>
  </section>`
}

