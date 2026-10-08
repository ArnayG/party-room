import test from 'node:test';
import assert from 'node:assert/strict';
import {WordMemory,memoryKey} from '../games/barcraft/word-memory.js';
import {createRhymeChain,getWordPool,wordBankSize,rhymeFamilies} from '../games/barcraft/rhymes.js';
function store(){const data=new Map();return{getItem:key=>data.get(key)||null,setItem:(key,value)=>data.set(key,value)};}
test('dictionary offers broad warm-up and stretch vocabulary',()=>{
  assert.ok(wordBankSize>=3000);assert.ok(rhymeFamilies.length>=110);
  assert.ok(getWordPool(['Everyday','Culture','Nature'],'easy').length>900);
  assert.ok(getWordPool(['Everyday','Culture','Nature'],'hard').length>200);
});
test('fifty 16-bar raps keep targets fresh after save and reload',()=>{
  const storage=store();const shown=new Set();
  for(let session=0;session<50;session++){
    const memory=WordMemory.load(storage);let previousFamily=null;
    for(let bar=0;bar<16;bar+=4){
      const chain=createRhymeChain({categories:['Everyday','Culture','Nature'],length:4,usedWords:memory.words,recentFamilies:memory.families,previousFamily});
      previousFamily=chain[0].rhymeFamily;memory.rememberFamily(previousFamily);
      for(const entry of chain){assert.ok(!shown.has(entry.word.toLowerCase()),`Repeated ${entry.word}`);shown.add(entry.word.toLowerCase());memory.remember(entry.word);}
    }
    memory.save(storage);
  }
  assert.equal(shown.size,800);
});
test('random prompts avoid repeats across sessions and recycle oldest words',()=>{
  const storage=store(),entries=['Night','light','flight'].map(word=>({word}));
  let memory=WordMemory.load(storage);memory.remember('Night');memory.remember('light');memory.save(storage);
  memory=WordMemory.load(storage);assert.equal(memory.pick(entries).word,'flight');memory.remember('flight');
  assert.equal(memory.pick(entries).word,'Night');memory.remember('Night');assert.equal(memory.pick(entries).word,'light');
});
test('rhyme exhaustion recycles targets without duplicates within a chain',()=>{
  const categories=['Feelings'],pool=getWordPool(categories,'hard');
  const chain=createRhymeChain({categories,level:'hard',usedWords:pool.map(p=>p.word),length:4});
  assert.equal(chain.length,4);assert.equal(new Set(chain.map(p=>p.word)).size,4);
});
test('storage denial and malformed saved data still allow a session',()=>{
  const inaccessible={getItem(){throw new Error('denied')},setItem(){throw new Error('denied')}};
  const memory=WordMemory.load(inaccessible);memory.remember('light');assert.doesNotThrow(()=>memory.save(inaccessible));
  assert.deepEqual(WordMemory.load({getItem:()=>'{bad'}).words,[]);
  const data=store();data.setItem(memoryKey,JSON.stringify({words:['night',null,8,'NIGHT'],families:7}));assert.deepEqual(WordMemory.load(data).words,['night']);
});
