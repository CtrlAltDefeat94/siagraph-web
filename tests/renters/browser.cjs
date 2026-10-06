// Actual Chrome DOM checks with synthetic fetch responses and a Chart coordinate spy.
const fs = require('fs'), cp = require('child_process'), path = require('path'), os = require('os');
const root = path.resolve(__dirname, '../..'), tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'renter-browser-'));
const wallet = 'a'.repeat(76), key = '1'.repeat(64);
const record = {renter_wallet_address:wallet,contracted_filesize:'18446744073709551615',active_contracts:'3',active_hosts:'2',active_public_keys:'1',refundable_allowance:'9'.repeat(50),host_revenue_committed:'0',active_renewed_contracts:'0',average_contract_duration:'100.25',expiring_within_1_day:'0',expiring_within_1_week:'1',expiring_within_30_days:'3',first_seen:'2026-01-01',first_seen_height:'1',last_active:'2026-01-03',last_active_height:'3',updated_at:'2026-01-03',updated_height:'3'};
const cases = [
    ['detail','renter.php','js/renters.js'], ['public-key','renter.php','js/renters.js'], ['all','renter.php','js/renters.js'], ['tabs','renter.php','js/renters.js'], ['empty','renter.php','js/renters.js'], ['directory','renter_explorer.php','js/renters.js'],
    ['distribution','renter_distribution.php','js/renter-distribution.js'], ['distribution-empty','renter_distribution.php','js/renter-distribution.js'],
    ['ambiguous','renter.php','js/renters.js'], ['unmapped','renter.php','js/renters.js']
];
try {
 for (const [name, page, script] of cases) {
    if (process.env.RENTER_BROWSER_CASE && name !== process.env.RENTER_BROWSER_CASE) continue;
    let html = cp.execFileSync('php', [path.join(__dirname, 'render-fixture.php'), page], {encoding:'utf8'});
    const identity = ['ambiguous','unmapped','public-key'].includes(name) ? `public_key=${key}` : `address=${wallet}`;
    const setup = `<style>${fs.readFileSync(root+'/css/dark.css','utf8')} ${fs.readFileSync(root+'/css/style.css','utf8')} ${fs.readFileSync(root+'/css/theme.css','utf8')} ${fs.readFileSync(root+'/css/overrides.css','utf8')} ${fs.readFileSync(root+'/css/pages/renters.css','utf8')} ${fs.readFileSync(root+'/css/pages/renter.css','utf8')}</style><script>
      window.urlChanges=[];window.history.replaceState=(...args)=>{window.urlChanges.push(args[2])};window.getCookie=()=> 'eur';window.requests=[];
      const NativeParams=URLSearchParams;
      window.URLSearchParams=class extends NativeParams {constructor(value){super(value === location.search ? '${identity}' : value)}};
      window.charts={};window.historyFetches=0;window.Chart=class {constructor(canvas,config){window.lastChart=config;const card=canvas.closest('[data-history-fields]');if(card)window.charts[card.id]=config;}destroy(){}resize(){}};
      window.fetch=async url=>{const route=String(url).split('?')[0].split('/').pop(); const r=${JSON.stringify(record)}; let data; let metaExtra={};window.requests.push(String(url)); if(route==='daily.php')window.historyFetches++;
        if(url.includes('/api/v1/exchange_rate.php'))return {ok:true,json:async()=>({date:new Date().toISOString(),eur:3})};
        if(route==='exchange_rate.php') {const start=new Date();start.setUTCDate(start.getUTCDate()-29);return {ok:true,json:async()=>[{date:start.toISOString().slice(0,10),eur:2},{date:new Date().toISOString().slice(0,10),eur:3}]};}
        if(route==='details.php' && ['ambiguous','unmapped'].includes('${name}'))return {ok:false,json:async()=>({errors:[{code:'${name}'==='ambiguous'?'ambiguous_key':'not_found',message:'${name}'==='ambiguous'?'Select the relevant wallet.':'No renter wallet association is available for this public key.',details:{items:[{renter_wallet_address:'${wallet}'},{renter_wallet_address:'${'b'.repeat(76)}'}]}}]})};
        if(route==='daily.php' && String(url).includes('all=1')) {const more=!String(url).includes('after=');return {ok:true,json:async()=>({data:[{...r,date:more?'2020-01-01':'2026-01-01',snapshot_height:'1'}],meta:{units:{},pagination:{has_more:more,next_after:more?'2020-01-01':null}},errors:[]})};}
        if(route==='details.php')data=r;
        else if(route==='index.php') {
          if(['distribution','distribution-empty'].includes('${name}')) {
            data = '${name}'==='distribution-empty' ? {items:[],pagination:{has_more:false}} : {items:[{renter_wallet_address:'${wallet}',contracted_filesize:'1000'}],pagination:{has_more:false}};
            metaExtra = {totals:{total_filesize:'${name}'==='distribution-empty'?'0':'1200',renter_count:'2'}};
          } else data={items:[r],pagination:{has_more:false}};
        }
        else if(route==='overview.php')data=null;
        else if(route==='resolve.php')data={status:'${name}',renter_wallet_address:null,items:${name==='ambiguous'?JSON.stringify([{renter_wallet_address:wallet},{renter_wallet_address:'b'.repeat(76)}]):'[]'}};
        else if(route==='public-keys.php')data={items:[{renter_public_key:'${key}',first_seen:'2026-01-01',first_seen_height:'1',last_seen:'2026-01-03',last_seen_height:'3'}],pagination:{has_more:false}};
        else if(['detail','tabs','all','public-key'].includes('${name}')) {const end=new Date(),start=new Date();start.setUTCDate(end.getUTCDate()-29);const flow={contracts_formed:'1',contract_revisions:'2',contracts_resolved_storage_proof:'1',contracts_resolved_expiration:'0',contracts_resolved_renewal:'1',bytes_uploaded:'1000000000',bytes_removed:'1000',spending:'1000000000000000000000000',funds_returned:'0',renewal_funds_rolled:null,additional_renewal_funds:null};data=[{...r,...flow,date:start.toISOString().slice(0,10),snapshot_height:'1'},{...r,...flow,date:end.toISOString().slice(0,10),snapshot_height:'3',contracted_filesize:'0',renewal_funds_rolled:'0'}];}
        else data=[];
        return {ok:true,json:async()=>({data,meta:{units:{},snapshot_date:'2026-01-01',snapshot_height:'3',...metaExtra},errors:[]})};};
      window.addEventListener('error',e=>{document.body.dataset.error=e.message});
    </script>`;
    const assertions = {
      detail: `!document.getElementById('renterDetail').hidden && document.querySelector('#renterMetrics dd').title==='18446744073709551615 bytes' && Object.keys(window.charts).length===9 && window.historyFetches===1 && !document.getElementById('historyMetric') && window.charts['renter-history-storage-storage'].data.datasets[0].data[1]===null && window.charts['renter-history-storage-storage'].data.datasets[0].data[364]===0 && window.charts['renter-history-economics-spending'].data.datasets[0].data[335]===2 && !document.getElementById('renter-panel-keys') && !document.querySelector('[data-chart-details]') && !document.getElementById('main-content').textContent.includes('Active public keys') && document.getElementById('renterEconomicMetrics').textContent.includes('EUR')`,
      'public-key': `!document.getElementById('renterDetail').hidden && window.urlChanges.length===0 && window.requests.some(url=>url.includes('details.php?public_key=')) && window.requests.some(url=>url.includes('daily.php?address='))`,
      all: `await (async()=>{
        document.getElementById('renter-tab-charts').click();
        document.querySelector('[data-history-group="storage"] [data-days="all"]').click();
        await new Promise(resolve=>setTimeout(resolve,50));
        const chart=window.charts['renter-history-storage-storage'];
        return chart.data.labels[0]==='2020-01-01' && chart.data.labels.at(-1)==='2026-01-01' && chart.data.labels.length>366 && window.requests.filter(url=>url.includes('all=1')).length===2;
      })()`,
      tabs: `await (async()=>{
        const tab=id=>document.getElementById('renter-tab-'+id), panel=id=>document.getElementById('renter-panel-'+id);
        if(panel('overview').hidden || !panel('economics').hidden)return false;
        tab('economics').click();
        if(panel('economics').hidden || tab('economics').getAttribute('aria-selected')!=='true')return false;
        if(!document.getElementById('renter-history-economics-spending'))return false;
        tab('charts').click();
        tab('charts').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft'}));
        if(panel('economics').hidden || document.activeElement!==tab('economics'))return false;
        tab('economics').dispatchEvent(new KeyboardEvent('keydown',{key:'Home'}));
        if(panel('overview').hidden || document.activeElement!==tab('overview'))return false;
        tab('economics').click();document.querySelector('[data-history-group="economics"] [data-days="90"]').click();
        await new Promise(resolve=>setTimeout(resolve,50));
        return document.documentElement.scrollWidth<=window.innerWidth && window.charts['renter-history-economics-spending'].data.labels.length===90 && window.charts['renter-history-storage-storage'].data.labels.length===365 && window.historyFetches===2 && document.querySelector('[data-history-group="economics"] [data-days="90"]').getAttribute('aria-pressed')==='true';
      })()`,
      empty: `[...document.querySelectorAll('[data-history-status]')].every(n=>n.textContent==='No renter history available yet.') && [...document.querySelectorAll('[data-chart-wrap]')].every(n=>n.hidden)`,
      directory: `document.querySelector('#renterResults a')?.getAttribute('href').includes('address=') && !document.querySelector('#renterResults .renter-copy') && !document.getElementById('renterTable').classList.contains('table-loading') && document.getElementById('renterNext').disabled && document.getElementById('renterStatus').textContent.includes('Showing 1 renters') && !document.getElementById('renterSearch') && !document.querySelector('[data-renter-tab]')`,
      distribution: `document.querySelector('#renterDistributionTableBody a')?.getAttribute('href').includes('address=') && document.getElementById('renterDistributionTableBody').textContent.includes('16.666667%') && window.lastChart.data.datasets[0].data[1]===16.666667`,
      'distribution-empty': `document.getElementById('renterDistributionChart').hidden && document.getElementById('renterDistributionStatus').textContent.includes('not available')`,
      ambiguous: `document.querySelectorAll('#renterCandidates a').length===2 && document.getElementById('renterDetail').hidden`,
      unmapped: `document.getElementById('renterStatus').textContent.includes('No renter wallet association') && document.getElementById('renterDetail').hidden`
    };
    html = html.replace('<script src="/js/renter-currency.js"></script>', `<script>${fs.readFileSync(root+'/js/renter-currency.js','utf8')}</script>`);
    html = html.replace('<script src="/js/renter-format.js"></script>', `<script>${fs.readFileSync(root+'/js/renter-format.js','utf8')}</script>`);
    html = html.replace('<head>', '<head>'+setup).replace(`<script src="/${script}"></script>`, `<script>${fs.readFileSync(path.join(root,script),'utf8')}</script>`);
    html = html.replace('</body>', `<script>setTimeout(async()=>{const result=document.createElement('pre');result.id='test-result';try{result.textContent=(${assertions[name]})&&!document.body.dataset.error?'PASS ${name}':'FAIL ${name} '+document.body.dataset.error;}catch(e){result.textContent='FAIL '+e.message;}document.body.append(result);},1000)</script></body>`);
    const file=path.join(tmp,name+'.html');fs.writeFileSync(file,html);
    const out=cp.spawnSync(process.env.CHROME_BIN || 'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--no-proxy-server','--window-size='+(process.env.RENTER_BROWSER_SIZE || '390,844'),...(process.env.RENTER_SCREENSHOT ? ['--screenshot='+process.env.RENTER_SCREENSHOT] : []),'--user-data-dir='+path.join(tmp,'chrome-'+name),'--dump-dom','--virtual-time-budget=2000','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:2e6});
    const result=out.stdout?.match(/<pre id="test-result">([^<]+)/)?.[1];
    console.log(result || `FAIL ${name}: ${out.error || out.stderr?.slice(-500)}`);
    if(!result?.startsWith('PASS'))process.exitCode=1;
 }
} finally { fs.rmSync(tmp,{recursive:true,force:true}); }
