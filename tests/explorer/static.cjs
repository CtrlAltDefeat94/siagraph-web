const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')

const root = path.resolve(__dirname, '../..')
const read = file => fs.readFileSync(path.join(root, file), 'utf8')
const shell = read('explorer/_page.php')
const view = read('js/explorer-v2/components/view.js')
const app = read('js/explorer-v2/app.js')
const css = read('css/pages/explorer.css')
const routeFiles = fs.readdirSync(path.join(root, 'js/explorer-v2/routes')).filter(file => file.endsWith('.js'))

assert.match(shell, /css\/components\/data-page\.css/)
assert.match(shell, /sg-data-page/)
assert.equal(fs.existsSync(path.join(root, 'explorer/index.php')), false)
assert.match(view, /export function renderLoading/)
assert.match(view, /export function renderNotFound/)
assert.match(view, /export function copyButton/)
assert.match(view, /export function rawJson/)
assert.match(app, /data-explorer-retry/)
assert.match(css, /@media \(max-width: 768px\)/)
assert.match(view, /data-label=/)

for (const file of routeFiles) {
  assert.equal(read(`js/explorer-v2/routes/${file}`).includes('onclick='), false, `${file} contains an inline event handler`)
}

console.log('PASS explorer shell, shared states, copy/raw helpers, responsive tables, and integrated entry point')
