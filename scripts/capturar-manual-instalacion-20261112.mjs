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
    await mkdir('docs/manuales/20261112/instalacion/imagenes', {recursive: true}); await writeFile(`docs/manuales/20261112/instalacion/imagenes/${name}.png`, Buffer.from(r.data, 'base64'));
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


  currentRole='Administrador general Moodle';
  await navigate('/login/index.php');await wait(`!!document.querySelector('#username')`);
  await evaluate(`document.querySelector('#username').value=${JSON.stringify(env.MOODLE_ADMIN_USER||'admin')};document.querySelector('#password').value=${JSON.stringify(env.MOODLE_ADMIN_PASSWORD)};document.querySelector('#loginbtn').click()`);
  await wait(`location.pathname!=='/login/index.php'&&document.readyState==='complete'`);
  const capture=async(name,path,focus)=>{
    await navigate(path);await wait(`document.readyState==='complete'&&!!document.body`);
    if(await evaluate(`document.querySelector('h1')?.textContent==='Not Found'||document.body.innerText.includes('Excepción -')`))throw new Error('Error en '+name);
    if(focus)await evaluate(`document.querySelector(${JSON.stringify(focus)})?.scrollIntoView({block:'center'})`);
    await screenshot(name);
  };
  await capture('instalacion_01_configuracion','/admin/settings.php?section=local_orangeportal');
  await capture('instalacion_02_raices','/admin/settings.php?section=local_orangeportal','[name="s_local_orangeportal_librarycategory"]');
  await capture('instalacion_03_temas','/admin/settings.php?section=themesettings','[name="s__allowcohortthemes"]');
  await capture('instalacion_04_cohorte','/cohort/edit.php?id=2','[name="theme"]');
  await capture('instalacion_05_teams','/admin/settings.php?section=local_orangeportal_teams');
  await logout();
  await writeFile('docs/manuales/20261112/instalacion/fuentes/capturas.json',JSON.stringify({fecha:'2026-11-12',entorno:'Moodle local; no PRE ni producción',version_plugin:'2026111100',capturas:captures,nota:'Configuración consultada sin guardar cambios. URL de Teams oculta por el campo de contraseña nativo. Las capturas reutilizadas del 11 de noviembre se identifican en la fuente del manual.'},null,2));
} finally {if(ws)ws.close();chrome.kill('SIGTERM');}
