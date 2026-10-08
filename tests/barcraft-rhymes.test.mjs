import test from 'node:test';
import assert from 'node:assert/strict';
import {createRhymeChain,rhymeFamilies} from '../games/barcraft/rhymes.js';
const categories=['Everyday','Culture','Nature','Feelings','Street','Wildcard'];
test('every category supports distinct two- and four-bar rhymes at both difficulties',()=>{
  for(const category of categories)for(const level of ['easy','hard'])for(const length of [2,4]){
    let previousFamily=null;
    for(let i=0;i<40;i++){
      const chain=createRhymeChain({categories:[category],level,length,previousFamily});
      assert.equal(chain.length,length);assert.equal(new Set(chain.map(p=>p.word)).size,length);
      assert.ok(chain.every(p=>p.category===category));assert.equal(new Set(chain.map(p=>p.rhymeFamily)).size,1);
      assert.notEqual(chain[0].rhymeFamily,previousFamily);assert.deepEqual(chain.map(p=>p.chainIndex),Array.from({length},(_,index)=>index+1));
      previousFamily=chain[0].rhymeFamily;
    }
  }
});
test('mixed category chains keep selected categories and pronunciation families',()=>{
  for(let i=0;i<80;i++){
    const chain=createRhymeChain({categories:['Nature','Culture'],level:i%2?'hard':'easy',length:4});
    const family=rhymeFamilies.find(f=>f.id===chain[0].rhymeFamily);
    assert.ok(chain.every(p=>['Nature','Culture'].includes(p.category)&&family.words[p.category].includes(p.word)));
    assert.equal(new Set(chain.map(p=>p.word)).size,4);
  }
});
test('invalid categories fail rather than silently ignoring the selection',()=>{
  assert.throws(()=>createRhymeChain({categories:['missing']}),/No rhyme family/);
});
