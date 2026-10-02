const assert=require('node:assert/strict');
global.window={DashboardRanges:require('../../js/dashboard/ranges.js')};
require('../../js/dashboard/sources.js');require('../../js/dashboard/host-sources.js');
const api=window.DashboardHostSources;
const rows=[
{date:'2024-01-01',revenue:null},
{date:'2024-02-01',revenue:'0',locked_collateral:'1000000000000000000000000',active_contracts:0},
{date:'2024-03-01',revenue:'2000000000000000000000000',burned_funds:'1000000000000000000000000',successful_contracts:1},
{date:'2024-03-02',revenue:'3000000000000000000000000',successful_contracts:2,locked_collateral:'2000000000000000000000000'},
{date:'2024-03-03',revenue:null,storage_price:1e12,upload_price:1e12,download_price:null},
];
const responses={'/host':{hosts:rows},'/forecast':{scheduled_revenue:[{unlock_date:'2024-05-01',revenue_sc:2}]},'/api/v1/daily/exchange_rate':[{date:'2024-03-01',eur:3},{date:'2024-03-02',eur:5},{date:'2024-03-31',eur:4}],'/api/v1/daily/compare_metrics':{actual:{coin_price:{eur:2}}}};
global.fetch=async url=>({ok:url in responses,json:async()=>responses[url]});
(async()=>{
let data=await api.load({kind:'host-economics',url:'/host',scheduled:'/forecast'});
assert.equal(data.anchorDate,'2024-03-01');assert.equal(data.rows[0].earned.sc,null);assert.equal(data.rows[1].earned.sc,0);
assert.equal(data.rows[2].earned.sc,5);assert.equal(data.rows[2].earned.eur,21);assert.equal(data.rows[2].burned.sc,-1);assert.equal(data.rows[2].locked.sc,2);
assert.equal(data.rows[1].earned.eur,0);assert.equal(data.rows[2].earned.usd,null);
const missingRate=api.monthly(rows,{history:[{date:'2024-03-01',eur:3}]});
assert.equal(missingRate[2].earnedMoney.eur,null,'Missing daily rates must not produce partial fiat totals');
assert.equal(missingRate[2].earnedMoney.sc,5);
assert.equal(data.rows[3].earned,undefined);assert.equal(data.rows[4].anticipated.eur,4);
data=await api.load({kind:'host-daily',url:'/host'});assert.equal(data.rows.at(-1).storage_price.sc,4320);assert.equal(data.rows.at(-1).upload_price.sc,1);assert.equal(data.rows.at(-1).download_price.sc,null);
data=await api.load({kind:'host-contracts',url:'/host'});assert.equal(data.rows[2].successful,3);assert.equal(data.rows[1].active,0);
data=await api.load({kind:'host-economics',url:'/host',scheduled:'/missing'});assert(data.warnings.length);assert.equal(data.rows[2].earned.sc,5);
data=await api.load({kind:'host-economics',url:'/missing',scheduled:'/forecast'});assert.equal(data.rows.at(-1).anticipated.sc,2);
console.log('PASS host monthly flows, snapshots, prices, rates, forecasts, missing/zero values, and partial failures');
})().catch(e=>{console.error(e);process.exitCode=1});
