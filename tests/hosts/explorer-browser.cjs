const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'explorer-browser-'));
const currencySource=fs.readFileSync(path.join(root,'script.js'),'utf8');
const formatter=currencySource.slice(currencySource.indexOf('window.currencyDisplay.formatFiatWithScTooltip ='),currencySource.indexOf('\n};',currencySource.indexOf('window.currencyDisplay.formatFiatWithScTooltip ='))+3);
const script=s=>`<script>${s}</script>`;
const host={host_id:1,net_address:'a-very-long-host-address-that-must-not-expand-the-row.example.test:9982',country_name:'United States of America',used_storage:123.45e12,total_storage:987.65e12,available_storage:864.2e12,used_storage_diff_available:1,used_storage_diff:1e12,estimated_egress_gb:2400,storage_price:1e9,upload_price:1e9,download_price:2e9,revenue_30d_sc:12345,revenue_30d_eur:21,software_version:'2.10.0-long-version-name',total_score:9,accepting_contracts:1};
try{
for(const [width,scenario] of [[2560,'normal'],[3840,'normal'],[1440,'normal'],[1024,'normal'],[390,'normal'],[1440,'failure'],[1440,'empty']]){
let html=cp.execFileSync('php',[path.join(root,'tests/dashboard/render-fixture.php'),'host_explorer.php'],{cwd:root,encoding:'utf8'});
html=html.replace('</head>',`<style>${['css/style.css','css/theme.css','css/overrides.css','css/components/data-page.css','css/pages/host-explorer.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style>`+script(`
window.currencyDisplay={getRateForDate:()=>999};
${formatter}
window.errors=[];window.calls=[];window.fail=${scenario==='failure'};window.empty=${scenario==='empty'};
addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
try {localStorage.clear()}catch(e){}
window.fetch=async url=>{calls.push(url);return {ok:!window.fail,json:async()=>url.includes('meta=1')?{countries:[],versions:[]}:{hosts:window.empty?[]:${JSON.stringify([host,{...host,host_id:2}])},pagination:{total_pages:3,current_page:Number(new URL(url,location.href).searchParams.get('page'))||1,per_page:15,total_rows:40}}}};
` )+'</head>');
html=html.replace('<script src="/js/host-columns.js"></script>',()=>script(fs.readFileSync(path.join(root,'js/host-columns.js'),'utf8')));
html=html.replace('</body>',()=>script(`
document.addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
const result=document.createElement('pre');result.id='test-result';
try{
const check=(v,m)=>{if(!v)throw new Error(m)},tick=()=>new Promise(r=>setTimeout(r,30));
const table=document.getElementById('hostTable'),region=document.querySelector('.host-table-scroll');
check(calls.length===2,'one metadata and one host request, no render loop');
if('${scenario}'==='failure') {check(!document.getElementById('hostsRetry').hidden,'retry visible');window.fail=false;document.getElementById('hostsRetry').click();await tick();check(table.tBodies[0].rows.length===2,'retry renders');}
else if('${scenario}'==='empty') {check(table.textContent.includes('No hosts match'),'empty state');}
else {
check(table.tBodies[0].rows.length===2,'host rows');document.getElementById('hostColumnsReset').click();await tick();
if(innerWidth>=768){
check(!table.classList.contains('compact-table'),'desktop stays tabular');
check([...table.tBodies[0].rows[0].cells].filter(c=>!c.hidden).length===13,'all columns shown');
if(innerWidth<3000)check(region.scrollWidth>region.clientWidth,'horizontal scrolling when columns exceed available space');
else check(region.scrollWidth<=region.clientWidth+1,'wide viewport fits all columns without scrolling');
if(innerWidth>1560){
const shell=document.getElementById('main-content'),card=document.querySelector('.hosts-results-card');
check(card.getBoundingClientRect().right>shell.getBoundingClientRect().right,'results expand into the right margin');
check(card.getBoundingClientRect().right<=document.documentElement.clientWidth,'results stop at viewport edge');
const left=card.getBoundingClientRect().left,wide=card.getBoundingClientRect().width;
const toggles=[...document.querySelectorAll('#hostColumnOptions input')];
toggles.forEach(input=>{if(input.checked)input.click()});
check(card.getBoundingClientRect().width<wide,'panel shrinks with fewer columns');
const layout=document.querySelector('.host-layout').getBoundingClientRect();
check(Math.abs(card.getBoundingClientRect().right-layout.right)<2,'few columns retain the normal panel width');
check(Math.abs(card.getBoundingClientRect().left-left)<1,'left edge stays anchored');
document.getElementById('hostColumnsReset').click();await tick();
}
check(table.tBodies[0].rows[0].getBoundingClientRect().height<85,'readable compact row height');
check(getComputedStyle(table.tBodies[0].rows[0].cells[2]).whiteSpace==='nowrap','country no wrapping');
if(region.scrollWidth>region.clientWidth){const cell=table.tBodies[0].rows[0].cells[1];region.scrollLeft=500;await tick();check(Math.abs(cell.getBoundingClientRect().left-region.getBoundingClientRect().left)<3,'host identity remains visible');}
check(table.querySelector('.host-link').title.includes('example.test'),'full host address available');
}else{check(table.classList.contains('compact-table'),'mobile compact');document.getElementById('filtersToggle').click();check(!document.getElementById('filtersContent').hidden,'mobile filters work');}
const country=document.querySelector('#hostColumnOptions input[value="country"]');country.click();check(hiddenHostColumns.has('country'),'column hides');
check(JSON.parse(localStorage.getItem(hostColumnStorageKey)).includes('country'),'column preference saved');
const second=[...document.querySelectorAll('#pagination button')].find(b=>b.textContent==='2');second.click();await tick();check(ajaxParams.page===2,'pagination retained after rendering');
document.getElementById('search').value='example';handleSearch();await tick();check(ajaxParams.query==='example','search retained');
document.getElementById('sort').value='total_storage';handleSortChange();await tick();check(ajaxParams.sort==='total_storage','sort works');
check(hostMoneyDisplay(12345,21).includes('EUR 21.00'),'historical fiat supplied directly');
check(hostMoneyDisplay(12345,null).endsWith(' SC'),'missing historical rates fall back to SC');
check(hostMoneyDisplay(0,0).includes('EUR 0.00'),'zero fiat remains zero');
document.dispatchEvent(new CustomEvent('currencyChange',{detail:'sc'}));check(selectedCurrency==='sc','currency handling');
}
check(region.getAttribute('aria-busy')==='false','loading finished');check(document.documentElement.scrollWidth<=innerWidth,'no page overflow');check(!errors.length,errors.join(';'));
result.textContent='PASS explorer ${width} ${scenario}';
}catch(e){result.textContent='FAIL '+e.stack}document.body.append(result);
},400));` )+'</body>');
const file=path.join(tmp,width+'-'+scenario+'.html');fs.writeFileSync(file,html);
const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size='+width+',1000','--user-data-dir='+path.join(tmp,'profile'),'--dump-dom','--virtual-time-budget=2500','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:5e6});
const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-500));assert(result?.startsWith('PASS'));
}
}finally{fs.rmSync(tmp,{recursive:true,force:true})}
