const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'host-dashboard-'));
const script=s=>`<script>${s}</script>`;
const rows=[];
for(let d=new Date('2024-01-01');d<=new Date('2025-03-31');d.setUTCDate(d.getUTCDate()+1)) rows.push({date:d.toISOString().slice(0,10),used_storage:1e12,total_storage:2e12,storage_price:1e9,upload_price:1e9,download_price:2e9,revenue:'1000000000000000000000000',burned_funds:'100000000000000000000000',locked_collateral:'2000000000000000000000000',risked_collateral:'1000000000000000000000000',successful_contracts:1,renewed_contracts:2,failed_contracts:0,active_contracts:10});
try{
for(const [scenario,width] of [['normal',1440],['normal',480],['partial',1440],['failure',1440],['empty',1440]]){
let html=cp.execFileSync('php',[path.join(__dirname,'render-host-fixture.php')],{cwd:root,encoding:'utf8'});
html=html.replace(/<script src="[^"]*"><\/script>/g,'');
const scripts=['js/graph-renderer.js','js/dashboard/ranges.js','js/dashboard/sources.js','js/dashboard/host-sources.js','js/dashboard/dashboard.js'];
html=html.replace('</head>',()=>`<style>${['css/style.css','css/theme.css','css/components/data-page.css','css/components/dashboard.css','css/pages/host.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style>`+script(fs.readFileSync(process.env.CHART_JS,'utf8'))+script(require('./date-adapter.cjs'))+script(`
window.errors=[];window.requests=[];window.fail='${scenario}'==='failure';window.partial='${scenario}'==='partial';
addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
window.bootstrap={Tooltip:{getOrCreateInstance:()=>({})}};
window.fetch=async url=>{requests.push(url);return {ok:!(window.fail&&url.includes('/daily/hosts'))&&!(window.partial&&url.includes('scheduled_revenue')),json:async()=>{
if(url.includes('/daily/hosts'))return {hosts:${JSON.stringify(scenario==='empty'?[]:rows)}};
if(url.includes('scheduled_revenue'))return {scheduled_revenue:[{unlock_date:'2025-05-01',revenue_sc:3}]};
if(url.includes('estimated_egress'))return {estimated_egress:[{month:'2025-03',estimated_egress_gb:12}]};
if(url.includes('exchange_rate'))return ${JSON.stringify(rows.map(row=>({date:row.date,eur:row.date==='2025-03-31'?2:1})))};
return {actual:{coin_price:{eur:3}}};
}}};
window.fetchWithCache=async url=>(await fetch(url)).json();
`)+scripts.map(p=>script(fs.readFileSync(path.join(root,p),'utf8'))).join('')+'</head>');
html=html.replace('</body>',()=>script(`
document.addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
const out=document.createElement('pre');out.id='test-result';
try{
const check=(v,m)=>{if(!v)throw new Error(m)}, tick=()=>new Promise(r=>setTimeout(r,120));
const chart=id=>Chart.getChart(document.getElementById('dashboard-'+id));
const tab=name=>{const r=document.getElementById('host-tab-'+name+'-radio');r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));};
const dashboard=id=>document.querySelector('[data-dashboard-id="host-'+id+'"]');
check(!requests.some(u=>u.includes('scheduled_revenue')||u.includes('estimated_egress')),'lazy endpoints');
const ids=[...document.querySelectorAll('[id]')].map(n=>n.id);check(new Set(ids).size===ids.length,'unique IDs');
tab('charts');await tick();const history=dashboard('history');
check(!requests.some(u=>u.includes('scheduled_revenue')),'economics still lazy');
check(history.querySelector('[data-range="1y"]').getAttribute('aria-pressed')==='true','history defaults to 1Y despite old URL');
check(chart('host-egress'),'egress independent');
if('${scenario}'==='failure'){
check(history.querySelector('[data-dashboard-chart="host-storage"] [data-chart-status]').textContent==='History unavailable.','history failure');
window.fail=false;history.querySelector('[data-dashboard-chart="host-storage"] [data-chart-retry]').click();await tick();check(chart('host-storage'),'retry recovers');
}else if('${scenario}'==='empty'){
check(!chart('host-storage'),'empty daily history');
}else{
check(chart('host-storage').width>0,'visible history chart size');
check(chart('host-contract-history').data.datasets[3].type==='line','active contracts line');
history.querySelector('[data-range="1y"]').click();
check(chart('host-contract-history').data.labels.length===12,'1Y includes twelve calendar months');
check(chart('host-contract-history').data.labels[0]==='2024-04-01','1Y starts eleven months before latest');
history.querySelector('[data-range="30d"]').click();check(chart('host-storage').data.labels.length===30,'30D ends on last observation');
const form=history.querySelector('[data-custom-range]');history.querySelector('[data-range="custom"]').click();form.elements.from.value='2024-01-01';form.elements.through.value='2024-12-31';form.dispatchEvent(new Event('submit',{cancelable:true}));
check(chart('host-storage').data.labels.length===366,'inclusive custom year');
form.elements.from.value='2024-01-15';form.elements.through.value='2024-12-15';form.dispatchEvent(new Event('submit',{cancelable:true}));
check(chart('host-contract-history').data.labels.length===12,'partial custom dates retain boundary months');
check(chart('host-storage').data.labels.length===336,'daily custom dates remain exact');
check(![...new URL(location.href).searchParams.keys()].some(key=>key.startsWith('host-history_')||key.startsWith('host-economics_')),'no time ranges in URL');
check(new URL(location.href).searchParams.get('id')==='1','host identity preserved');
}
tab('economics');await tick();const economics=dashboard('economics');check(economics.querySelector('[data-range="1y"]').getAttribute('aria-pressed')==='true','economics defaults to 1Y');check(chart('host-revenue'),'revenue chart');
if('${scenario}'!=='empty'){
check(chart('host-collateral').data.labels.at(-1)==='2025-03-01','collateral not extended by forecast');
check(chart('host-revenue').data.datasets[1].data.some(v=>v<0),'negative burned funds');
}
if('${scenario}'==='partial')check(!economics.querySelector('[data-chart-warning]').hidden,'forecast failure warning');
else check(chart('host-revenue').data.labels.at(-1)==='2025-05-01','forecast horizon');
economics.querySelector('[data-range="all"]').click();
check(history.querySelector('[data-range="all"]').getAttribute('aria-pressed')==='false','independent tab ranges');
document.dispatchEvent(new CustomEvent('currencyChange',{detail:'eur'}));await tick();
if('${scenario}'!=='partial')check(chart('host-revenue').data.datasets[2].data.at(-1)===9,'anticipated current fiat rate');
if('${scenario}'!=='empty'){const c=chart('host-revenue');check(c.data.datasets[0].data[c.data.labels.indexOf('2025-03-01')]===32,'earned fiat sums daily rates, not month-end or current rate');}
check(chart('host-revenue').scales.y.ticks.some(t=>String(t.label).includes('€')),'revenue y-axis shows fiat unit');
if('${scenario}'!=='empty')check(chart('host-collateral').scales.y.ticks.some(t=>String(t.label).includes('€')),'collateral y-axis shows fiat unit');
document.dispatchEvent(new CustomEvent('currencyChange',{detail:'sc'}));await tick();
check(chart('host-revenue').scales.y.ticks.some(t=>String(t.label).includes(' SC')),'revenue y-axis follows currency changes');
check(chart('host-revenue').width>0,'economics resized');
tab('charts');await tick();if(chart('host-storage'))check(chart('host-storage').width>0,'history resizes on return '+JSON.stringify({root:history.getBoundingClientRect().width,canvas:chart('host-storage').canvas.parentElement.getBoundingClientRect().width,status:history.querySelector('[data-dashboard-chart="host-storage"] [data-chart-status]').textContent,checked:document.getElementById('host-tab-charts-radio').checked}));
check(document.documentElement.scrollWidth<=innerWidth,'no page overflow');check(!errors.length,errors.join(';'));out.textContent='PASS host ${scenario} ${width}';
}catch(e){out.textContent='FAIL '+e.stack+' errors: '+errors.join(';')}document.body.append(out);
},400));` )+'</body>');
const file=path.join(tmp,scenario+width+'.html');fs.writeFileSync(file,html);
const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size='+width+',1100','--user-data-dir='+path.join(tmp,'profile'),'--dump-dom','--virtual-time-budget=4500','file://'+file+'?id=1&host-history_range=30d&host-history_from=2024-01-01&host-economics_range=all'],{encoding:'utf8',timeout:20000,maxBuffer:8e6});
const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-1000));assert(result?.startsWith('PASS'));
}
}finally{fs.rmSync(tmp,{recursive:true,force:true})}
