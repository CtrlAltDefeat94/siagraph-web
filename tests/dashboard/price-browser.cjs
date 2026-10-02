const fs = require('node:fs'), cp = require('node:child_process'), path = require('node:path'), os = require('node:os');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'price-dashboard-'));
const script = text => `<script>${text}</script>`;
try {
for (const scenario of ['normal', 'failure', 'empty']) {
    let html = cp.execFileSync('php', [path.join(__dirname, 'render-fixture.php'), 'siacoin_price.php'], {cwd:root,encoding:'utf8'});
    html = html.replace('</head>', `<style>${['css/components/data-page.css','css/components/dashboard.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style></head>`);
    html = html.replace('</body>', script(fs.readFileSync(process.env.CHART_JS,'utf8')) + script(require('./date-adapter.cjs')) + script(`
    window.errors=[];addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
    window.fail=${scenario === 'failure'};
    window.fetch=async url=>({ok:!window.fail,json:async()=>url.includes('/daily/') ? ${scenario === 'empty' ? '[]' : JSON.stringify([
        {date:'2023-01-01',eur:0.001,usd:0.002}, {date:'2024-01-01',eur:0.00001234,usd:0.00002345},
        {date:'2024-12-31',eur:0.002,usd:0.003}, {date:'2025-01-02',eur:0.004,usd:0.005}])} : {date:'2025-01-03 12:30:00',eur:0.006,usd:0.007}});
    `) + ['js/graph-renderer.js','js/dashboard/ranges.js','js/dashboard/sources.js','js/dashboard/dashboard.js'].map(p=>script(fs.readFileSync(path.join(root,p),'utf8'))).join('') + script(`
    document.addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
        const result=document.createElement('pre');result.id='test-result';
        try {
            const check=(v,m)=>{if(!v)throw new Error(m)};
            const graph=()=>Chart.getChart(document.querySelector('canvas'));
            const button=r=>document.querySelector('[data-range="'+r+'"]');
            const kpi=document.querySelector('[data-metric]');
            if ('${scenario}'==='failure') {
                const retry=document.querySelector('[data-chart-retry]');check(!retry.hidden,'retry offered');
                window.fail=false;retry.click();await new Promise(r=>setTimeout(r,30));check(graph(),'retry recovers');
            } else if ('${scenario}'==='empty') {
                check(!graph(),'empty history');check(kpi.textContent.includes('0.006000'),'independent quote');
            } else {
                check(graph(),'chart rendered');check(graph().data.labels.at(-1)==='2025-01-02','latest history anchor');
                check(kpi.textContent.includes('0.006000'),'hourly quote distinct');
                check(document.querySelector('[data-snapshot-date]').textContent.includes('12:30:00'),'visible timestamp');
                button('all').click();check(graph().data.datasets[0].data.includes(null),'missing dates remain gaps');
                button('custom').click();const form=document.querySelector('[data-custom-range]');
                form.elements.from.value='2024-01-01';form.elements.through.value='2024-12-31';form.dispatchEvent(new Event('submit',{cancelable:true}));
                check(graph().data.labels[0]==='2024-01-01'&&graph().data.labels.at(-1)==='2024-12-31','inclusive custom range');
                check(document.querySelector('[data-chart-rows]').textContent.includes('0.00001234'),'precise table');
                document.dispatchEvent(new CustomEvent('currencyChange',{detail:'usd'}));
                check(graph().data.datasets[0].data[0]===0.00002345,'currency updates data');check(kpi.textContent.includes('0.007000'),'currency updates quote');
                check(graph().data.labels[0]==='2024-01-01','currency preserves selection');
                document.dispatchEvent(new CustomEvent('currencyChange',{detail:'cad'}));
                check(kpi.textContent==='N/A','missing currency not zero');
                document.dispatchEvent(new CustomEvent('currencyChange',{detail:'eur'}));check(graph(),'recovers currency');
                const c=graph();c._eventHandler({type:'mousemove',x:c.scales.x.getPixelForValue(Date.parse('2024-01-01')),y:(c.chartArea.top+c.chartArea.bottom)/2,native:{type:'mousemove'}});
                check(document.querySelector('.dashboard-tooltip').textContent.includes('0.00001234'),'precise tooltip');
            }
            check(!errors.length,errors.join(';'));result.textContent='PASS price '+ '${scenario}';
        }catch(e){result.textContent='FAIL '+e.stack}document.body.append(result);
    },400));`) + '</body>');
    const file=path.join(tmp,scenario+'.html');fs.writeFileSync(file,html);
    const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size=480,1000','--user-data-dir='+path.join(tmp,'profile'),'--dump-dom','--virtual-time-budget=2500','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:5e6});
    const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-500));assert(result?.startsWith('PASS'));
}
} finally { fs.rmSync(tmp,{recursive:true,force:true}); }
