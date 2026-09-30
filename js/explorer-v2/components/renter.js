import { esc, id } from '../formatters/index.js'

// Resolve on navigation, so transaction lists do not issue a request per key.
// The details API resolves the canonical wallet while the public-key URL is retained.
export function renterLink(contract) {
  const value = contract?.v2FileContract || contract
  const key = value?.renterPublicKey || value?.renter_public_key
  const wallet = value?.renterWalletAddress || value?.renter_wallet_address || value?.renterOutput?.address
  if (!key && !wallet) return ''
  const params = new URLSearchParams()
  if (key) params.set('public_key', key)
  if (wallet) params.set('address', wallet)
  return `<a href="/renter?${esc(params.toString())}" title="View renter wallet">${id(key || wallet, 16, 10)}</a>`
}

export function transactionRenterLinks(tx) {
  const links = new Set()
  const visit = (value, depth = 0) => {
    if (!value || typeof value !== 'object' || depth > 12) return
    const link = renterLink(value)
    if (link) links.add(link)
    Object.values(value).forEach(child => {
      if (Array.isArray(child)) child.forEach(item => visit(item, depth + 1))
      else if (child && typeof child === 'object') visit(child, depth + 1)
    })
  }
  // Limit traversal to contract-related data; never infer identity from generic outputs.
  for (const key of ['fileContracts', 'fileContractRevisions', 'fileContractResolutions']) visit(tx?.[key])
  return [...links]
}
