import test from 'node:test';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const banks=JSON.parse(execFileSync('php',['-r',"require 'api/jackbox-data.php'; echo json_encode(['facts'=>party_jb_facts(),'quiz'=>party_jb_quizzes(),'comedy'=>party_jb_comedy(),'drawing'=>party_jb_draw_prompts()]);"],{cwd:new URL('../',import.meta.url),maxBuffer:8*1024*1024}).toString());
test('Jackbox adaptation banks contain unique prompts and unambiguous answer choices',()=>{
 for(const [name,count] of Object.entries({facts:121,quiz:717,comedy:9600,drawing:6000})){
  const rows=banks[name];assert.equal(rows.length,count);assert.equal(new Set(rows.map(q=>q.key)).size,count);assert.equal(new Set(rows.map(q=>q.question||q.text)).size,count);
  for(const q of rows){assert.ok((q.question||q.text).length>15);if(q.truth){assert.equal(q.fakes.length,3);assert.equal(new Set([q.truth,...q.fakes].map(s=>s.toLowerCase())).size,4);}}
 }
 for(const q of banks.quiz){const [type,a,b]=q.key.split(':');if(type==='math')assert.equal(Number(q.truth),Number(a)*Number(b));if(type==='order')assert.equal(Number(q.truth),Number(a)+Number(b)*3);}
});
