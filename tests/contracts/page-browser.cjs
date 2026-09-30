const fs=require('fs'),cp=require('child_process'),os=require('os'),path=require('path');
const root=path.resolve(__dirname,'../..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'host-contract-page-'));
const profile=process.env.CONTRACT_PROFILE||'host';
const key='b'.repeat(64),wallet='a'.repeat(76),cursor='1100:'+ 'c'.repeat(64)+':1000:18446744073709551615:1';
try {
 for(const scenario of ['active','completed','error']) {
  let html=cp.execFileSync('php',['-r',`$_GET['${profile==='host'?'public_key':'address'}']='${profile==='host'?key:wallet}'; require '${root}/${profile}_contracts.php';`],{encoding:'utf8'});
  html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'').replace(/<link\b[^>]*>/g,'');
  const setup=`<style>${['css/theme.css','css/pages/host.css','css/pages/active-contracts.css'].map(file=>fs.readFileSync(root+'/'+file,'utf8')).join('\n')} body{background:#171717;color:#eee}</style><script>
  window.requests=[];window.fail=true;window.errors=[];window.getCookie=()=> 'sc';
  addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
  window.fetch=async url=>{const u=new URL(url,'https://test.local');requests.push(u.pathname+u.search);
    if('${scenario}'==='error'&&u.pathname.includes('completed')&&window.fail){window.fail=false;return {ok:false,json:async()=>({errors:[{message:'Temporary test failure'}]})};}
    const completed=u.pathname.includes('completed');const last=completed?!!u.searchParams.get('after'):u.searchParams.get('page')==='2';
    const empty=!!u.searchParams.get('search');const row={contract_id:(last?'d':'c').repeat(64),renter_wallet_address:'${wallet}',host_public_key:'${key}',filesize:'1234',windowend:'1200',renewed_from_contract_id:'e'.repeat(64),revenue_locked:'1000000000000000000000000',resolution_type:'renewal',resolution_height:'1100'};
    return {ok:true,json:async()=>({data:{items:empty?[]:Array.from({length:last?1:25},()=>row),pagination:{has_more:!last&&!empty,next_after:!last&&!empty?'${cursor}':null}},meta:{generated_at:'2026-09-11T00:00:00Z'},errors:[]})};};
  </script>`;
  html=html.replace('</head>',setup+'</head>');
  for(const file of ['renter-format.js','renter-currency.js','active-contracts.js','host-contracts.js'])html=html.replace('</body>',`<script>${fs.readFileSync(root+'/js/'+file,'utf8')}</script></body>`);
  html=html.replace('</body>',`<script>
  const pause=()=>new Promise(r=>setTimeout(r,80));const check=(ok,label)=>{if(!ok)throw Error(label)};
  addEventListener('DOMContentLoaded',async()=>{const out=document.createElement('pre');out.id='test-result';try{
    await pause();await pause();
    const initial='${scenario}'==='active'?'active':'completed';
    let panel=document.querySelector('[data-contract-list="'+initial+'"]');
    check(!panel.hidden,'initial hash tab');check(requests.length===1,'only selected tab fetched');
    if('${scenario}'==='error'){check(panel.textContent.includes('Temporary test failure'),'error visible');panel.querySelector('[data-list-refresh]').click();await pause();}
    check(panel.querySelector('a[href="/contract/${'c'.repeat(64)}"]'),'contract link');
    panel.querySelector('[data-list-next]').click();await pause();check(panel.textContent.includes('Page 2'),'next page');
    check(panel.querySelector('[data-list-next]').disabled,'last page');
    if(initial==='completed')check(requests.some(r=>r.includes('after=1100')),'cursor sent');
    panel.querySelector('[data-list-previous]').click();await pause();check(panel.textContent.includes('Page 1'),'previous page');
    const other=initial==='active'?'completed':'active';document.querySelector('[data-contract-tab="'+other+'"]').click();await pause();
    panel=document.querySelector('[data-contract-list="'+other+'"]');check(!panel.hidden&&location.hash==='#'+other,'tab switches and updates URL');
    const form=panel.querySelector('form');form.elements.search.value='f'.repeat(64);form.dispatchEvent(new Event('submit',{cancelable:true}));await pause();
    check(panel.textContent.includes('No matching contract'),'search empty');check(panel.querySelector('[data-list-next]').disabled,'search resets pagination');
    form.elements.search.value='';form.elements.sort.value=other==='completed'?'oldest':'size';form.dispatchEvent(new Event('submit',{cancelable:true}));await pause();
    check(requests.at(-1).includes('sort='+form.elements.sort.value),'sort applied');
    check(document.querySelector('a[href*="/${profile}?${profile==='host'?'public_key':'address'}="]').getAttribute('href').endsWith('#contracts'),'back to profile');
    check(document.documentElement.scrollWidth<=innerWidth,'mobile overflow contained');check(!errors.length,errors.join(';'));
    out.textContent='PASS ${profile} page ${scenario}';
  }catch(e){out.textContent='FAIL page ${scenario}: '+e.message;}document.body.append(out);});
  </script></body>`);
  const file=path.join(tmp,scenario+'.html');fs.writeFileSync(file,html);
  const run=cp.spawnSync('google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--no-proxy-server','--window-size=390,844','--user-data-dir='+path.join(tmp,'chrome-'+scenario),'--dump-dom','--virtual-time-budget=4000','file://'+file+(scenario==='active'?'#active':'#completed')],{encoding:'utf8',timeout:20000,maxBuffer:2e6});
  const result=run.stdout?.match(/<pre id="test-result">([^<]+)/)?.[1];console.log(result||run.stderr);if(!result?.startsWith('PASS'))process.exitCode=1;
 }
}finally{fs.rmSync(tmp,{recursive:true,force:true});}
