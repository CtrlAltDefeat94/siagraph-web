// Offline browser integration test. Supply the Chart.js 3 bundle via CHART_JS.
const fs = require('node:fs'), cp = require('node:child_process'), path = require('node:path'), os = require('node:os');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const chartSource = fs.readFileSync(process.env.CHART_JS, 'utf8');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'dashboard-browser-'));
const rows = [];
for (let d = new Date('2023-01-01'); d <= new Date('2025-03-31'); d.setUTCDate(d.getUTCDate() + 1)) {
    rows.push({ date: d.toISOString().slice(0, 10) + ' 00:00:00', contracts_formed: 2, renewed_contracts: 3,
        successful_contracts: 4, failed_contracts: 0, active_contracts: 100, renter_collateral_locked: '1000000000000000000000000000',
        host_collateral_locked: '2000000000000000000000000000', circulating_supply: '1000000000000000000000000000000' });
}
// Use a deterministic UTC adapter with the real Chart.js canvas and event handling.
const adapter = require('./date-adapter.cjs');
try {
for (const page of ['contract_activity.php', 'contracts_collateral.php']) {
for (const scenario of ['normal', 'empty', 'failure', 'custom-url', 'short', 'leading-missing']) {
    const data = scenario === 'empty' ? [] : scenario === 'short' ? rows.slice(-1) : rows.map((row, index) => scenario === 'leading-missing' && index < 10
        ? { ...row, contracts_formed: index < 5 ? null : 0, active_contracts: index < 5 ? null : 0 } : row);
    let html = cp.execFileSync('php', [path.join(__dirname, 'render-fixture.php'), page], { cwd: root, encoding: 'utf8' });
    html = html.replace('</head>', `<style>${['css/style.css', 'css/theme.css', 'css/overrides.css', 'css/components/data-page.css', 'css/components/dashboard.css'].map(p => fs.readFileSync(path.join(root, p), 'utf8')).join('\n')}</style></head>`);
    const script = src => `<script>${src}</script>`;
    html = html.replace('</body>', script(chartSource) + script(adapter) + script(`
        window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
        window.fetch=async()=>({ok:${scenario !== 'failure'},json:async()=>${JSON.stringify(data)}});
        window.currencyDisplay={resolveRateForEntryDate:()=>0.01};
    `) + ['js/graph-renderer.js', 'js/dashboard/ranges.js', 'js/dashboard/sources.js', 'js/dashboard/dashboard.js'].map(p => script(fs.readFileSync(path.join(root, p), 'utf8'))).join('') + script(`
    document.addEventListener('DOMContentLoaded',()=>setTimeout(()=>{
        const result=document.createElement('pre');result.id='test-result';
        try {
            const check=(ok,msg)=>{if(!ok)throw new Error(msg)};
            const panels=[...document.querySelectorAll('[data-dashboard-chart]')];
            const metrics=[...document.querySelectorAll('[data-metric]')];
            const charts=()=>[...document.querySelectorAll('canvas')].map(c=>Chart.getChart(c));
            const button=r=>document.querySelector('[data-range="'+r+'"]');
            const form=document.querySelector('[data-custom-range]');
            const apply=(from,through)=>{button('custom').click();form.elements.from.value=from;form.elements.through.value=through;form.dispatchEvent(new Event('submit',{cancelable:true}));};
            if ('${scenario}'==='failure'||'${scenario}'==='empty') {
                check(panels.every(p=>!p.querySelector('[data-chart-status]').hidden),'status visible');
                check(metrics.every(m=>m.textContent==='N/A'),'missing KPI data');
            } else {
                check(charts().every(Boolean),'all four charts render');
                check(panels.every(p=>p.querySelector('[data-chart-status]').hidden),'all charts visible');
                if ('${scenario}'==='custom-url') {
                    check(charts().every(c=>c.data.labels[0]==='2024-01-01'&&c.data.labels.at(-1)==='2024-12-31'),'restored custom URL');
                } else check(button('3m').getAttribute('aria-pressed')==='true','3M default');
                if ('${scenario}'==='short') {
                    ['30d','3m','1y','all'].forEach(r=>{button(r).click();check(charts().every(c=>c.data.labels.length===1),'single record retained');});
                } else {
                    button('30d').click();check(charts().every(c=>c.data.labels.length===30),'30D anchored to last record');
                    const snapshot=metrics.map(m=>m.textContent).join('|');
                    apply('2024-01-01','2024-12-31');
                    check(charts().every(c=>c.data.labels.length===366),'inclusive full leap year');
                    check(new URL(location.href).searchParams.get('from')==='2024-01-01','custom URL updated');
                    if ('${page}'==='contract_activity.php') {
                        check(metrics[0].textContent==='732','period KPI sum');
                        check(charts()[1].options.scales.x.ticks.display===false&&charts()[3].options.scales.x.ticks.display===true,'completion axis labels');
                    } else {
                        check(metrics.map(m=>m.textContent).join('|')===snapshot,'latest snapshot unchanged');
                        check(charts()[1].data.datasets[0].data[0]===10,'fiat conversion');
                        check(charts()[3].data.datasets[0].data[0]===1000000,'SC supply');
                    }
                    const source=charts()[0], x=source.scales.x.getPixelForValue(Date.parse(source.data.labels[5]));
                    source._eventHandler({type:'mousemove',x,y:(source.chartArea.top+source.chartArea.bottom)/2,native:{type:'mousemove'}});
                    check([...document.querySelectorAll('.dashboard-tooltip')].filter(t=>!t.hidden).length===1,'single tooltip');
                    check(source.tooltip.getActiveElements().length===1,'hovered point');
                    const popup=panels[0].querySelector('.dashboard-tooltip');
                    check(popup.querySelectorAll('.is-hovered').length===1,'only hovered metric highlighted');
                    apply('2024-12-31','2024-01-01');check(form.elements.through.validationMessage.length>0,'invalid order rejected');
                    check(charts()[0].data.labels.length===366,'invalid range leaves charts intact');
                    form.elements.through.setCustomValidity('');
                    apply('2026-01-01','2026-12-31');check(panels.every(p=>p.querySelector('[data-chart-status]').textContent==='No data for this period.'),'empty period');
                    button('all').click();check(panels.every(p=>p.querySelector('[data-chart-status]').hidden),'recover from empty period');
                    check(charts().every(c=>c.data.labels.at(-1)==='2025-03-31'),'all includes latest record');
                    if ('${scenario}'==='leading-missing') check(charts()[0].data.labels[0]==='2023-01-11','trim initial missing and zero history');
                    if ('${page}'==='contract_activity.php') check(charts()[3].data.datasets[0].data.every(v=>v===0),'all-zero series retained');
                }
            }
            check(errors.length===0,errors.join(';'));
            check(document.documentElement.scrollWidth<=innerWidth,'no horizontal overflow');
            result.textContent='PASS ${page} ${scenario}';
        }catch(e){result.textContent='FAIL ${page} ${scenario}: '+e.stack;}document.body.append(result);
    },400));`) + '</body>');
    const file = path.join(tmp, page + '-' + scenario + '.html'); fs.writeFileSync(file, html);
    const suffix = scenario === 'custom-url' ? '?range=custom&from=2024-01-01&through=2024-12-31' : '';
    const run = cp.spawnSync(process.env.CHROME_BIN || 'google-chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--window-size=480,1000', '--user-data-dir=' + path.join(tmp, 'profile'), '--dump-dom', '--virtual-time-budget=2500', 'file://' + file + suffix], { encoding: 'utf8', timeout: 20000, maxBuffer: 5e6 });
    const result = run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];
    console.log(result || run.stderr?.slice(-500)); assert(result?.startsWith('PASS'));
}
}
} finally { fs.rmSync(tmp, { recursive: true, force: true }); }
