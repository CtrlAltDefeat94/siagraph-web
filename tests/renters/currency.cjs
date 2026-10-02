const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
(async()=>{
 for(const currency of ['eur','usd','cad','gbp','sc']) {
  const listeners={};
  const context={window:{APP_LOCALE:'en-US'},document:{cookie:'',addEventListener:(name,fn)=>listeners[name]=fn,dispatchEvent:()=>{}},Event,Intl,AbortController,setTimeout,clearTimeout,getCookie:()=>currency,fetch:async url=>({ok:true,json:async()=>url==='/api/v1/exchange_rate.php'?{eur:7,usd:8,cad:9,gbp:10}:[{date:'2020-01-01',eur:2,usd:3,cad:4,gbp:5},{date:'2020-01-02',eur:4,usd:6,cad:8,gbp:10}]})};
  vm.createContext(context);
  for(const file of ['renter-format.js','renter-currency.js'])vm.runInContext(fs.readFileSync(__dirname+'/../../js/'+file,'utf8'),context);
  const api=context.window.renterCurrency;await api.ready();const value='1000000000000000000000000';
  assert.equal(api.code,currency.toUpperCase());
  assert.equal(api.plot(value,{}),{eur:7,usd:8,cad:9,gbp:10,sc:1}[currency], 'Current values use the hourly snapshot, not the daily average');
  assert.ok(api.format(value,{}).includes(currency.toUpperCase()));
  assert.equal(api.plot(value,{},'2020-01-01'),{eur:2,usd:3,cad:4,gbp:5,sc:1}[currency]);
  assert.equal(api.plot(value,{},'1999-01-01'),currency==='sc'?1:null);
  assert.equal(api.plot(null,{},'2020-01-01'),null);
  assert.equal(api.plot('0',{},'2020-01-01'),0);
  await listeners.currencyChange({detail:'usd'});
  assert.equal(api.code,'USD'); assert.equal(api.plot(value,{},'2020-01-01'),3);
 }
 console.log('PASS configured currencies, dated conversion, missing rates, nulls and zeros');
})().catch(e=>{console.error(e);process.exitCode=1});
