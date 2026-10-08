export const memoryKey='barcraft.practice-history.v1';
const normalized=word=>word.toLowerCase().trim();
export class WordMemory{
  constructor(data={}){this.words=Array.isArray(data.words)?[...new Set(data.words.filter(w=>typeof w==='string'&&w.length<100).map(normalized))].slice(-5000):[];this.families=Array.isArray(data.families)?data.families.filter(f=>typeof f==='string').slice(-8):[];}
  static load(storage){try{return new WordMemory(JSON.parse(storage.getItem(memoryKey)||'{}')||{});}catch{return new WordMemory();}}
  remember(word){const key=normalized(word);this.words=this.words.filter(w=>w!==key);this.words.push(key);this.words=this.words.slice(-5000);}
  rememberFamily(family){this.families.push(family);this.families=this.families.slice(-8);}
  save(storage){try{storage.setItem(memoryKey,JSON.stringify({version:1,words:this.words,families:this.families}));}catch{/* Private browsing can disable storage; in-memory avoidance still works. */}}
  pick(entries,random=Math.random){const ranks=new Map(this.words.map((word,i)=>[word,i]));const fresh=entries.filter(p=>!ranks.has(normalized(p.word)));if(fresh.length)return fresh[Math.floor(random()*fresh.length)];return [...entries].sort((a,b)=>ranks.get(normalized(a.word))-ranks.get(normalized(b.word)))[0];}
}
