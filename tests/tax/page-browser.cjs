const fs = require('fs'), cp = require('child_process'), os = require('os'), path = require('path');
const root = path.resolve(__dirname, '../..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'host-revenue-page-'));
const key = 'b'.repeat(64);
try {
  for (const width of [390, 1280]) {
    let html = cp.execFileSync('php', ['-r', `$_GET['public_key']='ed25519:${key.toUpperCase()}'; require '${root}/host_revenue_export.php';`], {encoding: 'utf8'});
    html = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g, '').replace(/<link\b[^>]*>/g, '');
    const css = ['css/dark.css', 'css/style.css', 'css/theme.css', 'css/overrides.css', 'css/pages/transactions-export.css'].map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\n');
    html = html.replace('</head>', `<style>${css}</style></head>`);
    const setup = `
      const errors = []; addEventListener('error', e => errors.push(e.message));
      addEventListener('unhandledrejection', e => errors.push(String(e.reason)));
      const requests = [], downloads = [], opened = []; let mode = 'csv';
      window.open = (...args) => opened.push(args);
      HTMLAnchorElement.prototype.click = function () { downloads.push({name: this.download, href: this.href}); };
      window.fetch = async (url) => {
        requests.push(url);
        return {ok: mode !== 'error', headers: {get: () => mode === 'csv' ? 'text/csv' : 'application/json'},
          blob: async () => new Blob(['contract_id,collateral_lost_sc\\r\\nabc,2.000000000000000000000000\\r\\n'], {type:'text/csv'}),
          json: async () => ({errors:[{message:'Test export unavailable.'}]})};
      };
    `;
    const checks = `
      const pause = () => new Promise(resolve => setTimeout(resolve, 50));
      const check = (ok, label) => { if (!ok) throw Error(label); };
      addEventListener('DOMContentLoaded', async () => {
        const result = document.createElement('pre'); result.id = 'test-result';
        try {
          const form = document.getElementById('host-revenue-export-form');
          const key = form.elements.host_public_key, from = form.elements.from, to = form.elements.to;
          const currency = form.elements.currency, download = document.getElementById('revenue-download-btn'), raw = document.getElementById('revenue-raw-btn');
          check(key.value === '${key}', 'host key normalized and prefilled');
          check(from.value === '2025-07-01' && currency.value === 'eur', 'report defaults');
          const yesterday = new Date(); yesterday.setUTCDate(yesterday.getUTCDate()-1);
          check(to.value === yesterday.toISOString().slice(0,10), 'yesterday UTC default');
          check(currency.options.length === 9, 'all rate currencies');
          check(document.querySelector('a[href="/host?public_key=${key}"]'), 'back to host');
          key.value = 'invalid'; download.click(); await pause(); check(!requests.length, 'invalid key blocked');
          key.value = '${key}'; from.value = '2025-06-30'; download.click(); await pause(); check(!requests.length, 'pre-V2 date blocked');
          from.value = '2026-09-02'; to.value = '2026-09-01'; to.dispatchEvent(new Event('input')); download.click(); await pause(); check(!requests.length && !to.checkValidity(), 'reversed dates blocked');
          to.value = '2026-09-03'; to.dispatchEvent(new Event('input')); currency.value = 'gbp';
          download.click(); check(download.disabled && raw.disabled, 'buttons disabled during download'); await pause();
          const query = new URL(requests[0], 'https://test.local').searchParams;
          check(query.get('format') === 'csv' && query.get('currency') === 'gbp' && query.get('host_public_key') === '${key}', 'CSV request scope and currency');
          check(query.get('from') === '2026-09-02' && query.get('to') === '2026-09-03', 'inclusive date parameters');
          check(downloads.length === 1 && downloads[0].name === 'host-revenue_${key}_2026-09-02_2026-09-03_gbp.csv', 'download filename');
          check(!download.disabled && !raw.disabled, 'buttons restored');
          raw.click(); await pause(); check(opened.length === 1 && opened[0][0].includes('format=json') && opened[0][2] === 'noopener,noreferrer', 'raw JSON opens safely');
          mode = 'error'; download.click(); await pause(); check(document.getElementById('revenue-status').textContent === 'Test export unavailable.' && downloads.length === 1, 'API envelope errors shown');
          check(!download.disabled && !raw.disabled, 'error recovery');
          mode = 'json'; download.click(); await pause(); check(downloads.length === 1, 'unexpected JSON not downloaded as CSV');
          mode = 'csv'; download.click(); await pause(); check(downloads.length === 2, 'retry succeeds');
          check(document.documentElement.scrollWidth <= innerWidth, 'responsive layout has no horizontal overflow');
          check(!errors.length, errors.join('; '));
          result.textContent = 'PASS host revenue page ${width}px';
        } catch (error) { result.textContent = 'FAIL: ' + error.message; }
        document.body.appendChild(result);
      });
    `;
    html = html.replace('</body>', `<script>${setup}</script><script>${fs.readFileSync(path.join(root, 'js/host-revenue-export.js'), 'utf8')}</script><script>${checks}</script></body>`);
    const file = path.join(tmp, `page-${width}.html`); fs.writeFileSync(file, html);
    const run = cp.spawnSync('google-chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-proxy-server', `--window-size=${width},1000`, '--user-data-dir=' + path.join(tmp, `chrome-${width}`), '--dump-dom', '--virtual-time-budget=4000', 'file://' + file], {encoding:'utf8', timeout:25000, maxBuffer:4e6});
    const result = run.stdout?.match(/<pre id="test-result">([^<]+)/)?.[1];
    console.log(result || run.stderr);
    if (!result?.startsWith('PASS')) process.exitCode = 1;
  }
} finally { fs.rmSync(tmp, {recursive:true, force:true}); }
