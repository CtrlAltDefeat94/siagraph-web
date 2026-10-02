import { getJson } from '../services/api.js'
import { q, href, explorerEntityPath, entityIdFromPath } from '../state/router.js'
import { section, renderNotFound } from '../components/view.js'

export async function runSearchOrRender() {
  const value = String(q('id') || entityIdFromPath('search', '')).trim()
  if (!value) {
    return section('Search', `
      <form class="explorer-search" method="get" action="${href('/search')}">
        <input class="form-control" name="id" placeholder="Search block/tx/address/contract/output id" required>
        <button class="btn btn-brand" type="submit">Search</button>
      </form>
    `)
  }

  if (/^\d+$/.test(value)) {
    try {
      const index = await getJson(`/consensus/tip/${encodeURIComponent(value)}`)
      window.location.replace(explorerEntityPath('block', index.id))
      return section('Search', '<p>Redirecting to block...</p>')
    } catch (_) {}
  }

  const type = await getJson(`/search/${encodeURIComponent(value)}`)
  if (type === 'host') {
    window.location.replace(`/host?public_key=${encodeURIComponent(value)}`)
    return section('Search', '<p>Redirecting to host page...</p>')
  }

  const routes = {
    block: explorerEntityPath('block', value),
    transaction: explorerEntityPath('tx', value),
    v2Transaction: explorerEntityPath('tx', value),
    address: explorerEntityPath('address', value),
    siacoinElement: explorerEntityPath('output', value),
    siafundElement: explorerEntityPath('output', value),
    contract: explorerEntityPath('contract', value),
    v2Contract: explorerEntityPath('contract', value),
  }

  if (routes[type]) {
    window.location.replace(routes[type])
    return section('Search', '<p>Redirecting...</p>')
  }

  return renderNotFound('Search result')
}
