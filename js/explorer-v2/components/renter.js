import { esc, id } from '../formatters/index.js'

// Resolve on navigation, so transaction lists do not issue a request per key.
// The details API resolves the canonical wallet while the public-key URL is retained.
function renterIdentity(contract) {
  const value = contract?.v2FileContract || contract
  const key = value?.renterPublicKey || value?.renter_public_key
  const wallet = value?.renterWalletAddress || value?.renter_wallet_address || value?.renterOutput?.address
  if (!key && !wallet) return null
  const params = new URLSearchParams()
  if (key) params.set('public_key', key)
  if (wallet) params.set('address', wallet)
  return { href: `/renter?${params.toString()}`, raw: key || wallet }
}

export function renterLink(contract) {
  const entry = renterIdentity(contract)
  if (!entry) return ''
  return `<a href="${esc(entry.href)}" title="View renter wallet">${id(entry.raw, 16, 10)}</a>`
}

// Structured (href, full raw id) pairs for ledger-row rendering, deduped by href.
export function transactionRenterEntries(tx) {
  const seen = new Map()
  const visit = (value, depth = 0) => {
    if (!value || typeof value !== 'object' || depth > 12) return
    const entry = renterIdentity(value)
    if (entry) seen.set(entry.href, entry)
    Object.values(value).forEach(child => {
      if (Array.isArray(child)) child.forEach(item => visit(item, depth + 1))
      else if (child && typeof child === 'object') visit(child, depth + 1)
    })
  }
  // Limit traversal to contract-related data; never infer identity from generic outputs.
  for (const key of ['fileContracts', 'fileContractRevisions', 'fileContractResolutions']) visit(tx?.[key])
  return [...seen.values()]
}
