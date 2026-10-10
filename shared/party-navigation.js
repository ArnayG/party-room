export const portalUrl=()=>new URL('../',import.meta.url);
export function gameUrl(game,code){const url=game==='party'?portalUrl():new URL(`games/${game}/`,portalUrl());if(code)url.searchParams.set('room',code);return url;}
export function followGame(game,code){const url=gameUrl(game,code).href;if(location.href!==url){if(location.assign)location.assign(url);else location.href=url;}}
