const fs = require('node:fs'), cp = require('node:child_process'), path = require('node:path'), os = require('node:os');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const chartSource = fs.readFileSync(process.env.CHART_JS, 'utf8');
const adapter = require('./date-adapter.cjs');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'home-dashboard-'));
const network = [];
for (let date = new Date('2023-01-01'); date <= new Date('2025-03-31'); date.setUTCDate(date.getUTCDate() + 1)) network.push({date: date.toISOString(), utilized_storage: 1e12, active_hosts: 123});
const revenue = [];
for (let date = new Date('2023-01-01'); date <= new Date('2025-03-01'); date.setUTCMonth(date.getUTCMonth() + 1)) revenue.push({date: date.toISOString(), contract_revenue: {sc: '1000000000000000000000000000', eur: 10, usd: 12}});
try {
for (const scenario of ['normal', 'mobile']) {
    let html = cp.execFileSync('php', [path.join(__dirname, 'render-home-fixture.php')], { cwd: root, encoding: 'utf8' });
    const script = source => `<script>${source}</script>`;
    const setup = `
        window.fetchWithCache=async url=>(await window.fetch(url)).json();
        window.currencyDisplay={normalizeScValue:value=>value==null?null:Number(value.sc??value)/1e24,resolveRateForEntryDate:()=>0.01};
        window.errors=[];window.requests=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
        window.fetch=async url=>{requests.push(String(url)); let payload=[];let ok=true;
            if(String(url).includes('/daily/growth')){payload=${JSON.stringify(network)};ok='${scenario}'!=='network-failed';}
            else if(String(url).includes('/monthly/aggregates')){payload=${JSON.stringify(revenue)};ok='${scenario}'!=='history-failed';}
            else if(String(url).includes('network_anticipated')){payload={as_of:'2025-03-31T00:00:00Z',scheduled_revenue:[{unlock_date:'2025-04-01',revenue_sc:'2000'},{unlock_date:'2025-05-01',revenue_sc:'3000'}]};ok='${scenario}'!=='forecast-failed';}
            else if(String(url).includes('compare_metrics'))payload={actual:{coin_price:'${scenario}'==='no-rates'?{}:{eur:0.02,usd:0.03},utilized_storage:1e12,total_storage:2e12,active_contracts:100,online_hosts:20,'30_day_revenue':{eur:10,usd:12}},change:{online_hosts:2}};
            return {ok,json:async()=>payload};};
    `;
    html = html.replace('</head>', `<style>${['css/style.css','css/theme.css','css/overrides.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style>${script(setup)}</head>`);
    html = html.replace('</body>', script(chartSource) + script(adapter) + ['js/graph-renderer.js','js/network-revenue.js'].map(p=>script(fs.readFileSync(path.join(root,p),'utf8'))).join('') + script(`
    document.addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
        const result=document.createElement('pre');result.id='test-result';
        try {
            const check=(v,msg)=>{if(!v)throw new Error(msg)};
            check(!document.querySelector('[data-dashboard]'),'front page uses original custom layout');
            check(document.querySelectorAll('.exp-summary-item').length===6,'original KPI layout');
            check(document.querySelectorAll('.exp-range-tab').length===5,'original comparison tabs');
            check(document.querySelectorAll('.exp-analytics-grid > article').length===3,'original analytics cards');
            check(document.querySelectorAll('.exp-section-head a').length===3,'original detail links');
            check(['networkstorage','hostcount','monthlyrevenue'].every(id=>Chart.getChart(document.getElementById(id))),'original charts render');
            check(!document.querySelector('.dashboard-custom-range'),'no dashboard picker');
            document.querySelector('.exp-range-tab[data-range="7d"]').click();
            await new Promise(resolve=>setTimeout(resolve,50));
            check(document.getElementById('exp-summary-range-title').textContent.includes('week'),'comparison tabs work');
            if('${scenario}'==='normal') {
                const cards=[...document.querySelectorAll('.exp-analytics-grid > article')].map(e=>e.getBoundingClientRect());
                check(Math.abs(cards[0].top-cards[2].top)<2,'original desktop chart row');
            }
            check(errors.length===0,errors.join(';'));check(document.documentElement.scrollWidth<=innerWidth,'mobile overflow');
            result.textContent='PASS home ${scenario}';
        } catch(e) {result.textContent='FAIL home ${scenario}: '+e.stack;}document.body.append(result);
    },500));`) + '</body>');
    const file=path.join(tmp,scenario+'.html');fs.writeFileSync(file,html);
    const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size='+(scenario==='mobile'?'480,1200':'1440,900'),'--user-data-dir='+path.join(tmp,'chrome'),'--dump-dom','--virtual-time-budget=3000','--screenshot=/tmp/home-dashboard-'+scenario+'.png','file://'+file+'?range=custom&from=2024-01-01&through=2024-12-31'],{encoding:'utf8',timeout:20000,maxBuffer:6e6});
    const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-500));assert(result?.startsWith('PASS'));
}
} finally { fs.rmSync(tmp,{recursive:true,force:true}); }
