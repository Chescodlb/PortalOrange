// Local browser smoke test using Chrome DevTools; no npm dependencies.
import {spawn, execFileSync} from 'node:child_process';
import {readFile, writeFile, mkdtemp, mkdir} from 'node:fs/promises';
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
let ws;
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
    await send('Page.navigate', {url: path.startsWith('http') ? path : base + path});
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

  const capture = async (name,path) => {
    if(path)await navigate(path);
    await wait(`document.readyState==='complete' && !!document.body`);
    const error=await evaluate(`document.body.innerText.includes('Excepción -')||document.body.innerText.includes('Error de codificación')||document.querySelector('h1')?.textContent==='Not Found'`);
    if(error)throw new Error('Error Moodle en '+name);
    if(['usuarios_04_miembros','usuarios_08_coordinacion','usuarios_11_consulta'].includes(name)){
      await evaluate(`for(const input of document.querySelectorAll('input[id$="_searchtext"]')){input.value='Orange';input.dispatchEvent(new KeyboardEvent('keyup',{key:'e',keyCode:69,which:69,bubbles:true}));}`);
      await delay(2000);
    }
    if(name==='usuarios_09_participantes'){
      const result=await send('Page.captureScreenshot',{format:'png',clip:{x:0,y:0,width:1440,height:620,scale:1}});
      await writeFile('docs/manuales/20261111/imagenes/'+name+'.png',Buffer.from(result.data,'base64'));
      captures.push({id:name,perfil:currentRole,url:await evaluate('location.pathname+location.search'),titulo:await evaluate('document.title'),nota:'Captura del encabezado y controles; las filas con datos personales quedan fuera del encuadre.'});
      console.log('CAPTURA '+name);
    }else await screenshot(name);
  };
  await navigate('/login/index.php');
  await capture('00_acceso');
  currentRole='Agente';await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  await capture('agente_01_biblioteca','/local/orangeportal/index.php?section=library');
  await capture('agente_02_busqueda','/local/orangeportal/index.php?section=library&q=alta');
  const doc=await evaluate(`document.querySelector('.orange-library-document')?.href`);
  if(doc)await capture('agente_03_documento',doc);
  await capture('agente_04_comunicados','/local/orangeportal/index.php?section=notifications');
  await capture('agente_05_comunicado','/local/orangeportal/view.php?id=3&detail=1');
  await capture('agente_06_incidencias','/local/orangeportal/index.php?section=incidents');
  await capture('agente_07_incidencia','/local/orangeportal/view.php?id=1&detail=1');
  await capture('agente_08_formaciones','/local/orangeportal/index.php?section=courses');
  await capture('agente_09_curso','/course/view.php?id=4');
  await capture('agente_10_evaluacion','/mod/quiz/view.php?id=7');
  await capture('agente_11_encuesta','/mod/feedback/view.php?id=10');
  await capture('agente_12_notas','/grade/report/user/index.php?id=4');
  await logout();currentRole='Formador';await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
  await capture('formador_01_biblioteca','/local/orangeportal/index.php?section=library');
  const newdoc=await evaluate(`Array.from(document.querySelectorAll('a')).find(a=>a.textContent.trim()==='Nuevo documento')?.href`);
  if(newdoc)await capture('formador_02_documento',newdoc);
  await capture('formador_03_formaciones','/local/orangeportal/index.php?section=courses');
  await capture('formador_04_curso','/course/view.php?id=4');
  await capture('formador_05_colecciones','/local/orangeportal/collections.php?course=4');
  await capture('formador_06_comunicados','/local/orangeportal/manage.php?kind=notifications');
  await capture('formador_07_publicacion','/local/orangeportal/manage.php?kind=notifications&id=3');
  await evaluate(`document.querySelector('#id_published')?.scrollIntoView({block:'center'});`);
  await capture('formador_08_visibilidad');
  await capture('formador_09_incidencias','/local/orangeportal/manage.php?kind=incidents');
  await capture('formador_10_estado','/local/orangeportal/view.php?id=1&detail=1');
  await evaluate(`const d=document.querySelector('.orange-incident-detail-management');if(d)d.open=true;`);
  await capture('formador_11_cierre');
  await capture('formador_12_seguimiento','/local/orangeportal/reports.php?section=courses&period=all');
  await logout();currentRole='Coordinador';await login('coordinador.orange',env.ORANGE_DEMO_PASSWORD);
  await capture('coordinador_01_inicio','/local/orangeportal/index.php?section=library');
  await capture('coordinador_02_resumen','/local/orangeportal/reports.php?period=all');
  await capture('coordinador_03_incidencias','/local/orangeportal/reports.php?section=incidents&period=all');
  await capture('coordinador_04_comunicados','/local/orangeportal/reports.php?section=notifications&period=all');
  await capture('coordinador_05_formacion','/local/orangeportal/reports.php?section=courses&period=all');
  const edition=await evaluate(`document.querySelector('.orange-training-report tbody a')?.href`);
  if(edition)await capture('coordinador_06_edicion',edition);
  await capture('coordinador_07_usuarios','/local/orangeportal/reports.php?section=users&period=all&user_search=Orange');
  await capture('coordinador_08_incidencia','/local/orangeportal/view.php?id=1&detail=1');
  await logout();currentRole='Administrador general Moodle';
  await navigate('/login/index.php');await wait(`!!document.querySelector('#username')`);
  await evaluate(`document.querySelector('#username').value=${JSON.stringify(env.MOODLE_ADMIN_USER||'admin')};document.querySelector('#password').value=${JSON.stringify(env.MOODLE_ADMIN_PASSWORD)};document.querySelector('#loginbtn').click()`);
  await wait(`location.pathname!=='/login/index.php'&&document.readyState==='complete'`);
  await capture('usuarios_01_cuentas','/admin/user.php');
  await capture('usuarios_02_crear','/user/editadvanced.php?id=-1');
  await capture('usuarios_03_cohortes','/cohort/index.php?contextid=1');
  await capture('usuarios_04_miembros','/cohort/assign.php?id=2');
  await capture('usuarios_05_roles','/admin/roles/manage.php');
  await capture('usuarios_06_importar','/admin/roles/define.php?action=add');
  await capture('usuarios_07_sistema','/admin/roles/assign.php?contextid=1');
  const coord=await evaluate(`Array.from(document.querySelectorAll('a[href*="roleid="]')).find(a=>a.textContent.includes('Coordinación Orange'))?.href`);
  if(coord)await capture('usuarios_08_coordinacion',coord);
  await capture('usuarios_09_participantes','/user/index.php?id=4');
  const contextid=execFileSync('docker',['compose','exec','-T','web','php','-r',`define('CLI_SCRIPT',true);require 'config.php';echo context_course::instance(4)->id;`],{encoding:'utf8'}).trim();
  await capture('usuarios_10_roles_curso','/admin/roles/assign.php?contextid='+contextid);
  const consulta=await evaluate(`Array.from(document.querySelectorAll('a[href*="roleid="]')).find(a=>a.textContent.includes('Consulta formativa Orange'))?.href`);
  if(consulta)await capture('usuarios_11_consulta',consulta);
  await capture('usuarios_12_categorias','/course/management.php');
  await logout();
  await writeFile('docs/manuales/20261111/fuentes/capturas.json',JSON.stringify({fecha:'2026-11-11',entorno:'Moodle local de demostración; no son capturas de PRE',version_local:'2026111100',criterio:'Se documentan las rutas de PRE sin el selector Perfil Orange. Navegación real; no se han recreado interfaces.',capturas:captures},null,2));
  console.log('TOTAL '+captures.length+' capturas reales');
} finally {if(ws)ws.close();chrome.kill('SIGTERM');}
