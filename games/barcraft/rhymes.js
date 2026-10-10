import {longSessionFamilies} from './long-session-rhymes.js?v=20261009-party-1';
import {extraFamilies} from './expanded-rhymes.js?v=20261009-party-1';
// Curated by sound, not spelling. Each category can sustain a four-bar chain.
const baseFamilies = [
  {id:'ight',label:'-ight',level:'easy',words:{
    Everyday:['night','light','midnight','daylight'], Culture:['spotlight','limelight','write','recite'],
    Nature:['moonlight','sunlight','starlight','twilight'], Feelings:['delight','fright','spite','uptight'],
    Street:['fight','streetlight','taillight','stoplight'], Wildcard:['knight','sprite','flight','bite']
  }},
  {id:'ay',label:'-ay',level:'easy',words:{
    Everyday:['day','tray','pay','delay'], Culture:['play','ballet','display','screenplay'],
    Nature:['bay','clay','spray','ray'], Feelings:['dismay','okay','betray','away'],
    Street:['alleyway','freeway','subway','highway'], Wildcard:['x-ray','getaway','holiday','doorway']
  }},
  {id:'ain',label:'-ain',level:'easy',words:{
    Everyday:['train','chain','stain','drain'], Culture:['refrain','entertain','campaign','explain'],
    Nature:['rain','plain','grain','terrain'], Feelings:['pain','strain','complain','disdain'],
    Street:['lane','train','chain','drain'], Wildcard:['brain','plane','domain','arcane']
  }},
  {id:'eam',label:'-eem',level:'easy',words:{
    Everyday:['cream','steam','seam','team'], Culture:['theme','stream','meme','dream'],
    Nature:['stream','beam','gleam','sunbeam'], Feelings:['dream','esteem','extreme','scream'],
    Street:['team','scheme','steam','beam'], Wildcard:['laser beam','daydream','moonbeam','ice cream']
  }},
  {id:'ow',label:'-oh',level:'easy',words:{
    Everyday:['dough','toe','throw','go'], Culture:['show','flow','glow','blow'],
    Nature:['snow','crow','doe','floe'], Feelings:['low','woe','glow','grow'],
    Street:['row','tow','slow','go'], Wildcard:['UFO','yo','whoa','glow']
  }},
  {id:'ation',label:'-ation',level:'hard',words:{
    Everyday:['conversation','transportation','reservation','information'],
    Culture:['improvisation','illustration','animation','narration'],
    Nature:['vegetation','evaporation','condensation','pollination'],
    Feelings:['frustration','determination','inspiration','hesitation'],
    Street:['destination','foundation','navigation','renovation'],
    Wildcard:['levitation','teleportation','transformation','simulation']
  }},
  {id:'otion',label:'-oh-shun',level:'hard',words:{
    Everyday:['lotion','motion','promotion','commotion'], Culture:['emotion','notion','devotion','promotion'],
    Nature:['ocean','motion','wave motion','slow motion'], Feelings:['emotion','devotion','notion','demotion'],
    Street:['motion','promotion','commotion','locomotion'], Wildcard:['potion','notion','locomotion','slow motion']
  }},
  {id:'ition',label:'-ish-un',level:'hard',words:{
    Everyday:['permission','admission','addition','condition'], Culture:['edition','audition','composition','exhibition'],
    Nature:['emission','fission','transition','decomposition'], Feelings:['ambition','inhibition','suspicion','intuition'],
    Street:['demolition','position','opposition','expedition'], Wildcard:['magician','apparition','ammunition','premonition']
  }},
  {id:'ection',label:'-ek-shun',level:'hard',words:{
    Everyday:['connection','collection','selection','inspection'], Culture:['direction','projection','reflection','perfection'],
    Nature:['reflection','deflection','convection','intersection'], Feelings:['affection','rejection','reflection','introspection'],
    Street:['intersection','direction','protection','inspection'], Wildcard:['resurrection','misdirection','detection','projection']
  }}
];

const catalog=new Map();
for(const family of [...baseFamilies,...extraFamilies,...longSessionFamilies]){
  const key=family.level+':'+family.id;
  if(!catalog.has(key))catalog.set(key,{...family,words:{}});
  const entry=catalog.get(key);
  for(const [category,words] of Object.entries(family.words))entry.words[category]=[...new Set([...(entry.words[category]||[]),...words])];
}
export const rhymeFamilies=[...catalog.values()];
export const wordBankSize=new Set(rhymeFamilies.flatMap(f=>Object.values(f.words).flat())).size;
export function getWordPool(categories,level){const pool=new Map();for(const f of rhymeFamilies.filter(f=>f.level===level))for(const category of categories)for(const word of f.words[category]||[])if(!pool.has(word.toLowerCase()))pool.set(word.toLowerCase(),{word,category});return [...pool.values()];}

export function createRhymeChain({categories,level='easy',length=4,previousFamily=null,usedWords=[],recentFamilies=[],random=Math.random}) {
  const ranks=new Map(usedWords.map((word,i)=>[word.toLowerCase(),i]));
  const pools=rhymeFamilies.filter(f=>f.level===level).map(f=>{
    const entries=new Map();
    for(const category of categories)for(const word of f.words[category]||[])if(!entries.has(word))entries.set(word,{word,category});
    const values=[...entries.values()];
    return {family:f,entries:values,fresh:values.filter(p=>!ranks.has(p.word.toLowerCase()))};
  }).filter(p=>p.entries.length>=length);
  if(!pools.length)throw new Error('No rhyme family can satisfy these categories.');
  const unused=pools.filter(p=>p.fresh.length>=length);
  const maxFresh=Math.max(...pools.map(p=>p.fresh.length));
  const available=unused.length?unused:pools.filter(p=>p.fresh.length===maxFresh);
  const different=available.filter(p=>p.family.id!==previousFamily);
  const options=different.length?different:available;
  const lessRecent=options.filter(p=>!recentFamilies.slice(-4).includes(p.family.id));
  const candidates=lessRecent.length?lessRecent:options;
  const selected=candidates[Math.floor(random()*candidates.length)];
  const shuffled=[...selected.fresh];
  for(let i=shuffled.length-1;i>0;i--){const j=Math.floor(random()*(i+1));[shuffled[i],shuffled[j]]=[shuffled[j],shuffled[i]];}
  const oldest=selected.entries.filter(p=>ranks.has(p.word.toLowerCase())).sort((a,b)=>ranks.get(a.word.toLowerCase())-ranks.get(b.word.toLowerCase()));
  return [...shuffled,...oldest].slice(0,length).map((entry,index)=>({...entry,rhymeFamily:selected.family.id,rhymeLabel:selected.family.label,chainIndex:index+1,chainLength:length}));
}
