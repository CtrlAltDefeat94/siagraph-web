import { getJson } from '../services/api.js'
import { section, kv, rawJson } from '../components/view.js'
import { q, href } from '../state/router.js'

export async function renderBlockMetrics() {
  const start = q('start')
  const end = q('end')

  const [block, blocktime] = await Promise.all([
    getJson('/metrics/block'),
    getJson('/metrics/blocktime'),
  ])

  let difficulty = '<p class="text-muted">Provide start/end heights to load difficulty metrics.</p>'
  if (start && end) {
    const diff = await getJson(`/metrics/difficulty?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
    difficulty = rawJson('Difficulty JSON', diff)
  }

  const form = `<form class="explorer-inline-form" method="get" action="${href('/block-metrics')}">
    <input type="number" name="start" placeholder="start height" value="${start || ''}" required>
    <input type="number" name="end" placeholder="end height" value="${end || ''}" required>
    <button class="btn btn-sm btn-brand" type="submit">Load</button>
  </form>`

  return section('Tip Block Metrics', kv(block || {})) +
    section('Block Time Metrics', kv(blocktime || {})) +
    section('Difficulty', form + difficulty)
}
