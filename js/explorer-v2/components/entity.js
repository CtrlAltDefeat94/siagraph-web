import { esc } from '../formatters/index.js'
import { copyButton } from './view.js'

// Flat identity header shared by every explorer entity page (address, block, transaction, output, contract).
// Mirrors the flat, borderless KPI treatment already used by the statistics dashboards.
export function entityHero({ title, idLabel = 'Identifier', idValue, nav = '', kpis = [], compactId = false }) {
  const kpiHtml = kpis.map(([label, value]) => `<article class="entx-kpi"><small>${esc(label)}</small><b>${value}</b></article>`).join('')
  return `
  <section class="entx-hero">
    <div class="entx-hero-head">
      <h2 class="entx-title">${esc(title)}</h2>
      ${compactId && idValue ? `<span class="entx-compact-id" title="${esc(idValue)}"><span class="entx-compact-id-label">${esc(idLabel)}</span><span class="entx-compact-id-text">${esc(idValue)}</span>${copyButton(idValue, 'entx-copy', 'Copy')}</span>` : ''}
      ${nav}
    </div>
    ${idValue && !compactId ? `
    <div class="entx-id">
      <span class="entx-id-label">${esc(idLabel)}</span>
      <div class="entx-id-row">
        <div class="entx-id-value">${esc(idValue)}</div>
        ${copyButton(idValue, 'entx-copy', 'Copy')}
      </div>
    </div>` : ''}
    ${kpiHtml ? `<div class="entx-kpis">${kpiHtml}</div>` : ''}
  </section>`
}
