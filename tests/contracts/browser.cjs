const fs = require('fs'), cp = require('child_process'), path = require('path'), os = require('os');
const root = path.resolve(__dirname, '../..'), tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'active-contract-browser-'));
const wallet = 'a'.repeat(76), hostKey = 'b'.repeat(64), cid = 'c'.repeat(64);
const row = {contract_id:cid,host_public_key:hostKey,renter_wallet_address:wallet,filesize:'9007199254740993',windowend:'1144',renewed_from_contract_id:'d'.repeat(64),revenue_locked:'1000000000000000000000000'};
const payload = {data:{summary:{contracts:'26',with_data:'25',empty_contracts:'1',unknown_size:'0',renter_wallets:'1',hosts:'1',unknown_wallet_contracts:'0',bytes:row.filesize,revenue_locked:row.revenue_locked},distribution:[{identity:wallet,contracts:'26',bytes:row.filesize}],expirations:[{period:'day',contracts:'26',bytes:row.filesize,revenue_locked:row.revenue_locked}],reference:{block_height:'1000',timestamp:'2026-09-11 00:00:00'},items:Array.from({length:25},()=>row),pagination:{page:1,has_more:true}},meta:{generated_at:'2026-09-11T00:00:00Z'},errors:[]};
try {
 for (const scenario of ['host','renter','empty','failure','distribution']) {
  const isHost=scenario!=='renter';
  let markup;
  if(isHost) markup=fs.readFileSync(root+'/host/host.php','utf8').match(/<div class="card__content" data-active-contracts[^\n]+/)[0].replace(/<\?php.*?\?>/,hostKey);
  else markup=cp.execFileSync('php',[root+'/tests/renters/render-fixture.php','renter.php'],{encoding:'utf8'}).match(/<div data-active-contracts[^>]*><\/div>/)[0];
  const economics = '';
  let html=`<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{background:#181b20;color:#eee;margin:12px} ${fs.readFileSync(root+'/css/pages/active-contracts.css','utf8')}</style></head><body><input type="radio" name="host-tabs" id="fixture-economics-tab" hidden><div id="economics" hidden>${economics}</div><div id="panel" hidden>${markup}</div><script>
  window.errors=[];window.requests=[];window.getCookie=()=> 'eur';window.Chart=class{constructor(canvas,config){window.contractChart=config;}destroy(){}resize(){}};
  addEventListener('error',e=>window.errors.push(e.message));addEventListener('unhandledrejection',e=>window.errors.push(String(e.reason)));
  window.fetch=async url=>{const u=new URL(url,'https://fixture.test');requests.push(u.pathname+u.search);
    if(u.pathname==='/api/v1/exchange_rate.php')return {ok:true,json:async()=>({date:'2026-09-11T13:00:00Z',eur:2,usd:3})};
    if(u.pathname.includes('exchange_rate'))return {ok:true,json:async()=>[{date:'2026-09-11',eur:2,usd:3}]};
    if(u.pathname.includes('completed'))return {ok:false,json:async()=>({errors:[{message:'Unavailable'}]})};
    if('${scenario}'==='failure' && requests.filter(r=>r.includes('/active.php')).length===1)return {ok:false,json:async()=>({errors:[{message:'Test service unavailable'}]})};
    const p=${JSON.stringify(payload)};
    if('${scenario}'==='distribution') {
      const sizes=['15054774730752','2956451643392','2308196794368','942179090432','3825205248','3745513472','1639972864','0','0'];
      p.data.summary.bytes='21270812950528';
      p.data.distribution=sizes.map((bytes,i)=>({identity:i===8?null:String(i+1).repeat(76),others:i===8,contracts:'1',bytes}));
    }
    if('${scenario}'==='renter')p.data.distribution[0].identity='${hostKey}';
    if('${scenario}'==='empty'){p.data.summary={...p.data.summary,contracts:'0',with_data:'0',empty_contracts:'0',bytes:'0',revenue_locked:'0'};p.data.items=[];p.data.distribution=[];p.data.expirations=[];p.data.pagination.has_more=false;}
    if(u.searchParams.get('page')==='2'){p.data.items=[{...p.data.items[0],contract_id:'e'.repeat(64)}];p.data.pagination={page:2,has_more:false};}
    if(u.searchParams.get('search')){p.data.items=[];p.data.pagination.has_more=false;}
    return {ok:true,json:async()=>p};};
  </script>`;
  for(const file of ['renter-format.js','renter-currency.js','active-contracts.js'])html+=`<script>${fs.readFileSync(root+'/js/'+file,'utf8')}</script>`;
  html+=`<script>
  const pause=()=>new Promise(r=>setTimeout(r,80));
  const check=(ok,label)=>{if(!ok)throw Error(label)};
  addEventListener('DOMContentLoaded',async()=>{const result=document.createElement('pre');result.id='test-result';
   try{
    const widget=document.querySelector('[data-active-contracts]');
    ${isHost?'':`activeContracts.mount(widget,'${wallet}');`}
    await pause();check(requests.length===0,'hidden tab must not fetch');
    if('${scenario}'==='host') {
      document.getElementById('economics').hidden=false;document.getElementById('fixture-economics-tab').dispatchEvent(new Event('change'));await pause();await pause();
      check(!document.querySelector('#economics [data-locked-revenue]'),'locked revenue is not rendered in Economics');
      document.getElementById('economics').hidden=true;
    }
    document.getElementById('panel').hidden=false;document.dispatchEvent(new Event('renter:tabchange'));await pause();await pause();
    if('${scenario}'==='host') {
      check(requests.filter(r=>r.includes('/active.php')).length===1,'tabs share the first-page response');
      check(!widget.querySelector('.ac-summary').textContent.includes('Locked revenue'),'total locked revenue belongs to Economics');
      check(!!widget.querySelector('dt[title*="Sum of active contract filesizes"]'),'contracted storage explained');
    }
    const button=text=>[...widget.querySelectorAll('button')].find(b=>b.textContent===text);
    if('${scenario}'==='failure'){check(widget.textContent.includes('Test service unavailable'),'visible API error');check(!button('Refresh contracts'),'no manual refresh');document.dispatchEvent(new Event('renter:tabchange'));await pause();check(requests.filter(r=>r.includes('/active.php')).length===1,'tab changes do not retry failed data');result.textContent='PASS failure';document.body.append(result);return;}
    if (${isHost}) check(widget.querySelector('.ac-freshness[title*="Reference block 1000"]')?.textContent.includes('Updated'),'freshness height is tooltip-only');
    else check(widget.textContent.includes('Reference block 1000'),'freshness height');
    if('${scenario}'==='empty'){check(widget.textContent.includes('No active contracts.'),'empty display');check(!widget.querySelector('form'),'host summary has no list controls');}
    else{
      if ('${scenario}' !== 'distribution') check(widget.querySelector('[title="9007199254740993 bytes"]'),'exact bytes');
      if('${scenario}'==='distribution') {
        const chart=window.contractChart;
        check(chart.data.datasets[0].data.length===9,'all eight renters plus Others reach chart');
        check(chart.data.datasets[0].data.reduce((sum,n)=>sum+BigInt(n),0n)===21270812950528n,'chart includes all stored bytes');
        check(chart.data.datasets[0].data.filter(n=>n>0).length===7,'small nonzero renters retained');
        check(chart.data.datasets[0].borderWidth===2 && chart.data.datasets[0].borderColor==='#302323','original slice borders retained');
        check(widget.textContent.includes('0.0180%')&&widget.textContent.includes('0.0077%'),'small shares do not round to zero');
        check(!!widget.querySelector('h3[title*="Wallets with zero stored data"]'),'chart weighting explained');
      } else check(widget.textContent.includes('100.0%'),'distribution share');
      if(${isHost}){check(!requests.some(r=>r.includes('/completed.php')),'host never requests completion history');check(!widget.querySelector('form'),'no lists on host summary');check(widget.textContent.includes('EUR'),'currency');
        document.dispatchEvent(new CustomEvent('currencyChange',{detail:'usd'}));await pause();check(widget.textContent.includes('USD'),'currency change');}
      else check(widget.querySelector('a[href="/host?public_key=${hostKey}"]'),'host navigation');
      check(!widget.querySelector('form'),'summary has no contract list controls');
    }
    check(![...widget.querySelectorAll('button')].some(b=>/refresh|retry/i.test(b.textContent)),'no refresh buttons');
    check(document.documentElement.scrollWidth<=innerWidth,'mobile overflow contained');
    check(errors.length===0,errors.join(';'));result.textContent='PASS ${scenario}';
   }catch(e){result.textContent='FAIL ${scenario}: '+e.message;}document.body.append(result);
  });</script></body></html>`;
  const file=path.join(tmp,scenario+'.html');fs.writeFileSync(file,html);
  const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--no-proxy-server','--window-size=390,844','--user-data-dir='+path.join(tmp,'chrome-'+scenario),'--dump-dom','--virtual-time-budget=4000','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:2e6});
  const result=run.stdout?.match(/<pre id="test-result">([^<]+)/)?.[1];console.log(result||`FAIL ${scenario}: ${run.error||run.stderr?.slice(-300)}`);if(!result?.startsWith('PASS'))process.exitCode=1;
 }
}finally{fs.rmSync(tmp,{recursive:true,force:true});}
