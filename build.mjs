import {mkdir,cp,mkdtemp,rename,stat} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.dirname(fileURLToPath(import.meta.url));
const release=path.join(root,'release');
const destination=path.join(release,'party-room');
await mkdir(release,{recursive:true});
// Assemble a fresh directory so old releases cannot contribute stale assets.
const staging=await mkdtemp(path.join(release,'.build-'));
for(const file of ['index.html','portal.css','portal.js','games.js','UPLOAD.txt','.htaccess'])await cp(path.join(root,file),path.join(staging,file));
for(const folder of ['art','fonts','games','shared','api'])await cp(path.join(root,folder),path.join(staging,folder),{recursive:true});
let exists=false;try{exists=(await stat(destination)).isDirectory();}catch(error){if(error.code!=='ENOENT')throw error;}
// Preserve the prior generated folder rather than delete files from the workspace.
if(exists)await rename(destination,path.join(release,`previous-${path.basename(staging)}`));
await rename(staging,destination);
console.log('Upload folder:',destination);
