import { pageKey } from './state/router.js'
import { renderError, renderLoading } from './components/view.js'
import { ApiError } from './services/api.js'
import { renderHome } from './routes/home.js'
import { renderBlock } from './routes/block.js'
import { renderTx } from './routes/tx.js'
import { renderAddress } from './routes/address.js'
import { renderOutput } from './routes/output.js'
import { renderContract } from './routes/contract.js'
import { renderBlockMetrics } from './routes/blockMetrics.js'
import { runSearchOrRender } from './routes/search.js'
import {
  renderConsensus,
  renderEvent,
  renderExchange,
  renderHeight,
  renderMetrics,
  renderPeers,
  renderTxpool,
} from './routes/auxiliary.js'

function setContent(html) {
  const el = document.getElementById('explorer-content')
  if (el) el.innerHTML = html
}

// Same pathname, different query string (tabs, filters, pagination) can be swapped in place.
function isSamePageUrl(href) {
  try {
    const target = new URL(href, window.location.href)
    return target.origin === window.location.origin && target.pathname === window.location.pathname
  } catch (_) {
    return false
  }
}

async function rerender() {
  setContent(renderLoading())
  try {
    await run()
  } catch (e) {
    if (e instanceof ApiError) setContent(renderError(e))
    else setContent(renderError({ message: e?.message || 'Unhandled explorer error' }))
  }
  wireSharedActions()
}

function navigateSoft(href) {
  history.pushState(null, '', href)
  return rerender()
}

function wireSharedActions() {
  document.querySelectorAll('[data-copy-value]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copyValue || ''
      try {
        await navigator.clipboard.writeText(value)
        button.textContent = 'Copied'
        button.dataset.state = 'done'
      } catch (_) {
        button.textContent = 'Copy failed'
        button.dataset.state = 'error'
      }
      window.setTimeout(() => {
        button.textContent = 'Copy'
        button.dataset.state = ''
      }, 1200)
    })
  })

  document.querySelectorAll('[data-filter-nav]').forEach((select) => {
    select.addEventListener('change', () => {
      if (!select.value) return
      if (isSamePageUrl(select.value)) navigateSoft(select.value)
      else window.location.href = select.value
    })
  })

  document.querySelector('[data-explorer-retry]')?.addEventListener('click', () => {
    window.location.reload()
  })
}

function wireSoftNavigation() {
  document.getElementById('explorer-content')?.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]')
    if (!link) return
    const href = link.getAttribute('href') || ''
    if (!href || href.startsWith('#')) return
    if (link.target && link.target !== '_self') return
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return
    if (!isSamePageUrl(href)) return
    e.preventDefault()
    navigateSoft(href)
  })

  window.addEventListener('popstate', () => { rerender() })
}

async function run() {
  const page = pageKey()
  switch (page) {
    case 'home':
      return setContent(await renderHome())
    case 'block':
      return setContent(await renderBlock())
    case 'tx':
      return setContent(await renderTx())
    case 'address':
      return setContent(await renderAddress())
    case 'contract':
      return setContent(await renderContract())
    case 'output':
      return setContent(await renderOutput())
    case 'block-metrics':
      return setContent(await renderBlockMetrics())
    case 'search':
      return setContent(await runSearchOrRender())
    case 'height':
      return setContent(await renderHeight())
    case 'event':
      return setContent(await renderEvent())
    case 'txpool':
      return setContent(await renderTxpool())
    case 'consensus':
      return setContent(await renderConsensus())
    case 'metrics':
      return setContent(await renderMetrics())
    case 'peers':
      return setContent(await renderPeers())
    case 'exchange':
      return setContent(await renderExchange())
    default:
      return setContent(await runSearchOrRender())
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  wireSoftNavigation()
  setContent(renderLoading())
  try {
    await run()
    wireSharedActions()
  } catch (e) {
    if (e instanceof ApiError) {
      setContent(renderError(e))
    } else {
      setContent(renderError({ message: e?.message || 'Unhandled explorer error' }))
    }
    wireSharedActions()
  }
})
