const fs=require('node:fs'),cp=require('node:child_process'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'../..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'alerts-browser-'));
const script=s=>`<script>${s}</script>`,key='ed25519:'+'a'.repeat(64);
try{
for(const width of [1440,480]){
let html=cp.execFileSync('php',[path.join(root,'tests/dashboard/render-fixture.php'),'host_alerts.php',key],{cwd:root,encoding:'utf8'});
html=html.replace('</head>',()=>`<style>${['css/style.css','css/theme.css','css/components/data-page.css','css/pages/host-alerts.css'].map(p=>fs.readFileSync(path.join(root,p),'utf8')).join('\n')}</style>`+script(`
window.errors=[];window.requests=[];window.fail=false;
addEventListener('error',e=>errors.push(e.message));addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
window.fetch=async(url,options)=>{requests.push({url,...options});return {ok:!fail,json:async()=>fail?{error:'Fixture subscription failure'}:{success:true}};};
` )+'</head>');
html=html.replace('</body>',()=>script(`
document.addEventListener('DOMContentLoaded',()=>setTimeout(async()=>{
const result=document.createElement('pre');result.id='test-result';
try{
const check=(v,m)=>{if(!v)throw new Error(m)},tick=()=>new Promise(r=>setTimeout(r,10));
const form=document.getElementById('hostAlertsForm'),service=document.getElementById('service'),recipient=document.getElementById('recipient'),status=document.getElementById('subscriptionStatus'),key=document.getElementById('hostIdentifier');
check(key.value==='${key}','public key prefill preserved');check(!document.querySelector('h1').closest('.card'),'unboxed shared title');
check(document.querySelectorAll('.card').length===1,'shared content card');
check(document.querySelector('.host-alerts-hero__copy').textContent.includes('operational notifications'),'intro stays visible');
check(document.querySelector('.host-alerts-section-copy').textContent.includes('public key'),'setup explanation stays visible');check(document.querySelector('.host-alerts-hero__note').textContent.includes('4 hours'),'delivery frequency visible');
check(document.querySelectorAll('.host-alerts-rule strong').length===3,'alert explanations visible');
const submit=()=>form.dispatchEvent(new Event('submit',{cancelable:true}));
key.value='invalid';recipient.value='fixture@example.invalid';submit();await tick();check(!requests.length,'invalid public key blocks request');
key.value='${key}';submit();await tick();check(requests[0].url==='/api/v1/alerts/subscribe','same subscription endpoint');check(JSON.parse(requests[0].body).service==='email','email payload');check(status.textContent.includes('Successfully'),'success status');check(recipient.value==='','recipient cleared on success');
service.value='telegram';service.dispatchEvent(new Event('change'));check(!document.getElementById('telegramInstructions').classList.contains('is-hidden'),'telegram guidance visible');
recipient.value='123456';submit();await tick();check(JSON.parse(requests[1].body).recipient==='123456','telegram payload');
service.value='pushover';service.dispatchEvent(new Event('change'));check(document.getElementById('telegramInstructions').classList.contains('is-hidden'),'telegram instructions hidden');
recipient.value='test-token';window.fail=true;submit();await tick();check(status.textContent==='Fixture subscription failure','error status retained');check(!document.getElementById('submitSubscriptionBtn').disabled,'can retry');check(recipient.value==='test-token','recipient retained on error');
check(document.documentElement.scrollWidth<=innerWidth,'no horizontal overflow');check(!errors.length,errors.join(';'));result.textContent='PASS alerts ${width}';
}catch(e){result.textContent='FAIL '+e.stack}document.body.append(result);
},50));` )+'</body>');
const file=path.join(tmp,width+'.html');fs.writeFileSync(file,html);
const run=cp.spawnSync(process.env.CHROME_BIN||'google-chrome',['--headless','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--window-size='+width+',900','--user-data-dir='+path.join(tmp,'profile'),'--dump-dom','--virtual-time-budget=1500','file://'+file],{encoding:'utf8',timeout:20000,maxBuffer:5e6});
const result=run.stdout?.match(/<pre id="test-result">([\s\S]*?)<\/pre>/)?.[1];console.log(result||run.stderr?.slice(-500));assert(result?.startsWith('PASS'));
}
}finally{fs.rmSync(tmp,{recursive:true,force:true})}
