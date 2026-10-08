import {mkdir,cp} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.dirname(fileURLToPath(import.meta.url));
const destination=path.join(root,'release','party-room');
await mkdir(destination,{recursive:true});
for(const file of ['index.html','portal.css','portal.js','games.js','UPLOAD.txt','.htaccess'])await cp(path.join(root,file),path.join(destination,file));
for(const folder of ['art','fonts','games','shared','api'])await cp(path.join(root,folder),path.join(destination,folder),{recursive:true});
// Ship the site and PHP room service. Development files stay outside the upload.
console.log('Upload folder:',destination);
