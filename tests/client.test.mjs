import test,{beforeEach,afterEach} from 'node:test';
import assert from 'node:assert/strict';
import {PartyClient} from '../shared/party-client.js';
let originalFetch,originalStorage;
beforeEach(()=>{originalFetch=globalThis.fetch;originalStorage=globalThis.sessionStorage;const store=new Map();globalThis.sessionStorage={setItem:(k,v)=>store.set(k,v),getItem:k=>store.get(k),removeItem:k=>store.delete(k)};});
afterEach(()=>{globalThis.fetch=originalFetch;globalThis.sessionStorage=originalStorage;});
const response=room=>({ok:true,json:async()=>({room})});
const room=(version=1,code='ABCDE')=>({version,code,round:1});
function client(states=[]){const c=new PartyClient('wavelength',state=>states.push(state),()=>{});c.session={code:'ABCDE',token:'secret'};c.room=room();return c;}
test('rapid dial updates are sent in order rather than dropped while busy',async()=>{const c=client(),sent=[];globalThis.fetch=async(url,options)=>{sent.push(JSON.parse(options.body));await new Promise(r=>setTimeout(r,5));return response(room(sent.length+1));};await Promise.all([c.act('guess',{value:22}),c.act('guess',{value:77})]);assert.deepEqual(sent.map(s=>s.value),[22,77]);assert.equal(c.room.version,3);assert.equal(c.busy,false);});
test('a stale poll cannot reopen a room after leaving',async()=>{const states=[],c=client(states);let completePoll;globalThis.fetch=async(url,options)=>JSON.parse(options.body).action==='state'?new Promise(resolve=>{completePoll=resolve;}):response(room(3));const poll=c.request('state');await c.leave();completePoll(response(room(2)));await poll;assert.equal(c.session,null);assert.deepEqual(states,[null]);});
test('older poll versions cannot overwrite a newer game phase',async()=>{const states=[],c=client(states);let finish;globalThis.fetch=async()=>new Promise(resolve=>{finish=resolve;});const poll=c.request('state');c.room=room(8);finish(response(room(2)));await poll;assert.equal(c.room.version,8);assert.deepEqual(states,[]);});
test('a failed action does not block later actions',async()=>{const c=client();let calls=0;globalThis.fetch=async()=>++calls===1?{ok:false,status:409,json:async()=>({error:'Try again'})}:response(room(2));await assert.rejects(c.act('guess',{value:20}),/Try again/);await c.act('guess',{value:30});assert.equal(calls,2);});
test('recent prompt history persists across sessions without saving an active secret word',async()=>{
 const {recentPrompts,rememberPrompts}=await import('../shared/prompt-memory.js');const oldStorage=globalThis.localStorage,values=new Map();globalThis.localStorage={getItem:k=>values.get(k),setItem:(k,v)=>values.set(k,v)};
 try{rememberPrompts('imposter',{phase:'clues',word:'Pizza'});assert.deepEqual(recentPrompts('imposter'),[]);rememberPrompts('imposter',{phase:'reveal',word:'Pizza'});rememberPrompts('imposter',{phase:'reveal',word:'Banana'});rememberPrompts('imposter',{phase:'reveal',word:'Pizza'});assert.deepEqual(recentPrompts('imposter'),['Banana','Pizza']);rememberPrompts('wavelength',{phase:'clue',spectrum:['Cold','Hot']});assert.deepEqual(recentPrompts('wavelength'),['Cold | Hot']);rememberPrompts('scattergories',{phase:'writing',categories:['Animals','Drinks']});assert.deepEqual(recentPrompts('scattergories'),['Animals','Drinks']);}finally{globalThis.localStorage=oldStorage;}
});
test('long prompt histories preserve the newest entries without exceeding the HTTP request limit',async()=>{
 const {recentForRequest,recentPrompts}=await import('../shared/prompt-memory.js');const previous=globalThis.localStorage;const values=Array.from({length:1600},(_,i)=>`${i}:`+'💡'.repeat(50));globalThis.localStorage={getItem:()=>JSON.stringify(values)};
 try{const recent=recentForRequest('question-quest');assert.equal(recentPrompts('question-quest').length,1600);assert.ok(recent.length>0&&recent.length<1600);assert.equal(recent.at(-1),values.at(-1));assert.ok(new TextEncoder().encode(JSON.stringify(recent)).length<10100);}finally{globalThis.localStorage=previous;}
});
test('Rulebreakers remembers example words across sessions but stores the hidden rule key only at reveal',async()=>{
 const {rememberPrompts,recentPrompts}=await import('../shared/prompt-memory.js');const previous=globalThis.localStorage;let stored='[]';globalThis.localStorage={getItem:()=>stored,setItem:(key,value)=>{stored=value;}};
 try{const examples={yes:['apple','house','mouse'],no:['cat','dog','fox']};rememberPrompts('rulebreakers',{phase:'deducing',puzzleKey:'rule:a0',examples,state:{probes:[{word:'chair'}]}});const before=recentPrompts('rulebreakers');assert.ok(before.includes('rule-word:apple'));assert.ok(before.includes('rule-word:chair'));assert.ok(!before.includes('rule:a0'));rememberPrompts('rulebreakers',{phase:'reveal',puzzleKey:'rule:a0',examples});assert.ok(recentPrompts('rulebreakers').includes('rule:a0'));}finally{globalThis.localStorage=previous;}
});

test('shared party session restores in a different game and actions carry the selected game epoch',async()=>{
 const states=[];sessionStorage.setItem('party-room.session',JSON.stringify({game:'wavelength',code:'ABCDE',token:'secret',id:'you'}));let sent;globalThis.fetch=async(url,opts)=>{sent=JSON.parse(opts.body);return response({...room(2),game:'imposter',gameEpoch:4});};const c=new PartyClient('imposter',s=>states.push(s),()=>{});assert.equal(await c.restore('ABCDE'),true);assert.equal(c.session.token,'secret');assert.equal(c.room.game,'imposter');await c.act('vote',{suspect:'host'});assert.equal(sent.gameEpoch,4);assert.equal(JSON.parse(sessionStorage.getItem('party-room.session')).game,'imposter');c.clear();
});
