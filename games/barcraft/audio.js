export const tracks = [
  {id:'hours',name:'After hours',genre:'Lo-fi',bpm:86,description:'Dusty keys, a warm bassline, room to breathe.'},
  {id:'concrete',name:'Concrete poetry',genre:'Boom bap',bpm:94,description:'Punchy drums, jazz chords, a little sidewalk swagger.'},
  {id:'orbit',name:'Low orbit',genre:'Trap',bpm:140,description:'Deep 808s, midnight bells, crisp rolling hats.'},
  {id:'grip',name:'Griphop',genre:'Cinematic hip-hop',bpm:90,description:'Kevin MacLeod · A hip-hop instrumental with a cinematic string section.',url:'audio/griphop.mp3',artist:'Kevin MacLeod',source:'https://incompetech.com/music/royalty-free/index.html?isrc=USUAN1100413',art:'grip'},
  {id:'back-to-cool',name:'Back To Cool',genre:'West Coast',bpm:104,description:'Shane Ivers · Gritty drums, upfront bass, and funky electric piano.',url:'audio/back-to-cool.mp3',artist:'Shane Ivers',source:'https://www.silvermansound.com/free-music/back-to-cool',art:'west'},
  {id:'big-boi-pants',name:'Big Boi Pants',genre:'East Coast',bpm:77,description:'Shane Ivers · Deep kicks, orchestral strings, and a vibey organ.',url:'audio/big-boi-pants.mp3',artist:'Shane Ivers',source:'https://www.silvermansound.com/free-music/big-boi-pants',art:'east'},
  {id:'holy-moly',name:'HOLY MOLY',genre:'Heavy hip-hop',bpm:90,description:'Shane Ivers · Tight drums, a thick bassline, and gritty synths.',url:'audio/holy-moly.mp3',artist:'Shane Ivers',source:'https://www.silvermansound.com/free-music/holy-moly',art:'heavy'},
  {id:'the-88',name:'The 88',genre:'Old school',bpm:88,description:'Shane Ivers · A deep drum pocket, smooth bass, and vintage mellotron.',url:'audio/the-88.mp3',artist:'Shane Ivers',source:'https://www.silvermansound.com/free-music/the-88',art:'oldschool'}
];
const midi = n => 440 * 2 ** ((n-69)/12);
function tone(ctx,out,freq,time,duration,volume,type='sine',lowpass=2400){
  const o=ctx.createOscillator(),g=ctx.createGain(),f=ctx.createBiquadFilter();
  o.type=type;o.frequency.value=freq;f.type='lowpass';f.frequency.value=lowpass;
  g.gain.setValueAtTime(0,time);g.gain.linearRampToValueAtTime(volume,time+.008);g.gain.exponentialRampToValueAtTime(.0001,time+duration);
  o.connect(f).connect(g).connect(out);o.start(time);o.stop(time+duration+.02);
}
function kick(ctx,out,t,amp=1){
  const o=ctx.createOscillator(),g=ctx.createGain();o.frequency.setValueAtTime(150,t);o.frequency.exponentialRampToValueAtTime(45,t+.13);
  g.gain.setValueAtTime(.65*amp,t);g.gain.exponentialRampToValueAtTime(.0001,t+.38);o.connect(g).connect(out);o.start(t);o.stop(t+.4);
}
function noise(ctx,out,buffer,t,duration,amp,freq,high=true){
  const s=ctx.createBufferSource(),f=ctx.createBiquadFilter(),g=ctx.createGain();s.buffer=buffer;f.type=high?'highpass':'bandpass';f.frequency.value=freq;f.Q.value=.7;
  g.gain.setValueAtTime(amp,t);g.gain.exponentialRampToValueAtTime(.0001,t+duration);s.connect(f).connect(g).connect(out);s.start(t);s.stop(t+duration+.01);
}
async function renderGroove(track){
  const beat=60/track.bpm,duration=beat*32,ctx=new OfflineAudioContext(2,Math.ceil(duration*44100),44100),master=ctx.createGain();master.gain.value=.73;
  const compressor=ctx.createDynamicsCompressor();compressor.threshold.value=-16;compressor.ratio.value=3;master.connect(compressor).connect(ctx.destination);
  const dust=ctx.createBuffer(1,44100,44100),d=dust.getChannelData(0);for(let i=0;i<d.length;i++)d[i]=Math.random()*2-1;
  const trap=track.id==='orbit',boom=track.id==='concrete';
  const chords=[[48,55,58,63,67],[44,51,55,58,62],[46,53,56,60,65],[43,50,53,58,62]];
  for(let bar=0;bar<8;bar++){
    const t=bar*4*beat,chord=chords[Math.floor(bar/2)];
    const kicks=trap?[0,1.75,2.5,3.25]:boom?[0,1.5,2.75]:[0,1.75,2.5];kicks.forEach(b=>kick(ctx,master,t+b*beat));
    (trap?[2]:[1,3]).forEach(b=>{noise(ctx,master,dust,t+b*beat,.16,.3,1800,false);tone(ctx,master,180,t+b*beat,.1,.12,'triangle');noise(ctx,master,dust,t+b*beat+.018,.11,.13,2600,true);});
    const steps=trap?16:8;for(let h=0;h<steps;h++){const swing=!trap&&h%2?.065*beat:0;noise(ctx,master,dust,t+h*(4/steps)*beat+swing,.045+(h%4===2?.025:0),h%2?.085:.13,7000);}
    if(trap&&bar%2===1)for(let h=0;h<4;h++)noise(ctx,master,dust,t+3.5*beat+h*beat/8,.025,.055,8500);
    [0,1.75,2.5].forEach((b,i)=>tone(ctx,master,midi(chord[0]-12+(i===2?7:0)),t+b*beat,beat*(trap?1.2:.7),trap?.42:.28,'sine'));
    if(trap){[0,1.5,2.75].forEach((b,i)=>{tone(ctx,master,midi(chord[3+i%2]+12),t+b*beat,beat*1.3,.07,'sine');tone(ctx,master,midi(chord[3+i%2]+24),t+b*beat,beat*.45,.018,'sine');});}
    else { [0,2.5].forEach((b,hit)=>chord.slice(1).forEach((n,i)=>{tone(ctx,master,midi(n),t+b*beat+i*.007,beat*(hit?1.1:1.8),.075,'triangle',boom?2300:1500);tone(ctx,master,midi(n+12),t+b*beat+i*.007,beat*.65,.019,'sine');}));
      if(bar%2===1)[0.5,1.5,3].forEach((b,i)=>tone(ctx,master,midi(chord[4]+[12,7,0][i]),t+b*beat,beat*.7,.055,'sine'));
    }
  }
  return ctx.startRendering();
}
export class BeatEngine{
  constructor(){this.bpm=86;this.offset=0;this.running=false;this.cache=new Map();this.track=tracks[0];this.metronome=false;this.volume=.7;this.elapsed=0;}
  async init(){if(!this.ctx){this.ctx=new AudioContext();this.master=this.ctx.createGain();this.master.gain.value=this.volume;this.analyser=this.ctx.createAnalyser();this.analyser.fftSize=256;this.master.connect(this.analyser).connect(this.ctx.destination);}await this.ctx.resume();}
  async load(track){await this.init();this.track=track;if(!this.cache.has(track.id)){let buffer;if(track.url){const response=await fetch(track.url);if(!response.ok)throw Error('The beat could not be loaded. Choose a built-in groove or upload an audio file.');buffer=await this.ctx.decodeAudioData(await response.arrayBuffer());}else if(track.file){buffer=await this.ctx.decodeAudioData(await track.file.arrayBuffer());}else buffer=await renderGroove(track);this.cache.set(track.id,buffer);}this.buffer=this.cache.get(track.id);}
  play(beat=0){this.elapsed=beat;this.startTime=this.ctx.currentTime+.05;this.source=this.ctx.createBufferSource();this.source.buffer=this.buffer;this.source.playbackRate.value=this.bpm/this.track.bpm;this.source.loop=true;this.source.connect(this.master);
    const wait=Math.max(0,-beat)*60/this.bpm;
    const offset=Math.min(this.offset,Math.max(0,this.buffer.duration-.1));
    const barLength=240/this.track.bpm;
    const loopLength=Math.floor((this.buffer.duration-offset)/barLength)*barLength || this.buffer.duration-offset;
    this.source.loopStart=offset;this.source.loopEnd=offset+loopLength;
    const at=offset+(Math.max(0,beat)*60/this.track.bpm)%loopLength;this.source.start(this.startTime+wait,at);this.running=true;this.lastClick=Math.ceil(beat)-1;
  }
  position(){return this.running?this.elapsed+(this.ctx.currentTime-this.startTime)*this.bpm/60:this.elapsed;}
  pause(){if(this.running){this.elapsed=this.position();this.source.stop();this.source.disconnect();this.running=false;}return this.elapsed;}
  stop(){this.pause();this.elapsed=0;}
  setVolume(value){this.volume=value;if(this.master)this.master.gain.setTargetAtTime(value,this.ctx.currentTime,.03);}
  tick(){if(!this.running)return;const pos=this.position(),next=Math.floor(pos+.08*this.bpm/60);if(next>this.lastClick){this.lastClick=next;if(next<0||this.metronome){const t=this.startTime+(next-this.elapsed)*60/this.bpm;if(t>=this.ctx.currentTime-.02)tone(this.ctx,this.master,next%4===0?1200:820,Math.max(t,this.ctx.currentTime),.04,.12,'sine');}}}
}
