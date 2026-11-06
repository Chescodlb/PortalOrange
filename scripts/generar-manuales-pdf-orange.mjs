// Print the reviewed local manuals to PDF using Chrome; no remote assets or services.
import {spawn} from 'node:child_process';
import {readFile,writeFile,mkdtemp,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
const out=resolve('docs/manuales/20261106');
const manuals=JSON.parse(await readFile(join(out,'fuentes/manifest.json'),'utf8'));
const profile=await mkdtemp(join(tmpdir(),'orange-manual-print-'));
const chrome=spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',[
 '--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking',
 '--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
let ws;
try {
 let port;
 for(let i=0;i<100;i++){try{port=(await readFile(join(profile,'DevToolsActivePort'),'utf8')).split('\n')[0];break;}catch{await delay(100);}}
 if(!port)throw new Error('Chrome no ha arrancado');
 const targets=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
 ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let sequence=0;const pending=new Map();
 ws.addEventListener('message',({data})=>{const msg=JSON.parse(data);if(msg.id&&pending.has(msg.id)){const{resolve,reject}=pending.get(msg.id);pending.delete(msg.id);msg.error?reject(new Error(msg.error.message)):resolve(msg.result);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 await send('Page.enable');await send('Runtime.enable');
 for(const manual of manuals){
  await send('Page.navigate',{url:pathToFileURL(join(out,'fuentes',manual.id+'.html')).href});
  let loaded=false;
  for(let i=0;i<100;i++){const r=await send('Runtime.evaluate',{expression:`document.readyState==='complete'&&document.querySelectorAll('.chapter').length===${manual.chapters}`,returnByValue:true});if(r.result.value){loaded=true;break;}await delay(100);}
  if(!loaded)throw new Error('Documento no cargado: '+manual.id);
  await send('Runtime.evaluate',{expression:'document.fonts.ready',awaitPromise:true});
  const pdf=await send('Page.printToPDF',{
   printBackground:true,preferCSSPageSize:true,displayHeaderFooter:true,
   headerTemplate:'<div style="width:100%;margin:0 23mm;font:8px Arial;color:#a44200;font-weight:700">ORANGE / PORTAL DE CONOCIMIENTO</div>',
   footerTemplate:`<div style="width:100%;margin:0 23mm;font:8px Arial;color:#555;display:flex;justify-content:space-between"><span>Uso interno · ${manual.title}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`,
   generateDocumentOutline:true,generateTaggedPDF:true
  });
  await writeFile(join(out,manual.id+'.pdf'),Buffer.from(pdf.data,'base64'));
  console.log(manual.id+': PDF generado');
 }
} finally {if(ws)ws.close();chrome.kill('SIGTERM');await delay(300);await rm(profile,{recursive:true,force:true});}
