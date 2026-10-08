import test from 'node:test';
import assert from 'node:assert/strict';
import {readdir,stat} from 'node:fs/promises';
import path from 'node:path';
test('upload folder contains exactly the source assets, without stale files from previous builds',async()=>{
 const expected=['index.html','portal.css','portal.js','games.js','UPLOAD.txt','.htaccess'];
 for(const folder of ['art','fonts','games','shared','api'])for(const item of await readdir(folder,{recursive:true})){const file=path.join(folder,item);if((await stat(file)).isFile())expected.push(file);}
 const actual=[];for(const item of await readdir('release/party-room',{recursive:true}))if((await stat(path.join('release/party-room',item))).isFile())actual.push(item);
 assert.deepEqual(actual.sort(),expected.sort());
});
