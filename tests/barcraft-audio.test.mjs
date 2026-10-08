import test from 'node:test';
import assert from 'node:assert/strict';
import {BeatEngine,tracks} from '../games/barcraft/audio.js';
function engine(){const e=new BeatEngine();e.track=tracks[0];e.bpm=86;e.buffer={duration:32*60/86};e.master={};e.ctx={currentTime:10,createBufferSource(){return{playbackRate:{value:1},connect(){},disconnect(){},start(...args){this.started=args},stop(){this.stopped=true}}}};return e;}
const close=(actual,expected)=>assert.ok(Math.abs(actual-expected)<1e-8,`${actual} != ${expected}`);
test('count-in delays music for exactly four beats while the clock counts up',()=>{const e=engine();e.play(-4);close(e.source.started[0],10.05+240/86);close(e.source.started[1],0);e.ctx.currentTime=10.05+240/86;close(e.position(),0);});
test('pause and resume preserve the beat and track position',()=>{const e=engine();e.play();e.ctx.currentTime=10.05+6*60/86;close(e.pause(),6);assert.equal(e.source.stopped,true);e.ctx.currentTime=20;e.play(e.elapsed);close(e.source.started[1],6*60/86);e.ctx.currentTime=20.05+60/86;close(e.position(),7);});
test('a tempo change scales playback rate and beat clock together',()=>{const e=engine();e.bpm=120;e.play(4);close(e.source.playbackRate.value,120/86);e.ctx.currentTime=10.05+2;close(e.position(),8);});
test('loop boundaries preserve whole bars for recordings',()=>{const e=engine();e.buffer.duration=207;e.offset=.03;e.play(400);const loop=e.source.loopEnd-e.source.loopStart;close(loop/(240/86),Math.floor(loop/(240/86)));assert.ok(e.source.started[1]>=e.source.loopStart);assert.ok(e.source.started[1]<e.source.loopEnd);});
test('reset stops sound and clears the clock',()=>{const e=engine();e.play(8);e.stop();assert.equal(e.running,false);assert.equal(e.position(),0);});
