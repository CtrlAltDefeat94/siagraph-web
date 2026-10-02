const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'), tmp=fs.mkdtempSync(path.join(os.tmpdir(),'storage-dashboard-'));
const script=text=>`<script>${text}</script>`;
const daily=[];
for(let d=new Date('2024-01-01');d<=new Date('2025-03-31');d.setUTCDate(d.getUTCDate()+1)) daily.push({date:d.toISOString(),utilized_storage:2e15,total_storage:8e15,active_hosts:100,total_hosts:150});
const monthly=[11,12,13,14,15].map((m,i)=>({date:new Date(Date.UTC(2023, m,1)).toISOString().slice(0,10),utilized_storage:(i+1)*1e15,total_storage:(i+5)*1e15}));
try {
for(const scenario of ['normal','failure','partial','short','empty']){
let html=cp.execFileSync('php',[path.join(__dirname,'render-fixture.php'),'network_storage.php'],{cwd:root,encoding:'utf8'});
html=html.replace('</head>',`<style>${['css/style.css','css/theme.css','css/components/data-page.css', 'css/components/dashboard.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style></head>`);
html=html.replace('</body>',script(fs.readFileSync(process.env.CHART_JS,'utf8'))+script(require('./date-adapter.cjs'))+script(`
window.errors=[];console.error=(...args)=>errors.push(args.map(String).join(' '));addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
window.fetch=async url=>({ok:${scenario!=='failure'} && !('${scenario}'==='partial'&&url.includes('/ath')),json:async()=>{
 if(url.includes('/ath')) return {utilized_storage:{ath_bytes:3e15,ath_date:'2024-01-01',latest_date:'2025-03-31',days_since_ath:455}};
 if(url.includes('/monthly/')) return ${JSON.stringify(scenario==='empty'?[]:scenario==='short'?monthly.slice(-1):monthly)};
 if(url.includes('/metrics')) return [{date:'2025-03-30',block_height:100000}];
 return ${JSON.stringify(scenario==='empty'?[]:scenario==='short'?daily.slice(-1):daily)};
}});
`)+['js/graph-renderer.js','js/dashboard/ranges.js','js/dashboard/sources.js','js/dashboard/dashboard.js'].map(p=>script(fs.readFileSync(path.join(root,p),'utf8'))).join('')+script(`
document.addEventListener('DOMContentLoaded',()=>setTimeout(()=>{
 const result=document.createElement('pre');result.id='test-result';
 try{
 const check=(v,m)=>{if(!v)throw new Error(m)}, chart=id=>Chart.getChart(document.querySelector('#dashboard-'+id));
 const metric=key=>document.querySelector('[data-metric="'+key+'"]');
 const button=r=>document.querySelector('[data-range="'+r+'"]');
 if('${scenario}'==='failure'){
 check([...document.querySelectorAll('[data-chart-status]')].every(n=>n.textContent==='History unavailable.'),'failures visible');
 check([...document.querySelectorAll('[data-metric]')].every(n=>n.textContent==='N/A'),'missing snapshots');
 }else if('${scenario}'==='empty'){
 check(!chart('storage'),'empty history');check(metric('block_height').textContent==='100,000','independent block snapshot');
 }else{
 check(('${scenario}'==='short'?['storage','utilization','hosts']:['storage','utilization','hosts','forecast']).every(id=>chart(id)),'all charts '+JSON.stringify([...document.querySelectorAll('[data-chart-status]')].map(n=>n.textContent))+errors.join(';'));
 check(metric('active_hosts').textContent==='100','hosting card');check(metric('block_height').textContent==='100,000','block card');
 check(metric('utilization_percent').textContent==='25%','utilization ratio');
 check(metric('ath_bytes').textContent===('${scenario}'==='partial'?'N/A':'3 PB'),'ATH independent');
 button('30d').click();check(chart('storage').data.labels.at(-1)==='2025-03-31','historical latest anchor');
 if('${scenario}'==='short') {check(chart('storage').data.labels.length===1,'short source');check(!document.querySelector('[data-dashboard-chart="forecast"] [data-chart-warning]').hidden,'insufficient forecast');}
 else {
 check(chart('storage').data.labels.length===30,'30-day history');
 check(chart('forecast').data.labels.at(-1)==='2026-04-01','24-month projection');
 check(chart('forecast').data.datasets[2].borderDash.length===2,'projected line distinct');
 const mode=document.querySelector('[data-chart-mode]');mode.value='1';mode.dispatchEvent(new Event('change'));
 check(chart('forecast').options.scales.y.type==='logarithmic','exponential scale');
 mode.value='0';mode.dispatchEvent(new Event('change'));check(chart('forecast').options.scales.y.type==='linear','linear recovery');
 button('custom').click();const form=document.querySelector('[data-custom-range]');form.elements.from.value='2024-01-01';form.elements.through.value='2024-12-31';form.dispatchEvent(new Event('submit',{cancelable:true}));
 check(chart('storage').data.labels.length===366,'full custom year');check(chart('hosts').data.labels.length===366,'shared host range');
 check(metric('active_hosts').textContent==='100','snapshot unchanged');
 check(chart('forecast').data.labels.at(-1)<='2024-12-31','custom limits forecast');
 const c=chart('storage');c._eventHandler({type:'mousemove',x:c.scales.x.getPixelForValue(Date.parse('2024-06-01')),y:(c.chartArea.top+c.chartArea.bottom)/2,native:{type:'mousemove'}});
 check([...document.querySelectorAll('.dashboard-tooltip')].filter(n=>!n.hidden).length===1,'local tooltip');
 }
 }
 check(document.documentElement.scrollWidth<=innerWidth,'mobile overflow');check(!errors.length,errors.join(';'));result.textContent='PASS storage ${scenario}';
 }catch(e){result.textContent='FAIL '+e.stack;}document.body.append(result);
},400));` )+'</body>');
const file=path.join(tmp,scenario+'.html');fs.writeFileSync(file,html);
const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size=480,1000','--user-data-dir='+path.join(tmp,'profile'),'--dump-dom','--virtual-time-budget=2500','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:5e6});
const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-500));assert(result?.startsWith('PASS'));
}
}finally{fs.rmSync(tmp,{recursive:true,force:true})}
