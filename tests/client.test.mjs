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
