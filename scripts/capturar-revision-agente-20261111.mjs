// Local browser smoke test using Chrome DevTools; no npm dependencies.
import {spawn, execFileSync} from 'node:child_process';
import {readFile, writeFile, mkdtemp, mkdir, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
const env = Object.fromEntries((await readFile('.env', 'utf8')).split(/\r?\n/).filter(l => l && !l.startsWith('#')).map(l => {
  const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)];
}));
const base = `http://localhost:${env.MOODLE_PORT || 8080}`;
const profile = await mkdtemp(join(tmpdir(), 'orange-chrome-'));
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
   '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--window-size=1440,1000', 'about:blank'], {stdio: 'ignore'});
const delay = ms => new Promise(r => setTimeout(r, ms));
let ws, fixture;
const fixtureCommand=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','/tmp/orange-manual-agente-fixture.php',...args],{encoding:'utf8'});
  const results = [];
  const captures=[];let currentRole='Acceso';
function check(condition, label) { if (!condition) throw new Error(label); results.push(label); console.log('PASS ' + label); }
try {
  let port;
  for (let i = 0; i < 100; i++) { try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; } catch { await delay(100); } }
  if (!port) throw new Error('Chrome no ha arrancado');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, {once: true}));
  let seq = 0; const pending = new Map();
  ws.addEventListener('message', ({data}) => { const msg = JSON.parse(data); if (msg.id && pending.has(msg.id)) {
    const {resolve, reject} = pending.get(msg.id); pending.delete(msg.id);
    msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
  }});
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++seq; pending.set(id, {resolve, reject}); ws.send(JSON.stringify({id, method, params}));
  });
  const evaluate = async expression => {
    const r = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.text + ': ' + (r.result.description || ''));
    return r.result.value;
  };
  const wait = async (expression, attempts = 150) => {
    for (let i = 0; i < attempts; i++) { if (await evaluate(expression)) return; await delay(150); }
    throw new Error('Timeout: ' + expression + ' URL=' + await evaluate('location.href') + ' text=' + (await evaluate('document.body.innerText')).slice(0, 600));
  };
  const navigate = async path => {
    await send('Page.navigate', {url: /^(https?:|file:)/.test(path) ? path : base + path});
    await delay(500); await wait(`document.readyState !== 'loading' ||
      (location.pathname==='/login/index.php' && !!document.querySelector('#loginbtn'))`, path.includes('/login/') ? 800 : 150);
  };
  const screenshot = async name => {
    await delay(500); const r = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    await mkdir('docs/manuales/20261111/imagenes', {recursive: true}); await writeFile(`docs/manuales/20261111/imagenes/${name}.png`, Buffer.from(r.data, 'base64'));
    console.log('CAPTURA '+name);
    captures.push({id:name,perfil:currentRole,url:await evaluate('location.pathname+location.search'),titulo:await evaluate('document.title')});
  };
  const login = async (username, password) => {
    await navigate('/login/index.php'); await wait(`!!document.querySelector('#username')`);
    await evaluate(`document.querySelector('#username').value=${JSON.stringify(username)};
      document.querySelector('#password').value=${JSON.stringify(password)};
      document.querySelector('#loginbtn').click();`);
    await wait(`location.pathname.includes('/local/orangeportal/') && document.readyState!=='loading'`);
    await wait(`!!document.querySelector('.orange-intro')`);
    check(!(await evaluate(`!!document.querySelector('.alert-danger')`)), 'Acceso ' + username);
  };
  const logout = async () => {
    const url = await evaluate(`document.querySelector('a[href*="/login/logout.php"]').href`);
    await navigate(url); await wait(`!!document.querySelector('#username')`);
  };
  const nav = () => evaluate(`Array.from(document.querySelectorAll('.primary-navigation .nav-link')).filter(x=>x.textContent.trim()!=='').map(x=>x.textContent.trim()).filter(x=>!['Más','More'].includes(x))`);
  await send('Page.enable'); await send('Runtime.enable');

  execFileSync('docker',['compose','cp','scripts/manual-agente-fixture.php','web:/tmp/orange-manual-agente-fixture.php'],{stdio:'ignore'});
  fixture=JSON.parse(fixtureCommand());
  currentRole='Agente';await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/index.php?section=incidents');
  const selector=`a.orange-incident-title[href$="view.php?id=${fixture.noticeid}"]`;
  await wait(`!!document.querySelector(${JSON.stringify(selector)})`);
  await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
  await wait(`location.pathname==='/mod/page/view.php' && new URLSearchParams(location.search).get('id')==='${fixture.cmid}' && document.readyState==='complete'`);
  check(await evaluate(`document.body.innerText.includes('Información para la atención al cliente')`), 'El título de la incidencia abre directamente la actividad asociada');
  await screenshot('agente_13_incidencia_actividad');
  const headerIcons=await evaluate(`Array.from(document.querySelectorAll('.orange-personal-updates a svg')).map(x=>x.outerHTML)`);
  check(headerIcons.length===4,'Cuatro iconos originales de avisos superiores');
  const originalIcon=async name=>'data:image/svg+xml;base64,'+Buffer.from(await readFile('moodle/local/orangeportal/pix/'+name+'.svg')).toString('base64');
  const topNames=[['Comunicados','Campana: abre tus comunicados pendientes.'],['Incidencias','Triángulo: abre tus incidencias pendientes.'],['Formaciones','Libro abierto: abre tus formaciones nuevas.'],['Biblioteca','Libros: abre tus documentos nuevos.']];
  const rows=[
    [`<img src="${await originalIcon('envelope-unread')}">`,'Comunicado pendiente','Sobre cerrado: aún no has abierto la versión actual.'],
    [`<img src="${await originalIcon('envelope-read')}">`,'Comunicado leído','Sobre abierto: ya has abierto la versión actual.'],
    [`<img src="${await originalIcon('incident-unread')}">`,'Incidencia pendiente','Exclamación: aún no has abierto la versión actual.'],
    ['<span style="width:40px;height:40px"></span>','Incidencia leída','El título aparece sin exclamación: ya has abierto la versión actual.']
  ];
  const card=(icon,title,description)=>`<div class="card"><div class="icon">${icon}</div><div><strong>${title}</strong><p>${description}</p></div></div>`;
  const legend=`<!doctype html><html lang="es"><meta charset="utf-8"><title>Leyenda de iconos del portal Orange</title><style>
  *{box-sizing:border-box}body{margin:0;padding:30px 36px;width:1160px;background:white;font:18px/1.4 Arial;color:#202020}
  h1{font-size:28px;margin:0 0 18px}h2{font-size:21px;color:#914100;margin:16px 0 10px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 22px}
  .card{display:flex;align-items:center;gap:18px;padding:12px;border:1px solid #dedde0;border-radius:6px;min-height:90px}
  .icon{display:flex;justify-content:center;align-items:center;flex:0 0 46px;color:#914100}.icon svg,.icon img{width:40px;height:40px}strong{font-size:19px}p{font-size:17px;margin:4px 0 0}
  .foot{font-size:16px;color:#555;margin-top:16px}
  </style><h1>Iconos de los mensajes y avisos</h1><h2>Avisos del menú superior</h2><div class="grid">${topNames.map((x,i)=>card(headerIcons[i],...x)).join('')}</div>
  <h2>Lectura en los listados de Comunicados e Incidencias</h2><div class="grid">${rows.map(x=>card(...x)).join('')}</div>
  <p class="foot">La lectura corresponde a la versión actual. Si se publica una nueva versión, el aviso puede volver a quedar pendiente.</p></html>`;
  const legendPath=join(profile,'iconos.html');await writeFile(legendPath,legend);
  await navigate('file://'+legendPath);await wait(`Array.from(document.images).every(i=>i.complete && i.naturalWidth>0)`);
  const height=await evaluate('Math.ceil(document.body.getBoundingClientRect().height)');
  const pic=await send('Page.captureScreenshot',{format:'png',clip:{x:0,y:0,width:1160,height,scale:1}});
  await writeFile('docs/manuales/20261111/imagenes/agente_14_iconos_mensajes.png',Buffer.from(pic.data,'base64'));
  captures.push({id:'agente_14_iconos_mensajes',perfil:'Agente',url:null,titulo:'Leyenda de iconos de mensajes y avisos',nota:'Figura explicativa, no captura de una pantalla: SVG originales del menú superior y de pix/envelope-unread.svg, envelope-read.svg e incident-unread.svg; la incidencia leída no muestra exclamación. No se han modificado los iconos.'});
  const metadataPath='docs/manuales/20261111/fuentes/capturas.json';
  const metadata=JSON.parse(await readFile(metadataPath,'utf8'));
  const ids=new Set(captures.map(x=>x.id));metadata.capturas=metadata.capturas.filter(x=>!ids.has(x.id)).concat(captures);
  metadata.nota='Se entregan las capturas utilizadas y una leyenda explicativa con los iconos originales del portal. El caso de apertura directa utiliza una incidencia ficticia temporal, retirada después de la captura.';
  await writeFile(metadataPath,JSON.stringify(metadata,null,2)+'\n');
  console.log('PASS Captura de apertura directa y leyenda de iconos generadas');
} finally {
  if(ws)ws.close();chrome.kill('SIGTERM');
  if(fixture)console.log(fixtureCommand('cleanup',String(fixture.noticeid)).trim());
  execFileSync('docker',['compose','exec','-T','web','rm','-f','/tmp/orange-manual-agente-fixture.php']);
  await delay(300);await rm(profile,{recursive:true,force:true});
}
