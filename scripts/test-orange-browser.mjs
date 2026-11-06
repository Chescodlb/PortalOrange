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
  const wait = async expression => {
    for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await delay(150); }
    throw new Error('Timeout: ' + expression + ' URL=' + await evaluate('location.href') + ' text=' + (await evaluate('document.body.innerText')).slice(0, 600));
  };
  const navigate = async path => {
    await send('Page.navigate', {url: path.startsWith('http') ? path : base + path});
    await delay(500); await wait(`document.readyState !== 'loading' ||
      (location.pathname==='/login/index.php' && !!document.querySelector('#loginbtn'))`);
  };
  const screenshot = async name => {
    await delay(500); const r = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    await mkdir('backups/previews', {recursive: true}); await writeFile(`backups/previews/${name}.png`, Buffer.from(r.data, 'base64'));
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
  if (process.argv.includes('--live-updates-only')) {
    const token=crypto.randomUUID().replaceAll('-','').slice(0,12);
    const fixtureCommand=(action,key)=>execFileSync('docker',['compose','exec','-T','web','php',
      'local/orangeportal/cli/live_updates_browser_fixture.php',action,token,...(key?[key]:[])],{encoding:'utf8'});
    const state=key=>evaluate(`(()=>{const e=document.querySelector('[data-update="${key}"]');return e?{count:Number(e.dataset.count),total:Number(e.dataset.total),critical:Number(e.dataset.critical),red:e.classList.contains('is-critical'),href:e.href,title:e.title}:null})()`);
    let requests=0;
    await send('Network.enable');
    ws.addEventListener('message',({data})=>{const event=JSON.parse(data);if(event.method==='Network.requestWillBeSent'&&event.params.request.url.includes('/local/orangeportal/updates.php')){requests++;}});
    try {
      await navigate('/login/index.php');
      check(await evaluate(`fetch('/local/orangeportal/updates.php',{method:'POST'}).then(r=>r.status)`)===401,'Endpoint rechaza sesiones anónimas');
      const fixture=JSON.parse(fixtureCommand('create'));
      await login(fixture.username,env.ORANGE_DEMO_PASSWORD);
      await navigate('/local/orangeportal/index.php?section=library');
      await wait(`document.querySelector('.orange-personal-updates')?.dataset.liveUpdates==='1'`);
      const before=await state('incidents');
      check(before.total===0&&!before.red,'Borradores y publicaciones ajenas no entran en el contador');
      check(await evaluate(`fetch('/local/orangeportal/updates.php',{method:'POST',body:new URLSearchParams({sesskey:'invalid'})}).then(r=>r.status)`)===403,'Endpoint comprueba el token de sesión');
      await evaluate(`document.querySelector('#orange-library-search').value='Texto que debe conservarse';document.querySelector('#orange-library-search').focus();window.liveOriginalSearch=document.querySelector('#orange-library-search');`);
      const originalUrl=await evaluate('location.href');
      fixtureCommand('publish','normal');
      console.log('Esperando el intervalo real de 30 segundos...');
      await delay(31000);
      await wait(`document.querySelector('[data-update="incidents"]').dataset.total==='1'`);
      check((await state('incidents')).count===1&&!(await state('incidents')).red,'Nueva incidencia aparece sin recargar tras el intervalo real');
      check(await evaluate(`location.href===${JSON.stringify(originalUrl)}&&window.liveOriginalSearch===document.querySelector('#orange-library-search')&&document.activeElement===window.liveOriginalSearch&&window.liveOriginalSearch.value==='Texto que debe conservarse'`),'Actualización conserva página, formulario y foco');
      await navigate('/user/preferences.php');
      await wait(`document.querySelector('.orange-personal-updates')?.dataset.liveUpdates==='1'`);
      fixtureCommand('publish','communication');
      const priorRequests=requests;
      await evaluate(`window.dispatchEvent(new Event('focus'));window.dispatchEvent(new Event('focus'));window.dispatchEvent(new Event('focus'));`);
      await wait(`document.querySelector('[data-update="notifications"]').dataset.critical==='1'`);
      const notification=await state('notifications');
      check(notification.red&&notification.count===1&&notification.href.includes('impact=critical'),'Comunicado crítico actualiza color, cantidad y filtro en una página nativa');
      check(requests===priorRequests+1,'Eventos simultáneos comparten una única consulta');
      const hiddenRequests=requests;
      await evaluate(`Object.defineProperty(document,'hidden',{value:true,configurable:true});document.dispatchEvent(new Event('visibilitychange'));window.dispatchEvent(new Event('focus'));`);
      fixtureCommand('publish','critical');
      await delay(1000);
      check(requests===hiddenRequests&&(await state('incidents')).critical===0,'Pestaña oculta pausa las consultas');
      await evaluate(`delete document.hidden;document.dispatchEvent(new Event('visibilitychange'));`);
      await wait(`document.querySelector('[data-update="incidents"]').dataset.critical==='1'`);
      const critical=await state('incidents');
      check(critical.red&&critical.count===1&&critical.total===2&&critical.href.includes('priority=critical')&&critical.title.includes('2'),'Volver a la pestaña muestra críticas sin leer y mantiene el total en la ayuda');
      await screenshot('indicadores-actualizacion-automatica');
      await evaluate(`fetch('/local/orangeportal/view.php?id=${fixture.notices.critical}&detail=1').then(r=>r.text())`);
      await evaluate(`window.dispatchEvent(new Event('focus'))`);
      await wait(`document.querySelector('[data-update="incidents"]').dataset.critical==='0'`);
      const read=await state('incidents');
      check(!read.red&&read.total===1&&read.count===1&&!read.href.includes('priority=critical'),'Lectura en otra pestaña devuelve el icono a naranja y resta solo la leída');
      check(await evaluate(`fetch('/local/orangeportal/updates.php',{method:'POST',body:new URLSearchParams({sesskey:M.cfg.sesskey})}).then(async r=>({cache:r.headers.get('cache-control'),data:await r.json()})).then(x=>x.cache.includes('no-store')&&x.data.indicators.length===4&&x.data.indicators.every(i=>!('userid' in i)&&!('title' in i)))`),'Respuesta privada sin caché contiene solo los cuatro indicadores');
      await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
      await evaluate(`window.dispatchEvent(new Event('focus'))`);await delay(1000);
      check((await state('incidents')).total===1,'Sin conexión se conservan los indicadores y la página');
      await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
      const exitUrl=await evaluate(`document.querySelector('a[href*="/login/logout.php"]').href`);
      await evaluate(`fetch(${JSON.stringify(exitUrl)}).then(r=>r.text())`);
      check(await evaluate(`fetch('/local/orangeportal/updates.php',{method:'POST'}).then(r=>r.status)`)===401,'Sesión cerrada deja de devolver contadores');
      await evaluate(`window.dispatchEvent(new Event('focus'))`);await delay(1000);
      const stoppedRequests=requests;
      await evaluate(`window.dispatchEvent(new Event('focus'))`);await delay(500);
      check(requests===stoppedRequests,'El refresco se detiene al expirar la sesión');
      await navigate('/login/index.php');
      await evaluate(`document.querySelector('#username').value='usuario.externo';document.querySelector('#password').value=${JSON.stringify(env.ORANGE_DEMO_PASSWORD)};document.querySelector('#loginbtn').click()`);
      await wait(`location.pathname!=='/login/index.php'&&document.readyState!=='loading'`);
      check(await evaluate(`!document.querySelector('.orange-personal-updates')&&fetch('/local/orangeportal/updates.php',{method:'POST',body:new URLSearchParams({sesskey:M.cfg.sesskey})}).then(r=>r.status===403)`),'Usuario externo conserva su portal y no accede a los contadores Orange');
      console.log('Verificaciones de actualización automática completas: '+results.length);
    } finally { console.log(fixtureCommand('cleanup').trim()); }
  } else if (process.argv.includes('--notice-opening-only')) {
    const fixture=JSON.parse(await readFile('backups/previews/native-content-fixture.json','utf8'));
    const moduleid=fixture.modules[fixture.course];
    const edit='/local/orangeportal/manage.php?kind=notifications&id='+fixture.notice;
    const resources='/local/orangeportal/resources.php?notice='+fixture.notice;
    const view='/local/orangeportal/view.php?id='+fixture.notice;
    const configure=async id=>{
      await navigate(resources+'&lang=es');
      await wait(`!!document.querySelector('select[name="openactivity"]')`);
      await evaluate(`document.querySelector('select[name="openactivity"]').value=${JSON.stringify(String(id))};document.querySelector('[name="submitbutton"]').click()`);
      await wait(`!!document.querySelector('.alert-success')&&document.readyState!=='loading'`);
    };
    await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    for(const lang of ['es','en']) {
      await navigate(resources+'&lang='+lang);
      check(await evaluate(`!!document.querySelector('select[name="openactivity"] option[value="${moduleid}"]')&&!document.body.textContent.includes('[[')`),lang+': apertura por publicación traducida con sus recursos');
    }
    await navigate(edit);
    check(await evaluate(`!document.querySelector('select[name="openactivity"]')`),'El formulario inicial no pide recursos todavía inexistentes');
    await configure(moduleid);
    await navigate(view);
    check(await evaluate(`location.pathname==='/local/orangeportal/view.php'&&!!document.querySelector('.orange-notice-resources')`),'Formador siempre abre la ficha aunque el agente abra un recurso');
    await navigate(resources);
    await evaluate(`document.querySelector('button.section-modchooser-link').click()`);
    await wait(`!!document.querySelector('.modal.show .modchooser [data-internal="url"] a[data-action="add-chooser-option"]')`);
    await evaluate(`Array.from(document.querySelectorAll('.modal.show .modchooser [data-internal="url"] a[data-action="add-chooser-option"]')).find(x=>x.getClientRects().length).click()`);
    await wait(`location.pathname==='/course/modedit.php'&&!!document.querySelector('input[name="externalurl"]')`);
    check(await evaluate(`!!document.querySelector('[name="submitbutton2"]')&&!document.querySelector('[name="submitbutton"]')`),'Formulario nativo ofrece guardar y volver a los recursos');
    await evaluate(`document.querySelector('[name="name"]').value='TESTWEB URL añadida';document.querySelector('[name="externalurl"]').value='https://example.org/';document.querySelector('[name="submitbutton2"]').click()`);
    await wait(`location.pathname==='/local/orangeportal/resources.php'&&document.body.textContent.includes('TESTWEB URL añadida')`);
    check(await evaluate(`document.querySelectorAll('.orange-native-activity').length>=3`),'Guardar vuelve a recursos y permite añadir múltiples actividades');
    const update=await evaluate(`Array.from(document.querySelectorAll('.orange-native-activity')).find(x=>x.textContent.includes('TESTWEB URL añadida')).querySelector('a[href*="modedit.php"]').href`);
    await navigate(update);
    await evaluate(`document.querySelector('[name="name"]').value='TESTWEB URL editada';document.querySelector('[name="submitbutton2"]').click()`);
    await wait(`location.pathname==='/local/orangeportal/resources.php'&&document.body.textContent.includes('TESTWEB URL editada')`);
    check(true,'Editar un recurso también vuelve a la misma gestión');
    await navigate(update);
    await evaluate(`document.querySelector('[name="cancel"]').click()`);
    await wait(`location.pathname==='/local/orangeportal/resources.php'&&!!document.querySelector('select[name="openactivity"]')`);
    check(true,'Cancelar vuelve a recursos sin perder la publicación');
    await navigate(resources);
    check(await evaluate(`document.querySelector('select[name="openactivity"]').value==='${moduleid}'`),'Se conserva la apertura al añadir, editar y cancelar recursos');
    await screenshot('publicacion-configurar-apertura');
    await logout();await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate(view);
    await wait(`location.pathname==='/mod/page/view.php'&&document.body.textContent.includes('ORANGE-NATIVE-')`);
    check(await evaluate(`new URL(location.href).searchParams.get('id')==='${moduleid}'`),'Comunicado abre directamente su Página sin pantalla intermedia');
    check(await evaluate(`Array.from(document.querySelectorAll('a')).some(a=>a.href.includes('view.php?id=${fixture.notice}')&&a.href.includes('detail=1'))`),'La actividad conserva un acceso a la ficha');
    await navigate(view+'&detail=1');
    check(await evaluate(`!!document.querySelector('.orange-notice-resources')`),'La ficha sigue disponible aunque haya apertura directa');
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await configure(fixture.label);
    await logout();await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate(view);
    await wait(`location.pathname==='/local/orangeportal/activity.php'&&document.body.textContent.includes('ORANGE-INLINE-')`);
    check(await evaluate(`!document.querySelector('form button[type="submit"]')&&document.querySelector('#region-main').textContent.includes('ORANGE-INLINE-')`),'Área de texto y medios abre directamente sin pedir otro clic');
    await screenshot('publicacion-apertura-directa-area-texto');
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await configure(0);
    await logout();await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate(view);
    check(await evaluate(`location.pathname==='/local/orangeportal/view.php'&&!!document.querySelector('.orange-notice-resources')`),'Puede restaurarse la apertura de ficha');
    console.log('Verificaciones de apertura completas: '+results.length);
  } else if (process.argv.includes('--native-chooser-only')) {
    const fixture=JSON.parse(await readFile('backups/previews/native-content-fixture.json','utf8'));
    const modal='document.querySelector(\'.modal.show .modchooser\')';
    const waitChooser=async()=>{
      await wait(`!!${modal}?.querySelector('[data-region="chooser-option-container"]')`);
      check(await evaluate(`!!${modal}.querySelector('[data-region="chooser-option-container"] .optionicon img')&&!!${modal}.querySelector('[data-region="chooser-option-actions-container"]')`),'Selector nativo con iconos, favoritos y ayuda');
    };
    const selectModule=async(name)=>{
      await evaluate(`Array.from(${modal}.querySelectorAll('[data-internal="${name}"] a[data-action="add-chooser-option"]')).find(x=>x.getClientRects().length)?.click()`);
      await wait(`location.pathname==='/course/modedit.php'&&!!document.querySelector('form.mform')`);
    };
    await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    for(const lang of ['es','en']) {
      await navigate('/local/orangeportal/add_document.php?category='+fixture.category+'&lang='+lang);
      check(await evaluate(`!!document.querySelector('select[name="categoryid"]')&&!document.querySelector('[name="documenttype"]')`),lang+': Biblioteca solo pide la categoría');
      await evaluate(`document.querySelector('input[name="submitbutton"]').click()`);
      await waitChooser();
      check(await evaluate(`${modal}.textContent.includes(${JSON.stringify('Archivo')})||${modal}.textContent.includes('File')`),lang+': modal en el idioma de Moodle');
      check(await evaluate(`${modal}.querySelectorAll('[role="tab"]').length>=3`),lang+': pestañas nativas Todos, Actividades y Recursos');
      if(lang==='es') {
        await screenshot('selector-nativo-biblioteca');
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await screenshot('selector-nativo-movil');
        check(await evaluate(`document.documentElement.scrollWidth<=innerWidth+2`),'Modal nativo adaptable a móvil');
        await send('Emulation.clearDeviceMetricsOverride');
      }
      await selectModule('url');
      check(await evaluate(`new URL(location.href).searchParams.get('course')===${JSON.stringify(String(fixture.library))}&&!!document.querySelector('input[name="externalurl"]')`),lang+': recurso elegido abre el formulario nativo en su categoría');
    }
    await navigate('/local/orangeportal/resources.php?notice='+fixture.notice+'&lang=es');
    check(await evaluate(`!!document.querySelector('button.section-modchooser-link')&&!document.querySelector('a[href*="add_activity.php"]')`),'Publicaciones abren el modal sin página intermedia');
    await evaluate(`document.querySelector('button.section-modchooser-link').click()`);
    await waitChooser();
    await selectModule('quiz');
    check(await evaluate(`new URL(location.href).searchParams.get('course')===${JSON.stringify(String(fixture.course))}&&!!document.querySelector('#id_attempts')`),'Cuestionario usa el contenedor de la publicación');
    await navigate('/local/orangeportal/collections.php?course='+fixture.training+'&lang=en');
    const target=await evaluate(`Array.from(document.querySelectorAll('button.section-modchooser-link')).at(-1)?.dataset.sectionnum`);
    check(target!==undefined,'Colecciones tienen botón visual nativo por sección');
    await evaluate(`Array.from(document.querySelectorAll('button.section-modchooser-link')).at(-1).click()`);
    await waitChooser();
    await selectModule('page');
    check(await evaluate(`new URL(location.href).searchParams.get('course')===${JSON.stringify(String(fixture.training))}&&new URL(location.href).searchParams.get('section')===${JSON.stringify(target)}`),'La selección conserva la formación y colección de destino');
    await logout();await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/collections.php?course=4');
    check(await evaluate(`!document.querySelector('button.section-modchooser-link')`),'Agente no recibe el selector de edición');
    await navigate('/local/orangeportal/add_activity.php?course='+fixture.course);
    check(await evaluate(`!document.querySelector('button.section-modchooser-link')`),'Acceso directo del agente también denegado');
    console.log('Verificaciones de selector completas: '+results.length);
  } else if (process.argv.includes('--native-content-only') || process.argv.includes('--native-content-withdrawn')) {
    const fixture=JSON.parse(await readFile('backups/previews/native-content-fixture.json','utf8'));
    const withdrawn=process.argv.includes('--native-content-withdrawn');
    if(!withdrawn) {
      await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
      for(const lang of ['es','en']) {
        await navigate('/local/orangeportal/add_document.php?category='+fixture.category+'&lang='+lang);
        check(await evaluate(`!!document.querySelector('select[name="categoryid"]')&&!document.querySelector('[name="collection"]')&&!document.querySelector('[name="documenttype"]')&&!document.body.textContent.includes('[[')`),lang+': Biblioteca pide categoría y actividad, sin colección');
        await navigate('/local/orangeportal/resources.php?notice='+fixture.notice+'&lang='+lang);
        check(await evaluate(`document.body.textContent.includes('TESTWEB Página')&&!!document.querySelector('button.section-modchooser-link')&&!document.body.textContent.includes('[[')`),lang+': gestor de recursos de publicación traducido');
      }
      await navigate('/local/orangeportal/add_document.php?category='+fixture.category+'&lang=es');
      await evaluate(`document.querySelector('input[name="submitbutton"]').click()`);
      await wait(`!!document.querySelector('.modal.show .modchooser [data-internal="url"] a[data-action="add-chooser-option"]')`);
      await evaluate(`Array.from(document.querySelectorAll('.modal.show .modchooser [data-internal="url"] a[data-action="add-chooser-option"]')).find(x=>x.getClientRects().length).click()`);
      await wait(`location.pathname==='/course/modedit.php'&&!!document.querySelector('input[name="externalurl"]')`);
      check(await evaluate(`!!document.querySelector('input[name="name"]')`),'Nuevo documento abre el formulario nativo completo de URL');
      await evaluate(`document.querySelector('input[name="cancel"]').click()`);
      await wait(`location.pathname==='/local/orangeportal/index.php'&&document.readyState!=='loading'`);
      check(await evaluate(`new URL(location.href).searchParams.get('section')==='library'`),'Cancelar formulario nativo vuelve a Biblioteca');
      await navigate('/local/orangeportal/manage.php?kind=notifications&id='+fixture.notice);
      await evaluate(`document.querySelector('[name="saveandresources"]').click()`);
      await wait(`location.pathname==='/local/orangeportal/resources.php'&&document.readyState!=='loading'`);
      check(await evaluate(`document.body.textContent.includes('TESTWEB Página')`),'Guardar publicación sin descripción abre sus recursos');
      await navigate('/local/orangeportal/collections.php?course='+fixture.training+'&lang=es');
      check(await evaluate(`!!document.querySelector('.orange-training-collection')&&!document.body.textContent.includes('[[')`),'Formación lista las colecciones existentes');
      await evaluate(`document.querySelector('input[name="name"]').value='TESTWEB Colección añadida';document.querySelector('input[name="submitbutton"]').click()`);
      await wait(`!!Array.from(document.querySelectorAll('.orange-training-collection')).find(x=>x.textContent.includes('TESTWEB Colección añadida'))`);
      check(true,'Crear colección añade una sección nativa');
      await evaluate(`Array.from(document.querySelectorAll('form')).find(f=>f.action.includes('/course/view.php')).querySelector('button').click()`);
      await wait(`location.pathname==='/course/view.php'&&document.readyState!=='loading'`);
      check(await evaluate(`!!document.querySelector('[data-action="addblock"]')||document.body.textContent.includes('Agregar un bloque')||!!document.querySelector('a[href*="bui_addblock"]')`),'Edición nativa permite agregar bloques laterales');
      await logout();
    }
    await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    if(!withdrawn) {
      await navigate('/local/orangeportal/view.php?id='+fixture.notice);
      check(await evaluate(`document.body.textContent.includes('TESTWEB Página')&&!document.querySelector('a[href*="resources.php"]')`),'Agente ve recursos y carece de herramientas de gestión');
      const downloaded=await evaluate(`fetch(${JSON.stringify(fixture.file)}).then(r=>r.text())`);
      check(downloaded==='ORANGE-FILE-'+fixture.token,'Descarga nativa pluginfile autorizada para el destinatario');
      await navigate('/mod/page/view.php?id='+fixture.modules[fixture.course]);
      check(await evaluate(`document.body.textContent.includes(${JSON.stringify('ORANGE-NATIVE-'+fixture.token)})`),'Recurso nativo muestra su contenido dentro de Orange');
      await screenshot('recursos-publicacion-agente');
      await navigate('/local/orangeportal/index.php?section=library&category='+fixture.category);
      check(await evaluate(`document.querySelector('.orange-library-documents').textContent.includes('TESTWEB Página')`),'Documento nuevo aparece directamente bajo su categoría');
      await navigate('/local/orangeportal/add_document.php');
      check(await evaluate(`!document.querySelector('select[name="documenttype"]')`),'Agente no carga documentos');
    } else {
      const downloaded=await evaluate(`fetch(${JSON.stringify(fixture.file)}).then(r=>r.text())`);
      check(!downloaded.includes('ORANGE-FILE-'+fixture.token),'Retirar publicación bloquea descarga aunque conserve una matrícula anterior');
      await navigate('/mod/page/view.php?id='+fixture.modules[fixture.course]);
      check(await evaluate(`!document.body.textContent.includes(${JSON.stringify('ORANGE-NATIVE-'+fixture.token)})`),'Retirar publicación bloquea la URL nativa de la actividad');
    }
    console.log('Verificaciones de recursos completas: '+results.length);
  } else if (process.argv.includes('--agent-import-only')) {
    await login('admin.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/users.php');
    check(await evaluate(`!!document.querySelector('a[href*="import_users.php"]')`),'Administración Orange ofrece importación CSV');
    for(const lang of ['en','es']) {
      await navigate('/local/orangeportal/import_users.php?lang='+lang);
      check(await evaluate(`!!document.querySelector('#id_csvfile')&&!document.body.textContent.includes('[[')`),lang+': formulario traducido');
      check(await evaluate(`!!document.querySelector('a[href*="template=1"]')&&!!document.querySelector('a[data-content*="username"] .fa-circle-question')`),lang+': plantilla e icono circular de ayuda');
      await evaluate(`document.querySelector('a[data-content*="username"]').click()`);
      await wait(`!!document.querySelector('.popover-body')`);
      check(await evaluate(`document.querySelector('.popover-body').textContent.includes('cohorts')&&document.querySelector('.popover-body').textContent.includes('courses')`),lang+': ayuda explica formato, colectivos y formaciones');
    }
    await navigate('/local/orangeportal/import_users.php?lang=es');
    const shortname=await evaluate(`document.querySelector('.orange-import-courses tbody tr td')?.textContent`);
    check(!!shortname,'Catálogo muestra nombres cortos reales de formaciones Orange');
    check(await evaluate(`fetch(document.querySelector('a[href*="template=1"]').href).then(r=>r.text()).then(csv=>csv.split('\\n')[0].includes(';courses'))`),'Plantilla descargable incluye la columna courses');
    const fixture=join(profile,'agentes.csv');
    const user='csvbrowser_'+Date.now();
    const password='Csv!9aB_'+Date.now();
    await writeFile(fixture,'username;firstname;lastname;email;password;idnumber;cohorts;courses\n'+user+';Prueba;CSV;'+user+'@example.invalid;'+password+';CE-CSV;;'+shortname+'\n');
    await evaluate(`document.querySelector('.fp-btn-choose').click()`);
    await wait(`!!document.querySelector('.fp-repo-area')`);
    await evaluate(`Array.from(document.querySelectorAll('.fp-repo-area a')).find(x=>x.textContent.includes('Subir')).click()`);
    await wait(`!!document.querySelector('input[name="repo_upload_file"]')`);
    const doc=await send('DOM.getDocument');
    const file=await send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'input[name="repo_upload_file"]'});
    await send('DOM.setFileInputFiles',{nodeId:file.nodeId,files:[fixture]});
    await evaluate(`document.querySelector('.fp-upload-btn').click()`);
    await wait(`document.querySelector('.filepicker-filename')?.textContent.includes('agentes.csv')`);
    await evaluate(`document.querySelector('#id_submitbutton').click()`);
    await wait(`!!document.querySelector('.orange-agent-import-preview')`);
    check(await evaluate(`document.querySelector('.orange-agent-import-preview').textContent.includes(${JSON.stringify(user)})&&!document.documentElement.outerHTML.includes(${JSON.stringify(password)})&&!document.body.textContent.includes('[[')`),'Archivo subido: vista previa sin contraseña');
    check(await evaluate(`document.querySelector('.orange-agent-import-preview').textContent.includes(${JSON.stringify(shortname)})`),'Vista previa identifica la formación a matricular');
    check(await evaluate(`!!document.querySelector('#orange-import-progress')&&document.querySelector('#orange-import-runner').parentElement!==document.querySelector('#orange-import-progress')`),'Progreso y botones correctamente renderizados');
    await screenshot('agentes-csv-vista-previa');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await screenshot('agentes-csv-movil');
    check(await evaluate(`document.documentElement.scrollWidth<=innerWidth+2`),'CSV usable en móvil sin desbordamiento global');
    await send('Emulation.clearDeviceMetricsOverride');
    await evaluate(`Array.from(document.querySelectorAll('#orange-import-runner form')).find(f=>f.querySelector('input[name="action"]').value==='cancel').querySelector('button').click()`);
    await wait(`location.pathname.endsWith('/users.php')&&document.readyState!=='loading'`);
    await navigate('/local/orangeportal/import_users.php');
    check(await evaluate(`!!document.querySelector('#id_csvfile')&&!document.querySelector('#orange-import-runner')`),'Cancelar elimina la vista previa y permite otra carga');
    await logout();
    await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/import_users.php');
    check(await evaluate(`!document.querySelector('#id_csvfile')&&!document.querySelector('#orange-import-runner')`),'Formador no accede al importador');
    console.log('Verificaciones CSV completas: '+results.length);
  } else if (process.argv.includes('--user-reports-only')) {
    for(const username of ['formador.orange','coordinador.orange','calidad.orange','admin.orange']) {
      await login(username,env.ORANGE_DEMO_PASSWORD);
      for(const language of ['es','en']) {
        await navigate('/local/orangeportal/reports.php?section=users&lang='+language);
        check(await evaluate(`!!document.querySelector('.orange-user-summary')&&!document.body.textContent.includes('[[')&&!document.querySelector('.alert-danger')`),username+' / '+language+': directorio individual traducido');
        check(await evaluate(`document.querySelector('#report-period').value==='month'&&!!document.querySelector('#user_search')&&!!document.querySelector('#user_cohort')&&!document.querySelector('#user_centre')`),username+' / '+language+': periodo y filtros de nombre/CE, colectivo/centro');
        const profile=await evaluate(`Array.from(document.querySelectorAll('.orange-user-summary tbody tr')).find(r=>r.cells[0].textContent.includes('Agente Orange'))?.querySelector('a')?.href`);
        check(!!profile,username+' / '+language+': ficha del agente accesible');
        await navigate(profile);
        check(await evaluate(`document.querySelectorAll('.orange-user-tabs a').length===5&&!document.body.textContent.includes('[[')`),username+' / '+language+': resumen individual con cinco secciones');
        const training=await evaluate(`Array.from(document.querySelectorAll('.orange-user-tabs a')).find(a=>a.href.includes('user_tab=training')).href`);
        await navigate(training);
        check(await evaluate(`!document.body.textContent.includes('[[')&&!document.querySelector('.alert-danger')`),username+' / '+language+': formación sin errores ni cadenas pendientes');
        for(const tab of ['incidents','notifications','surveys']) {
          const href=await evaluate(`Array.from(document.querySelectorAll('.orange-user-tabs a')).find(a=>a.href.includes('user_tab=${tab}')).href`);
          await navigate(href);
          check(await evaluate(`!!document.querySelector('.orange-user-tabs [aria-current="page"]')&&!document.body.textContent.includes('[[')&&!document.querySelector('.alert-danger')`),username+' / '+language+': desglose '+tab+' sin errores');
        }
        await navigate(training);
        if(username==='calidad.orange') {
          check(await evaluate(`!document.querySelector('a[href*="/mod/quiz/report.php"]')&&!document.querySelector('.orange-training-report')`),'Calidad sin resultados ni intentos de formación');
        }
        if(username==='formador.orange'&&language==='es') {await screenshot('seguimiento-usuario-formacion-es');}
      }
      if(username==='admin.orange') {
        await navigate('/local/orangeportal/reports.php?section=users&period=all&lang=es');
        await screenshot('seguimiento-usuarios-es');
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Directorio de seguimiento sin desbordamiento móvil');
        await send('Emulation.clearDeviceMetricsOverride');
      }
      await logout();
    }
    await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/reports.php?section=users');
    check(await evaluate(`!document.querySelector('.orange-user-summary')&&!document.querySelector('.orange-user-tabs')`),'Agente no accede al seguimiento de otras personas');
    await writeFile('backups/previews/user-report-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de seguimiento individual: '+results.length);
  } else if (process.argv.includes('--reading-reports-only')) {
    await login('admin.orange',env.ORANGE_DEMO_PASSWORD);
    for(const language of ['es','en']) {
      for(const section of ['incidents','notifications']) {
        await navigate('/local/orangeportal/reports.php?section='+section+'&lang='+language);
        check(await evaluate(`document.querySelectorAll('.orange-category-tabs a').length===4&&document.querySelector('.orange-category-tabs [aria-current="page"]').href.includes('section=${section}')`),language+' / '+section+': selector de Seguimiento activo');
        check(await evaluate(`!!document.querySelector('.orange-reading-summary')&&!document.querySelector('.orange-reading-detail')&&!document.body.textContent.includes('[[')`),language+' / '+section+': resumen por publicación separado del detalle');
        check(await evaluate(`Array.from(document.querySelectorAll('.orange-reading-summary tbody tr')).every(r=>Number(r.cells[2].textContent)===Number(r.cells[3].textContent.match(/— (\\d+)\\//)?.[1]||0)+Number(r.cells[4].textContent.match(/— (\\d+)\\//)?.[1]||0))`),language+' / '+section+': total igual a leídos más sin leer');
        check(await evaluate(`document.querySelectorAll('.orange-reading-summary tbody tr').length<=25`),language+' / '+section+': resumen paginado');
        const detail=await evaluate(`document.querySelector('.orange-reading-summary tbody a')?.href`);
        check(!!detail,language+' / '+section+': publicación disponible para abrir detalle');
        await navigate(detail);
        check(await evaluate(`!!document.querySelector('.orange-reading-detail')&&!document.querySelector('.orange-reading-summary')&&!!document.querySelector('#report-history-${section}')`),language+' / '+section+': detalle por usuarios e histórico');
        check(await evaluate(`!Array.from(document.querySelectorAll('.orange-reading-detail th')).some(e=>/confirm/i.test(e.textContent))&&!!document.querySelector('[name="${section}_user"]')&&!!document.querySelector('[name="${section}_reading"]')`),language+' / '+section+': filtros individuales sin Confirmación');
        const csv=await evaluate(`fetch(document.querySelector('.orange-reading-detail a[href*="export.php"]').href).then(async r=>({type:r.headers.get('content-type'),body:await r.text()}))`);
        check(csv.type?.includes('csv')&&!/confirm/i.test(csv.body.split(/\r?\n/)[0]),language+' / '+section+': CSV por publicación sin Confirmación');
        await screenshot('seguimiento-'+section+'-'+language);
      }
      await navigate('/local/orangeportal/reports.php?section=courses&lang='+language);
      check(await evaluate(`!!document.querySelector('.orange-training-report')&&!!document.querySelector('a[href*="/grade/report/grader/"]')&&!document.querySelector('.orange-reading-summary')`),language+': Formación conserva resultados y enlaces');
    }
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/local/orangeportal/reports.php?section=notifications');
    check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Seguimiento sin desbordamiento en móvil');
    await send('Emulation.clearDeviceMetricsOverride');
    await logout();await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/index.php?section=notifications');
    check(await evaluate(`!document.querySelector('[name="pending"]')`),'Comunicados sin filtro de confirmación');
    const detail=await evaluate(`document.querySelector('.orange-notification-title')?.href`);
    check(!!detail,'Comunicado asignado disponible para abrir');
    await navigate(detail);
    check(await evaluate(`!!document.querySelector('.orange-notice-content')&&!document.querySelector('input[value="confirm"]')`),'Detalle registra lectura sin botón de confirmar');
    const blocked=await evaluate(`fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'confirm',sesskey:M.cfg.sesskey,revision:'1'})}).then(r=>r.text()).then(html=>!new DOMParser().parseFromString(html,'text/html').querySelector('.orange-notice-content'))`);
    check(blocked,'POST de confirmación antiguo rechazado');
    await writeFile('backups/previews/reading-report-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de seguimiento: '+results.length);
  } else if (process.argv.includes('--consultation-roles-only')) {
    for(const username of ['coordinador.orange','calidad.orange']) {
      await login(username,env.ORANGE_DEMO_PASSWORD);
      for(const language of ['es','en']) {
        await navigate('/local/orangeportal/reports.php?section=courses&lang='+language);
        check(await evaluate(`!!document.querySelector('.orange-intro')&&!document.querySelector('.alert-danger')`),username+' / '+language+': consulta de informes');
        check(await evaluate(`!Array.from(document.querySelectorAll('.orange-admin-links a')).some(e=>/manage.php|options.php|categories.php|users.php|cohorts.php/.test(e.href))&&!document.querySelector('a[href*="export.php"]')`),username+' / '+language+': sin gestión ni exportación');
        const grades=await evaluate(`document.querySelectorAll('a[href*="/grade/report/grader/"]').length`);
        check((grades>0)===(username==='coordinador.orange'),username+' / '+language+': notas según perfil');
      }
      await screenshot('consulta-'+username.split('.')[0]);
      const denied=await evaluate(`Promise.all(['options.php','manage.php?kind=notifications&new=1','users.php','categories.php?kind=incidents','export.php?type=readings'].map(async path=>{
        const r=await fetch('/local/orangeportal/'+path),html=await r.text();
        const d=new DOMParser().parseFromString(html,'text/html');
        return {path,denied:!d.querySelector('.orange-intro')&&/permission|permiso|acceso|capabilit/i.test(d.body.textContent)};
      }))`);
      for(const result of denied)check(result.denied,username+': acceso directo denegado '+result.path);
      await navigate('/local/orangeportal/incident_tracking.php');
      check(await evaluate(`!!document.querySelector('.orange-intro')&&!document.querySelector('.orange-admin-links a[href*="manage.php"]')`),username+': histórico de incidencias sin edición');
      if(username==='coordinador.orange') {
        await navigate('/grade/report/grader/index.php?id=4&edit=1');
        check(await evaluate(`!!document.querySelector('#user-grades')&&!document.querySelector('#user-grades input')`),'Coordinación consulta calificaciones sin campos editables');
      } else {
        await navigate('/grade/report/grader/index.php?id=4');
        check(await evaluate(`!document.querySelector('#user-grades')&&/permission|permiso|acceso|capabilit/i.test(document.body.innerText)`),'Calidad no accede al libro de notas de otros');
      }
      await logout();
    }
    await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/manage.php?kind=incidents&new=1');
    check(await evaluate(`!!document.querySelector('[name="title"]')&&!!document.querySelector('[name="state"]')`),'Formación conserva publicación y estados');
    await writeFile('backups/previews/consultation-role-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de consulta: '+results.length);
  } else if (process.argv.includes('--training-columns-only')) {
    await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/index.php?section=courses&lang=es');
    check(await evaluate(`!!document.querySelector('.orange-training-browser')&&!document.querySelector('.orange-card')&&!document.querySelector('.orange-training-open')`),'Formaciones usa columnas sin tarjetas ni selección ficticia');
    check(await evaluate(`['Formación inicial','Formación continua'].every(t=>Array.from(document.querySelectorAll('.orange-library-category')).some(a=>a.textContent.includes(t)))`),'Dos ramas principales disponibles');
    const categoryLink=async text=>evaluate(`Array.from(document.querySelectorAll('.orange-library-category')).find(a=>a.querySelector('span').textContent===${JSON.stringify(text)}).href`);
    const initial=await categoryLink('Formación inicial');const continuous=await categoryLink('Formación continua');
    await navigate(initial);
    check(await evaluate(`!!document.querySelector('.orange-library-category.is-selected')&&document.querySelectorAll('.orange-training-course').length>0`),'Rama inicial resaltada y cursos accesibles');
    const course=await evaluate(`document.querySelector('.orange-training-course').href`);
    const title=await evaluate(`document.querySelector('.orange-training-course strong').textContent`);
    await navigate(course);
    check(await evaluate(`document.querySelector('.orange-library-detail h4').textContent===${JSON.stringify(title)}&&document.querySelector('.orange-training-open').href.includes('/course/view.php')&&!document.querySelector('.orange-create-quiz')`),'Ficha y apertura nativa sin herramientas de edición para agente');
    await screenshot('formaciones-columnas-inicial');
    await navigate(continuous);
    check(await evaluate(`!document.querySelector('.orange-training-open')&&!!document.querySelector('.orange-library-empty')`),'Rama continua preparada sin cursos ficticios');
    await navigate(continuous+'&q='+encodeURIComponent(title));
    check(await evaluate(`document.querySelectorAll('.orange-training-course').length>0&&document.querySelector('.orange-library-result-count').textContent.includes('todas las formaciones')`),'Búsqueda global desde otra rama');
    await evaluate(`document.querySelector('#orange-library-order').value='name';document.querySelector('#orange-library-order').dispatchEvent(new Event('change',{bubbles:true}))`);
    await wait(`new URL(location.href).searchParams.get('order')==='name'&&document.readyState==='complete'`);
    check(await evaluate(`document.querySelector('#orange-library-order').value==='name'`),'Orden automático sin botón Aplicar');
    await navigate('/local/orangeportal/index.php?section=courses&q=ZZZ-SIN-FORMACION');
    check(await evaluate(`!document.querySelector('.orange-training-course')&&!!document.querySelector('.orange-library-empty')`),'Búsqueda vacía sin acceso ficticio');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate(course);
    check(await evaluate(`innerWidth<=390&&document.documentElement.scrollWidth<=390&&document.querySelector('.orange-training-browser').scrollWidth>document.querySelector('.orange-training-browser').clientWidth`),'Móvil desplaza columnas dentro de Formaciones');
    await screenshot('formaciones-columnas-movil');await send('Emulation.clearDeviceMetricsOverride');
    await navigate('/local/orangeportal/index.php?section=courses&lang=en');
    check(await evaluate(`document.querySelector('.orange-library-results h3').textContent==='Courses'&&document.querySelector('label[for="orange-training-search"]').textContent==='Search all training'&&!document.body.innerText.includes('[[training')`),'Explorador traducido al inglés');
    await navigate('/local/orangeportal/index.php?section=library&lang=es');
    check(await evaluate(`!!document.querySelector('.orange-library-browser')&&!document.querySelector('.orange-training-browser')&&document.querySelector('.orange-library-results h3').textContent==='Documentos'`),'Biblioteca conserva su explorador documental');
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate(course);
    check(await evaluate(`!!document.querySelector('.orange-create-quiz')&&!!document.querySelector('.orange-library-detail input[name="edit"]')&&!!document.querySelector('.orange-create-course')`),'Formador conserva creación, edición y evaluaciones');
    const creation=await evaluate(`document.querySelector('.orange-create-course').href`);
    check(new URL(creation).searchParams.get('category')===new URL(course).searchParams.get('category'),'Nuevo curso propone la categoría seleccionada');
    await writeFile('backups/previews/training-columns-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones Formaciones: '+results.length);
  } else if (process.argv.includes('--orange-admin-only')) {
    await login('admin.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/users.php?lang=es');
    check(await evaluate(`!!document.querySelector('.orange-admin-links a.active[href*="users.php"]')&&!!document.querySelector('.primary-navigation a[href$="users.php"]')`),'Administración Orange abre Usuarios y marca la pestaña activa');
    check(await evaluate(`!document.body.innerText.includes('usuario.externo')&&document.body.innerText.includes('Cuenta protegida')`),'Lista limitada a Orange y administradores protegidos');
    await screenshot('administrador-orange-usuarios');
    await navigate('/local/orangeportal/users.php?action=new');
    check(await evaluate(`['username','password','firstname','lastname','email'].every(n=>document.querySelector('[name="'+n+'"]'))`),'Formulario de alta nativa disponible');
    await navigate('/local/orangeportal/cohorts.php');
    check(await evaluate(`!!document.querySelector('.orange-admin-links a.active[href*="cohorts.php"]')&&!document.body.innerText.includes('[[')`),'Colectivos con traducciones y pestaña activa');
    await navigate('/local/orangeportal/cohorts.php?action=new');
    check(await evaluate(`!!document.querySelector('[name="name"]')&&!!document.querySelector('[name="courses[]"]')`),'Crear colectivo y elegir matrículas Orange');
    await navigate('/local/orangeportal/manage.php?kind=incidents&new=1');
    check(await evaluate(`!!document.querySelector('[name="incidentclient"]')||!!document.querySelector('[name="client"]')`),'Administrador puede gestionar incidencias');
    check(await evaluate(`!Array.from(document.querySelectorAll('[name="users[]"] option')).some(o=>o.textContent.includes('usuario.externo'))`),'Destinatarios no ofrecen usuarios externos');
    await navigate('/local/orangeportal/reports.php');
    check(await evaluate(`document.querySelectorAll('a[href*="export.php"]').length===0`),'Resumen sin exportación de destinatarios hasta elegir una publicación');
    const csv=await evaluate(`fetch('/local/orangeportal/export.php?type=users').then(async r=>({ok:r.ok,type:r.headers.get('content-type'),text:await r.text()}))`);
    check(csv.ok&&csv.type.includes('csv')&&!csv.text.includes('usuario.externo')&&csv.text.includes('admin.orange'),'CSV de usuarios limitado a Orange');
    await navigate('/admin/search.php');
    check(await evaluate(`!document.querySelector('#adminsearchquery')&&!document.querySelector('input[name="query"]')`),'Sin buscador de administración general');
    await navigate('/user/editadvanced.php?id=-1');
    check(await evaluate(`!document.querySelector('[name="firstname"]')`),'Sin creación global de cuentas Moodle');
    await navigate('/local/orangeportal/users.php?lang=en');
    check(await evaluate(`document.querySelector('.orange-intro h2').textContent==='Orange users'&&!document.body.innerText.includes('[[')`),'Administración delegada traducida al inglés');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/local/orangeportal/users.php?lang=es');
    check(await evaluate(`innerWidth<=390&&document.documentElement.scrollWidth<=390`),'Administración de usuarios sin desbordamiento móvil');
    await send('Emulation.clearDeviceMetricsOverride');
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    check(await evaluate(`!document.querySelector('.orange-admin-links a[href*="users.php"]')`),'Formador no recibe gestión de usuarios');
    await navigate('/local/orangeportal/users.php');
    check(await evaluate(`!document.querySelector('a[href*="action=new"]')`),'Formador rechazado también por acceso directo');
    await writeFile('backups/previews/orange-admin-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de administración Orange: '+results.length);
  } else if (process.argv.includes('--personal-updates-only')) {
    await login('agente.orange', env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/index.php?section=notifications&lang=es');
    await wait(`!!document.querySelector('.orange-communications-layout')`);
    check(await evaluate(`document.querySelector('.orange-communications-filters').getBoundingClientRect().right<document.querySelector('.orange-communications-layout .orange-incidents-results').getBoundingClientRect().left`), 'Comunicados con filtros a la izquierda');
    check(await evaluate(`['q','category','impact','datefrom','dateto','unread'].every(n=>document.querySelector('.orange-communications-filters [name="'+n+'"]'))`), 'Búsqueda, categoría, impacto, fechas y lectura en lateral');
    check(await evaluate(`document.querySelectorAll('.orange-personal-updates .orange-update').length===4&&!document.querySelector('#nav-notification-popover-container')&&!document.querySelector('[data-region="popover-region-messages"]')`), 'Cuatro indicadores Orange sin iconos nativos de avisos y mensajes');
    check(await evaluate(`(async()=>{
      const icons=['notifications','incidents'].map(key=>document.querySelector('[data-update="'+key+'"]'));
      const previous=icons.map(e=>e.classList.contains('is-critical'));
      icons.forEach(e=>e.classList.add('is-critical'));
      // The theme animates link colors; inspect the settled CSS color.
      await new Promise(resolve=>setTimeout(resolve,700));
      const red=icons.every(e=>getComputedStyle(e).color==='rgb(230, 0, 0)'&&getComputedStyle(e.querySelector('.orange-update-count')).backgroundColor==='rgb(230, 0, 0)');
      icons.forEach((e,i)=>{if(!previous[i])e.classList.remove('is-critical')});
      return red;
    })()`),'CSS rojo para icono y contador crítico de ambas secciones');
    const keys=['notifications','incidents','courses','library'];
    for (const key of keys) {
      const link=await evaluate(`document.querySelector('[data-update="${key}"]').href`);
      const count=await evaluate(`Number(document.querySelector('[data-update="${key}"]').dataset.count)`);
      await navigate(link);
      check(await evaluate(`!document.querySelector('.alert-danger')&&!document.body.innerText.includes('[[updates_')&&!!document.querySelector('[data-update="${key}"] svg')`),'Indicador accesible y traducido: '+key);
      const rows=await evaluate(key==='library'?`document.querySelectorAll('.orange-library-document').length`:key==='courses'?`document.querySelectorAll('.orange-training-course').length`:key==='notifications'?`document.querySelectorAll('.orange-notifications-table tbody tr').length`:`document.querySelectorAll('.orange-incidents-table tbody tr').length`);
      check(rows===count,'Contador coincide con su listado: '+key);
    }
    await navigate('/local/orangeportal/index.php?section=notifications&lang=en');
    check(await evaluate(`document.querySelector('.orange-communications-filters h3').textContent==='Filter announcements'&&document.querySelector('[data-update="courses"]').title.includes('New unopened training')`), 'Filtros e indicadores en inglés');
    await navigate('/local/orangeportal/index.php?section=notifications&lang=es');
    await screenshot('comunicados-filtros-e-indicadores');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/local/orangeportal/index.php?section=notifications');
    check(await evaluate(`document.documentElement.scrollWidth<=innerWidth&&Array.from(document.querySelectorAll('.orange-update')).every(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth})`),'Indicadores y Comunicados sin desbordamiento en móvil');
    await screenshot('comunicados-indicadores-movil');
    await send('Emulation.clearDeviceMetricsOverride');
    await logout();
    await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    check(await evaluate(`document.querySelectorAll('.orange-update').length===4&&!!document.querySelector('.primary-navigation a[href*="manage.php"]')`),'Formador conserva indicadores y acceso de gestión');
    await navigate('/local/orangeportal/index.php?section=notifications');
    check(await evaluate(`!document.querySelector('[name="pending"]')&&!!document.querySelector('.orange-communications-filters')`),'Formador conserva filtros laterales sin confirmación');
    for (const language of ['es','en']) {
      await navigate('/local/orangeportal/manage.php?kind=notifications&new=1&lang='+language);
      check(await evaluate(`!!document.querySelector('select[name="impact"]:not([disabled]) option[value="critical"]')`),language+': Impacto editable en nuevos Comunicados');
    }
    console.log('Verificaciones personales completas: '+results.length);
  } else if (process.argv.includes('--incidents-workflow-only')) {
  await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/index.php?section=incidents');
  await wait(`!!document.querySelector('.orange-incidents-layout')`);
  check(await evaluate(`document.querySelector('.orange-incidents-filters').getBoundingClientRect().right<document.querySelector('.orange-incidents-results').getBoundingClientRect().left`),'Filtros a la izquierda de la tabla');
  check(await evaluate(`!document.querySelector('[name="pending"]')&&!document.querySelector('.orange-incident-tracking-link')&&!Array.from(document.querySelectorAll('.orange-incidents-table th:not(:first-child)')).some(h=>h.textContent.trim()==='Lectura')`),'Agente sin confirmaciones, columna Lectura ni seguimiento administrativo');
  check(await evaluate(`!!document.querySelector('[name="incidentclient"]')&&!!document.querySelector('[name="incidentproduct"]')&&!document.querySelector('option[value="closedonly"]')`),'Filtros cliente/producto y solo estados abiertos para el agente');
  const target=await evaluate(`document.querySelector('.orange-incident-title')?.href`);
  check(!!target,'Incidencia abierta accesible');
  await screenshot('incidencias-agente-filtros-laterales');
  await navigate(target);
  check(await evaluate(`!document.querySelector('input[name="action"][value="confirm"]')&&!document.querySelector('input[name="action"][value="state"]')`),'Detalle del agente sin confirmar ni cambiar estado');
  await navigate('/local/orangeportal/index.php?section=incidents');
  const id=new URL(target).searchParams.get('id');
  check(await evaluate(`!document.querySelector('tr[data-incident-id="${id}"] [role="img"]')`),'La exclamación desaparece tras abrir la incidencia');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Incidencias sin desbordamiento de página en móvil');
  await send('Emulation.clearDeviceMetricsOverride');await logout();
  await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/index.php?section=incidents');
  check(await evaluate(`!!document.querySelector('.orange-incident-tracking-link')&&!!document.querySelector('option[value="closedonly"]')`),'Formador con seguimiento y filtro de cerradas');
  await navigate('/local/orangeportal/manage.php?kind=incidents&new=1');
  check(await evaluate(`!!document.querySelector('[name="client"]')&&!!document.querySelector('[name="product"]')&&!!document.querySelector('[name="state"]')`),'Formador puede crear incidencias con cliente y producto');
  await screenshot('incidencias-formador-crear');
  await navigate(target);
  check(await evaluate(`!!document.querySelector('input[name="action"][value="state"]')&&!document.querySelector('input[name="action"][value="confirm"]')`),'Solo formador puede actualizar estado; no hay confirmación');
  await navigate('/local/orangeportal/incident_tracking.php');
  check(await evaluate(`document.body.innerText.includes('Destinatarios actuales')&&document.body.innerText.includes('Destinatarios que la abrieron')`),'Seguimiento con destinatarios y aperturas');
  await screenshot('incidencias-seguimiento-formador');await logout();
  await login('coordinador.orange',env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/index.php?section=incidents');
  check(await evaluate(`!!document.querySelector('.orange-incident-tracking-link')&&!!document.querySelector('option[value="closedonly"]')`),'Coordinador accede a todas y al seguimiento');
  await navigate(target);
  check(await evaluate(`!document.querySelector('input[name="action"][value="state"]')`),'Coordinador sin control para cambiar estado');
  await navigate('/local/orangeportal/incident_tracking.php?id='+id);
  check(await evaluate(`document.body.innerText.includes('Histórico de estados')&&document.body.innerText.includes('Histórico de aperturas por versión')`),'Seguimiento individual con historial y lecturas');
  await navigate('/local/orangeportal/index.php?section=incidents&lang=en');
  check(await evaluate(`document.querySelector('.orange-incidents-filters h3').textContent==='Filter incidents'&&document.querySelector('.orange-incident-tracking-link').textContent==='Incident tracking'&&!document.body.innerText.includes('[[')`),'Nueva interfaz traducida al inglés');
  await writeFile('backups/previews/incident-workflow-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
  console.log('Verificaciones: '+results.length);
  } else if (process.argv.includes('--library-reading-only')) {
  await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
  const states=()=>evaluate(`Object.fromEntries(Array.from(document.querySelectorAll('.orange-library-document')).map(a=>[new URL(a.href).searchParams.get('document'),a.classList.contains('is-read')]))`);
  const otherstates=await states();
  check(Object.keys(otherstates).length>0,'Segundo usuario con documentos accesibles');
  await logout();
  await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  check(await evaluate(`document.querySelector('#orange-library-order').value==='newest'`),'Más recientes es el orden predeterminado');
  check(await evaluate(`Array.from(document.querySelectorAll('.orange-library-document.is-unread')).every(e=>e.querySelector('.orange-library-read-icon').getAttribute('aria-label')==='Sin leer')`),'Documentos sin leer identificados con distintivo');
  await screenshot('biblioteca-documentos-sin-leer');
  check(await evaluate(`(()=>{const s=document.querySelector('#orange-library-order'),h=document.querySelector('.orange-library-results-heading h3'),r=s.getBoundingClientRect(),t=h.getBoundingClientRect();return r.height<=30&&Math.abs((r.top+r.height/2)-(t.top+t.height/2))<2&&r.left>t.right&&!s.form.querySelector('button');})()`),'Selector compacto a la derecha del título, sin botón');
  await evaluate(`const s=document.querySelector('#orange-library-order');s.value='name';s.dispatchEvent(new Event('change',{bubbles:true}))`);
  await wait(`new URL(location.href).searchParams.get('order')==='name'&&document.readyState!=='loading'`);
  check(await evaluate(`(()=>{const names=Array.from(document.querySelectorAll('.orange-library-document strong')).map(e=>e.textContent);return names.join('|')===[...names].sort((a,b)=>a.localeCompare(b,'es')).join('|');})()`),'Orden alfabético aplicable desde el formulario');
  const unread=await evaluate(`(()=>{const a=document.querySelector('.orange-library-document.is-unread')||document.querySelector('.orange-library-document');return a?{href:a.href,id:new URL(a.href).searchParams.get('document'),wasread:a.classList.contains('is-read')}:null;})()`);
  check(!!unread,'Documento accesible para comprobar apertura');
  await navigate(unread.href);
  check(await evaluate(`document.querySelector('.orange-library-document.is-selected').classList.contains('is-read')===${JSON.stringify(unread.wasread)}`),'Seleccionar la ficha no marca el documento como leído');
  const nativeurl=await evaluate(`document.querySelector('.orange-library-open').href`);
  await navigate(nativeurl);await wait(`!!document.querySelector('#region-main')`);
  await navigate('/local/orangeportal/index.php?section=library&order=newest');
  check(await evaluate(`Array.from(document.querySelectorAll('.orange-library-document')).find(a=>new URL(a.href).searchParams.get('document')===${JSON.stringify(unread.id)}).classList.contains('is-read')`),'Abrir el recurso nativo registra lectura individual');
  await screenshot('biblioteca-documentos-leidos');
  await screenshot('biblioteca-orden-compacto');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Orden y distintivos sin desbordamiento móvil');
  await send('Emulation.clearDeviceMetricsOverride');
  await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
  check(JSON.stringify(await states())===JSON.stringify(otherstates),'La apertura de un agente no cambia la lectura del formador');
  await navigate('/local/orangeportal/index.php?section=library&lang=en');
  check(await evaluate(`document.querySelector('label[for="orange-library-order"]').textContent==='Sort documents'&&document.querySelector('#orange-library-order option[value="newest"]').textContent==='Most recent'`),'Orden y lectura traducidos al inglés');
  await writeFile('backups/previews/library-reading-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
  } else if (process.argv.includes('--library-columns-only')) {
  await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  check(await evaluate(`!!document.querySelector('.orange-library-browser')&&!document.querySelector('#orange-menu-library')&&!document.querySelector('.primary-navigation a[href*="section=library"]').hasAttribute('aria-expanded')`),'Biblioteca abre página sin desplegable');
  check(await evaluate(`document.querySelector('.orange-library-browser').innerText.includes('RPV')&&document.querySelector('.orange-library-browser').innerText.includes('CENTREX')&&!document.querySelector('.orange-library-open')`),'Ámbitos separados y botón oculto antes de seleccionar documento');
  const categorylink=async text=>{
    const href=await evaluate(`Array.from(document.querySelectorAll('.orange-library-category')).find(a=>a.querySelector('span').textContent===${JSON.stringify(text)})?.href`);
    check(!!href,'Categoría disponible: '+text);await navigate(href);await wait(`!!document.querySelector('.orange-library-browser')`);
  };
  await categorylink('RPV');await categorylink('ALTA');await categorylink('Alta con terminal');await categorylink('SIM Física');
  check(await evaluate(`Array.from(document.querySelectorAll('.orange-library-column h3')).map(e=>e.textContent).join('|')==='Biblioteca|RPV|ALTA|Alta con terminal|Documentos'`),'Columnas con nombres de categorías y panel Documentos');
  check(await evaluate(`!!document.querySelector('.orange-library-document')&&!document.querySelector('.orange-library-open')`),'Documentos nativos en lugar de tarjetas de colección');
  const docurl=await evaluate(`document.querySelector('.orange-library-document').href`);
  const docname=await evaluate(`document.querySelector('.orange-library-document strong').textContent`);
  await navigate(docurl);await wait(`!!document.querySelector('.orange-library-open')`);
  check(await evaluate(`document.querySelector('.orange-library-open').href.includes('/mod/')&&document.querySelector('.orange-library-detail h4').textContent===${JSON.stringify(docname)}`),'Selección muestra ficha y apertura del recurso nativo');
  await screenshot('biblioteca-columnas-sim-fisica');
  const nativeurl=await evaluate(`document.querySelector('.orange-library-open').href`);
  await navigate(nativeurl);check(await evaluate(`!!document.querySelector('#region-main')&&!document.querySelector('.alert-danger')`),'Abrir documento conserva control y vista nativa de Moodle');
  await navigate('/local/orangeportal/index.php?section=library&q='+encodeURIComponent(docname));
  check(await evaluate(`document.querySelector('.orange-library-results').innerText.includes(${JSON.stringify(docname)})&&document.querySelector('.orange-library-results').innerText.includes('toda la biblioteca')`),'Búsqueda global por nombre');
  await screenshot('biblioteca-columnas-busqueda');
  await navigate('/local/orangeportal/index.php?section=library&q=Contenido%20demostrativo');
  check(await evaluate(`document.querySelectorAll('.orange-library-document').length>0`),'Búsqueda por descripción');
  await navigate('/local/orangeportal/index.php?section=library&q=ZZZSINRESULTADOSZZZ');
  check(await evaluate(`!document.querySelector('.orange-library-document')&&document.querySelector('.orange-library-empty').textContent.includes('coincidan')`),'Búsqueda sin resultados clara');
  await navigate(docurl);
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate(`innerWidth<=390&&document.documentElement.scrollWidth<=390&&document.querySelector('.orange-library-browser').scrollWidth>document.querySelector('.orange-library-browser').clientWidth`),'Móvil: desplazamiento contenido dentro de la biblioteca');
  await screenshot('biblioteca-columnas-movil');
  await send('Emulation.clearDeviceMetricsOverride');
  await navigate('/local/orangeportal/index.php?section=library&lang=en');
  check(await evaluate(`document.querySelector('.orange-library-results h3').textContent==='Documents'&&document.querySelector('label[for="orange-library-search"]').textContent==='Search the entire library'`),'Biblioteca traducida al inglés');
  await navigate('/local/orangeportal/index.php?section=courses&lang=es');
  check(await evaluate(`!document.querySelector('#orange-menu-courses')&&!!document.querySelector('.orange-training-browser')`),'Formaciones usa columnas sin desplegable');
  await writeFile('backups/previews/library-columns-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
  console.log('Verificaciones: '+results.length);
  } else if (process.argv.includes('--isolation-only')) {
  await send('Emulation.setFocusEmulationEnabled',{enabled:true});
  const genericlogin=async(username,password)=>{
    await navigate('/login/index.php');await wait(`!!document.querySelector('#username')`);
    await evaluate(`document.querySelector('#username').value=${JSON.stringify(username)};document.querySelector('#password').value=${JSON.stringify(password)};document.querySelector('#loginbtn').click()`);
    await wait(`!location.pathname.includes('/login/') && document.readyState!=='loading'`);
  };
  const themename=()=>evaluate(`document.querySelector('link[href*="/theme/styles.php/"]')?.href||Array.from(document.styleSheets).map(x=>x.href).filter(Boolean).join(' ')`);
  const nonorange=async()=>{
    check(!(await nav()).some(x=>['Biblioteca','Incidencias','Notificaciones'].includes(x)), 'Usuario externo conserva menú nativo');
    check(!(await themename()).includes('/orange/'), 'Usuario externo no usa el tema Orange');
    check(await evaluate(`!document.querySelector('.orange-nav-panel')&&!document.querySelector('script[src*="orangeportal/navigation"]')`),'Usuario externo sin desplegables Orange');
  };
  await navigate('/login/index.php');
  check(!(await themename()).includes('/orange/'),'Acceso común con tema del sitio');
  await screenshot('aislamiento-login-comun');
  await login('agente.orange',env.ORANGE_DEMO_PASSWORD);
  check((await themename()).includes('/orange/'),'Miembro Orange usa el tema hijo');
  check((await nav()).join('|')==='Biblioteca|Comunicados|Incidencias|Formaciones','Menú Orange conservado');
  check(await evaluate(`getComputedStyle(document.querySelector('.navbar')).backgroundColor==='rgb(0, 0, 0)'`),'Marca Orange conservada');
  check(await evaluate(`document.querySelector('.navbar-brand img')?.src.includes('theme_orange')||document.querySelector('.navbar-brand img')?.src.includes('/orange/')`),'Logo independiente de Moove');
  await screenshot('aislamiento-orange-biblioteca');
  for(const section of ['courses','incidents','notifications']){
    await navigate('/local/orangeportal/index.php?section='+section);
    check(await evaluate(`!!document.querySelector('.orange-intro')&&!document.querySelector('.alert-danger')`),'Orange: '+section+' disponible');
  }
  await navigate('/user/preferences.php');
  check((await themename()).includes('/orange/')&&await evaluate(`document.body.innerText.includes('Mi cuenta')`),'Orange conserva apariencia y breadcrumb personal');
  await logout();
  await genericlogin('usuario.externo',env.ORANGE_DEMO_PASSWORD);
  check(!await evaluate(`location.pathname.includes('/local/orangeportal/')`),'Usuario externo no redirigido al portal');
  await nonorange();await screenshot('aislamiento-usuario-externo');
  for(const section of ['library','courses','incidents','notifications']){
    await navigate('/local/orangeportal/index.php?section='+section);
    check(await evaluate(`document.body.innerText.includes('no pertenece a la cohorte')&&!document.querySelector('.orange-intro')`),'Acceso directo ajeno bloqueado: '+section);
  }
  await navigate('/user/preferences.php');await nonorange();
  check(!await evaluate(`document.body.innerText.includes('Mi cuenta')`),'Breadcrumb personal externo conserva Moodle');
  await navigate('/my/');await logout();
  await genericlogin('editor.orange',env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/manage.php?kind=notifications');
  check((await themename()).includes('/orange/')&&await evaluate(`!!document.querySelector('a[href*="new=1"]')`),'Editor Orange conserva gestión y marca');
  await screenshot('aislamiento-editor-orange');await logout();
  await genericlogin(env.MOODLE_ADMIN_USER||'admin',env.MOODLE_ADMIN_PASSWORD);
  await navigate('/admin/settings.php?section=local_orangeportal');
  check(await evaluate(`!!document.querySelector('select[name="s_local_orangeportal_audiencecohort"]')`),'Administrador general puede elegir cohorte desde Moodle');
  check(!(await themename()).includes('/orange/'),'Administrador general fuera de cohorte conserva tema del sitio');
  await navigate('/admin/settings.php?section=themesettingorange');
  check(await evaluate(`document.body.innerText.includes('SCSS')&&!document.body.innerText.includes('Excepción')`),'Logo y SCSS Orange gestionables desde ajustes propios');
  await writeFile('backups/previews/isolation-checks.json',JSON.stringify({date:new Date().toISOString(),results},null,2));
  console.log('Verificaciones: '+results.length);
  } else if (process.argv.includes('--admin-languages-only')) {
    await login('editor.orange', env.ORANGE_DEMO_PASSWORD);
    for (const language of ['es', 'en']) {
      const en = language === 'en';
      for (const kind of ['incidents', 'notifications']) {
        await navigate('/local/orangeportal/manage.php?kind=' + kind + '&lang=' + language);
        check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en ? 'Orange publications' : 'Publicaciones Orange')} && document.querySelector('.orange-admin-links .active').textContent===${JSON.stringify(en ? (kind==='incidents'?'Incidents':'Announcements') : (kind==='incidents'?'Incidencias':'Comunicados'))}`), language+' / '+kind+': cabecera y pestaña traducidas');
        check(await evaluate(`Array.from(document.querySelectorAll('table th')).map(n=>n.textContent).join('|')===${JSON.stringify(en?'Title|Category|Publication|Status|Actions':'Título|Categoría|Publicación|Estado|Acciones')}`),language+' / '+kind+': tabla traducida');
        check(await evaluate(`document.querySelector('h1').textContent.trim()===${JSON.stringify(en?'Publish and manage notices':'Publicar y gestionar avisos')} && document.title.includes(${JSON.stringify(en?'Publish and manage notices':'Publicar y gestionar avisos')})`),language+' / '+kind+': título y encabezado de página traducidos');
        const editurl=await evaluate(`document.querySelector('table a[href*="manage.php"][href*="id="]')?.href`);
        if(editurl){await navigate(editurl);check(await evaluate(`document.querySelector('input[name="title"]').value!=='' && document.querySelector('label[for="id_title"]').textContent.includes(${JSON.stringify(en?'Title':'Título')})`),language+' / '+kind+': edición conserva el contenido y traduce etiquetas');}
        await navigate('/local/orangeportal/manage.php?kind='+kind+'&new=1&lang='+language);
        check(await evaluate(`document.querySelector('label[for="id_title"]').textContent.includes(${JSON.stringify(en?'Title':'Título')}) && document.querySelector('#id_submitbutton').value===${JSON.stringify(en?'Save publication':'Guardar publicación')} && document.body.innerText.includes(${JSON.stringify(en?'Individual users':'Usuarios individuales')}) && document.body.innerText.includes(${JSON.stringify(en?'Changing the title or content':'Si cambias el título o contenido')})`),language+' / '+kind+': formulario, destinatarios y ayuda traducidos');
        if(en && kind==='incidents')await screenshot('administracion-formulario-ingles');
      }
      for(const kind of ['library','courses','incidents','notifications']){
        await navigate('/local/orangeportal/categories.php?kind='+kind+'&lang='+language);
        const label=en?({library:'Library',courses:'Training',incidents:'Incidents',notifications:'Announcements'})[kind]:({library:'Biblioteca',courses:'Formaciones',incidents:'Incidencias',notifications:'Comunicados'})[kind];
        check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en?'Categories: '+label:'Categorías de '+label)} && document.querySelector('.orange-category-tabs .active').textContent===${JSON.stringify(label)}`),language+' / '+kind+': cabecera y sección de categorías traducidas');
        check(await evaluate(`document.querySelector('#id_submitbutton').value===${JSON.stringify(en?'Save category':'Guardar categoría')} && document.querySelector('label[for="id_parentid"]').textContent.includes(${JSON.stringify(en?'Parent category':'Categoría superior')})`),language+' / '+kind+': formulario de categorías traducido');
        if(en && kind==='library')await screenshot('administracion-categorias-ingles');
      }
      for(const type of ['states','impacts']){
        await navigate('/local/orangeportal/options.php?type='+type+'&lang='+language);
        check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en?'Statuses and impacts':'Estados e impactos')}`),language+' / '+type+': catálogo traducido');
      }
    }
    await logout();await login('coordinador.orange',env.ORANGE_DEMO_PASSWORD);
    for(const language of ['es','en']){
      const en=language==='en';
      await navigate('/local/orangeportal/reports.php?section=courses&lang='+language);
      check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en?'Tracking':'Seguimiento')} && document.querySelector('.orange-admin-links .active').textContent===${JSON.stringify(en?'Tracking':'Seguimiento')}`),language+': resultados y navegación traducidos');
      await evaluate(`document.querySelectorAll('details.orange-report-panel').forEach(e=>e.open=true)`);
      check(await evaluate(`document.body.innerText.includes(${JSON.stringify(en?'Active participants':'Participantes activos')}) && !!document.querySelector('.orange-training-report')`),language+': tablas e instrucciones de informes traducidas');
      check(await evaluate(`document.querySelector('h1').textContent.trim()===${JSON.stringify(en?'Tracking':'Seguimiento')} && document.title.includes(${JSON.stringify(en?'Tracking':'Seguimiento')})`),language+': título y encabezado de informes traducidos');
      await navigate('/local/orangeportal/reports.php?section=incidents&lang='+language);
      const noticeurl=await evaluate(`document.querySelector('.orange-reading-summary tbody a')?.href`);
      check(!!noticeurl,language+': aviso disponible para comprobar histórico');
      await navigate(noticeurl);
      check(await evaluate(`document.body.innerText.includes(${JSON.stringify(en?'Reading history by version':'Histórico de lecturas por versión')})`),language+': histórico de confirmaciones traducido');
      if(en)await screenshot('administracion-resultados-ingles');
    }
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/add_document.php?lang=en');
    check(await evaluate(`document.querySelector('.orange-intro h2').textContent==='New document' && document.querySelector('#id_submitbutton').value==='Add an activity or resource'`),'Formador: nuevo documento en inglés');
    await logout();await login(env.MOODLE_ADMIN_USER||'admin',env.MOODLE_ADMIN_PASSWORD);
    await navigate('/admin/settings.php?section=local_orangeportal&lang=en');
    check(await evaluate(`document.body.innerText.includes('Orange settings') && document.body.innerText.includes('Automatic training alerts') && document.body.innerText.includes('Library root category')`),'Administrador general: ajustes existentes del plugin en inglés');
    await navigate('/local/orangeportal/manage.php?kind=incidents&lang=en');
    const viewurl=await evaluate(`document.querySelector('table a[href*="view.php"]')?.href`);
    await navigate(viewurl);
    check(await evaluate(`document.body.innerText.includes('Management preview:') && document.body.innerText.includes('Edit publication') && document.body.innerText.includes('Back to list')`),'Detalle de publicación y vista de gestión traducidos');
    check(await evaluate(`!document.body.innerText.includes('[[') && !document.querySelector('.debuggingmessage')`),'Sin cadenas de idioma faltantes');
    console.log('Verificaciones de administración bilingüe: '+results.length);
  } else if (process.argv.includes('--breadcrumb-user-only')) {
    for (const username of ['agente.orange', 'coordinador.orange']) {
      await login(username, env.ORANGE_DEMO_PASSWORD);
      for (const language of ['es', 'en']) {
        const pages = [
          ['/user/preferences.php', language === 'es' ? 'Preferencias' : 'Preferences'],
          ['/grade/report/overview/index.php', language === 'es' ? 'Calificaciones' : 'Grades'],
          ['/calendar/view.php', language === 'es' ? 'Calendario' : 'Calendar'],
          ['/user/files.php', language === 'es' ? 'Archivos privados' : 'Private files']
        ];
        for (const [path, label] of pages) {
          await navigate(path + '?lang=' + language);
          const items = await evaluate(`Array.from(document.querySelectorAll('.breadcrumb li')).map(li=>({text:li.textContent.trim(),href:li.querySelector('a')?.href || ''}))`);
          const root = language === 'es' ? 'Mi cuenta' : 'My account';
          check(items.length >= 2 && items[0].text === root && items.some(i=>i.text === label), username + ' / ' + language + ': Mi cuenta y sección en ' + path);
          check(items[0].href.includes('/user/profile.php?id=') && !items.some(i=>['Página Principal','Página principal','Home'].includes(i.text) || (i.href && new URL(i.href).pathname === '/')),
            username + ' / ' + language + ': sin inicio repetido ni enlaces a Biblioteca en ' + path);
          if (language === 'es' && username === 'agente.orange') {
            await screenshot('breadcrumb-cuenta-' + path.split('/').filter(Boolean).join('-'));
            await navigate(items[0].href);
            check(await evaluate(`location.pathname==='/user/profile.php' && !document.querySelector('.alert-danger')`), 'Mi cuenta abre el perfil desde ' + path);
          }
        }
      }
      await logout();
    }
    console.log('Verificaciones de breadcrumbs personales: ' + results.length);
  } else if (process.argv.includes('--breadcrumb-only') || process.argv.includes('--breadcrumb-management-only')) {
    if (!process.argv.includes('--breadcrumb-management-only')) {
    await login('formador.orange', env.ORANGE_DEMO_PASSWORD);
    for (const language of ['es', 'en']) {
      for (const section of ['library', 'courses']) {
        await navigate('/local/orangeportal/index.php?section=' + section + '&lang=' + language);
        const courseurl = await evaluate(`document.querySelector('.orange-card-link[href*="/course/view.php"]').href`);
        const courseid = new URL(courseurl).searchParams.get('id');
        await navigate(courseurl + '&lang=' + language);
        const activityurl = await evaluate(`Array.from(document.querySelectorAll('a[href*="/mod/"]')).find(a=>a.href.includes(${JSON.stringify(section === 'courses' ? '/mod/quiz/view.php' : '/mod/page/view.php')}))?.href`);
        check(!!activityurl, section + ': actividad nativa disponible para prueba');
        const paths = [courseurl, '/course/edit.php?id=' + courseid, '/user/index.php?id=' + courseid, activityurl];
        for (const path of paths) {
          await navigate(path + '&lang=' + language);
          const items = await evaluate(`Array.from(document.querySelectorAll('.breadcrumb li')).map(li=>({text:li.textContent.trim(),href:li.querySelector('a')?.href || ''}))`);
          const label = language === 'es' ? (section === 'library' ? 'Biblioteca' : 'Formaciones') : (section === 'library' ? 'Library' : 'Training');
          check((items.length === 0 && path !== activityurl) || (items.length > 0 && items[0].text === label && items[0].href.includes('section=' + section) &&
            !items.some(item=>['Página Principal','Home'].includes(item.text))), language + ' / ' + section + ': raíz correcta en ' + new URL(path, base).pathname);
          if (path === activityurl) {
            check(items.some(item=>item.href.includes('/course/view.php?id=' + courseid)), language + ' / ' + section + ': conserva enlace al curso en actividad');
            check(items.length >= 3, language + ' / ' + section + ': conserva ruta nativa de actividad');
            await screenshot('breadcrumb-' + section + '-' + language);
          }
        }
        await navigate('/local/orangeportal/add_document.php?lang=' + language);
        const documentcrumb = await evaluate(`Array.from(document.querySelectorAll('.breadcrumb li')).map(li=>li.textContent.trim())`);
        check(documentcrumb[0] === (language === 'es' ? 'Biblioteca' : 'Library'), language + ': nuevo documento empieza en Biblioteca');
      }
    }
    await logout();
    }
    await login(env.MOODLE_ADMIN_USER || 'admin', env.MOODLE_ADMIN_PASSWORD);
    for (const section of ['library', 'courses', 'incidents', 'notifications']) {
      await navigate('/local/orangeportal/categories.php?kind=' + section + '&lang=es');
      const rootlink = await evaluate(`document.querySelector('.breadcrumb a')?.href || ''`);
      check(rootlink.includes('section=' + section), section + ': gestión de categorías vuelve a su sección');
    }
    for (const section of ['incidents', 'notifications']) {
      await navigate('/local/orangeportal/manage.php?kind=' + section + '&lang=es');
      const rootlink = await evaluate(`document.querySelector('.breadcrumb a')?.href || ''`);
      check(rootlink.includes('section=' + section), section + ': gestión de publicaciones vuelve a su sección');
    }
    await navigate('/local/orangeportal/reports.php?lang=es');
    const reportcrumb = await evaluate(`document.querySelector('.breadcrumb li')?.textContent.trim()`);
    check(reportcrumb === 'Administración', 'Resultados empieza en Administración');
    console.log('Verificaciones de breadcrumbs: ' + results.length);
  } else if (process.argv.includes('--creation-only') || process.argv.includes('--creation-languages')) {
    await login('formador.orange', env.ORANGE_DEMO_PASSWORD);
    for (const language of (process.argv.includes('--creation-languages') ? ['es', 'en'] : ['es'])) {
      for (const section of ['library', 'courses']) {
        await navigate('/local/orangeportal/index.php?section='+section+'&lang='+language);
        if(section==='library') {
          check(await evaluate(`!document.querySelector('.orange-create-course')&&!!document.querySelector('a[href*="add_document.php"]')`),'Biblioteca ofrece documento directo sin crear colección');
          await navigate('/local/orangeportal/add_document.php?lang='+language);
          check(await evaluate(`!!document.querySelector('select[name="categoryid"]')&&!document.querySelector('input[name="fullname"]')&&!document.querySelector('[name="collection"]')`),'Biblioteca elige categoría y tipo de recurso');
          check(await evaluate(`document.querySelectorAll('select[name="categoryid"] option').length>0&&!Array.from(document.querySelectorAll('select[name="categoryid"] option')).some(o=>o.textContent.includes('Formación inicial'))`),'Destino de documentos limitado a categorías de Biblioteca');
        } else {
          const target=await evaluate(`document.querySelector('.orange-create-course').href`);
          await navigate(target+'&lang='+language);
          await wait(`!!document.querySelector('#id_fullname')`);
          check(await evaluate(`!!document.querySelector('select[name="category"]')`),'Formaciones conserva el formulario nativo de curso');
          check(await evaluate(`!Array.from(document.querySelectorAll('select[name="category"] option')).some(o=>o.textContent.includes('Biblioteca'))`),'Crear formación no mezcla categorías de Biblioteca');
        }
        check(await evaluate(`!document.querySelector('.secondary-navigation .nav-link')`),section+': sin barra Categoría/Más');
        const first=await evaluate(`document.querySelector('.breadcrumb a')?.href||''`);
        check(first.includes('section='+section),section+': breadcrumb vuelve a su sección');
        await screenshot('crear-'+section+'-'+language);
      }
    }
    console.log('Verificaciones de creación: ' + results.length);
  } else if (process.argv.includes('--navigation-only')) {
    await login('agente.orange', env.ORANGE_DEMO_PASSWORD);
    for (const [lang, labels] of [
      ['es', ['Biblioteca', 'Comunicados', 'Incidencias', 'Formaciones']],
      ['en', ['Library', 'Announcements', 'Incidents', 'Training']],
    ]) {
      await navigate('/local/orangeportal/index.php?section=library&lang=' + lang);
      check((await nav()).join('|') === labels.join('|'), 'Etiquetas del menú: ' + lang);
      for (const [i, section] of ['library', 'notifications', 'incidents', 'courses'].entries()) {
        check(await evaluate(`!document.querySelector('.orange-nav-panel')&&!document.querySelector('.orange-nav-trigger')&&!document.querySelector('script[src*="orangeportal/navigation.js"]')`), 'Sin paneles ni script de desplegables: ' + section + ' / ' + lang);
        await evaluate(`document.querySelector('.primary-navigation a[href*="section=${section}"]').click()`);
        await wait(`new URL(location.href).searchParams.get('section')==='${section}'&&document.readyState==='complete'`);
        check(await evaluate(`!!document.querySelector('.primary-navigation a.active[href*="section=${section}"]')&&document.body.innerText.includes(${JSON.stringify(labels[i])})`), 'Acceso directo y sección activa: ' + section + ' / ' + lang);
      }
    }
    await send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/local/orangeportal/index.php?section=library&lang=es');
    await evaluate(`document.querySelector('[data-target="theme_moove-drawers-primary"]').click()`);
    await wait(`!!document.querySelector('.drawer-primary.show')`);
    check(await evaluate(`!document.querySelector('.drawer-primary .orange-nav-trigger')&&!!document.querySelector('.drawer-primary a[href*="section=courses"]')`), 'Menú móvil con enlaces directos');
    await evaluate(`document.querySelector('.drawer-primary a[href*="section=courses"]').click()`);
    await wait(`new URL(location.href).searchParams.get('section')==='courses'&&document.readyState==='complete'`);
    check(await evaluate(`document.body.innerText.includes('Formaciones')&&!document.querySelector('.orange-nav-panel')`), 'Formaciones accesible desde móvil');
    await screenshot('navegacion-directa-movil');
    await send('Emulation.clearDeviceMetricsOverride');
    await navigate('/local/orangeportal/index.php?section=library&lang=es');
    await logout();
    await navigate('/login/index.php');
    await evaluate(`document.querySelector('#username').value='usuario.externo';document.querySelector('#password').value=${JSON.stringify(env.ORANGE_DEMO_PASSWORD)};document.querySelector('#loginbtn').click()`);
    await wait(`location.pathname!=='/login/index.php'&&document.readyState==='complete'`);
    check(await evaluate(`!document.querySelector('.primary-navigation a[href*="/local/orangeportal/"]')`), 'Usuario externo conserva navegación nativa');
    await writeFile('backups/previews/navigation-checks.json', JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de navegación completas: ' + results.length);
  } else {
  await login(env.MOODLE_ADMIN_USER || 'admin', env.MOODLE_ADMIN_PASSWORD);
  console.log('NAV ADMIN ' + JSON.stringify(await nav()));
  check((await nav()).join('|') === 'Biblioteca|Comunicados|Incidencias|Formaciones|Administración', 'Menú administrador: cuatro secciones y Administración');
  check(await evaluate(`document.querySelector('.orange-sidebar').innerText.includes('RPV/CENTREX')`), 'Árbol RPV/CENTREX');
  check(await evaluate(`document.querySelector('.navbar img.logo').naturalWidth > 0`), 'Logo cargado desde Moove');
  await screenshot('biblioteca-admin');
  for (const section of ['courses', 'incidents', 'notifications']) {
    await navigate('/local/orangeportal/index.php?section=' + section);
    check(await evaluate(`!!document.querySelector('.orange-intro') && !document.querySelector('.alert-danger')`), 'Sección ' + section);
    await screenshot(section + '-admin');
  }
  for (const path of ['/local/orangeportal/manage.php?kind=incidents&new=1', '/local/orangeportal/categories.php', '/local/orangeportal/reports.php', '/admin/settings.php?section=themesettingmoove']) {
    await navigate(path);
    check(!(await evaluate(`!!document.querySelector('.alert-danger')`)), 'Administración ' + path);
  }
  await logout();
  await login('agente.orange', env.ORANGE_DEMO_PASSWORD);
  console.log('NAV AGENTE ' + JSON.stringify(await nav()));
  check((await nav()).join('|') === 'Biblioteca|Comunicados|Incidencias|Formaciones', 'Agente sin menú de administración ni Inicio');
  check(await evaluate(`document.querySelectorAll('.orange-card').length===2`), 'Agente ve sus dos colecciones nativas');
  await screenshot('biblioteca-agente');
  await navigate('/local/orangeportal/index.php?section=incidents');
  check(await evaluate(`document.querySelectorAll('.orange-incidents-table tbody tr').length>=2`), 'Asignaciones individuales y por cohorte');
  const incidentUrl = await evaluate(`document.querySelector('.orange-incident-title').href`);
  await navigate(incidentUrl);
  await wait(`!!document.querySelector('.orange-notice-content')`);
  check(await evaluate(`document.body.innerText.includes('Leído:')`), 'Apertura registra lectura');
  check(await evaluate(`!document.querySelector('input[value=\"confirm\"]')`), 'Incidencia sin confirmación manual');
  await evaluate(`const f=document.querySelector('input[name="action"][value="state"]').form; f.querySelector('select').value='progress'; f.querySelector('button').click()`);
  await wait(`document.body.innerText.includes('Estado: En seguimiento')`);
  check(await evaluate(`document.body.innerText.includes('Agente Orange')`), 'Estado compartido con autor en histórico');
  await screenshot('incidencia-agente');
  await navigate('/local/orangeportal/index.php?section=notifications');
  check(await evaluate(`document.querySelectorAll('.orange-notifications-table tbody tr').length>=2`), 'Noticias por grupo y comunicados por cohorte');
  const notificationUrl = await evaluate(`document.querySelector('.orange-notification-title').href`);
  await navigate(notificationUrl);
  check(await evaluate(`!document.querySelector('input[value=\"confirm\"]')`), 'Comunicado sin confirmación manual');
  await send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
  await navigate('/local/orangeportal/index.php?section=library');
  await screenshot('biblioteca-movil');
  check(await evaluate(`document.documentElement.scrollWidth <= innerWidth + 2`), 'Móvil sin desbordamiento horizontal');
  await send('Emulation.clearDeviceMetricsOverride');
  await logout(); await login('agente.otro', env.ORANGE_DEMO_PASSWORD);
  check(await evaluate(`document.querySelectorAll('.orange-card').length===0`), 'Otro equipo no ve documentos restringidos');
  await navigate('/local/orangeportal/index.php?section=incidents');
  check(await evaluate(`document.querySelectorAll('.orange-incidents-table tbody tr').length===0`), 'Otro equipo no ve incidencias ni contadores ajenos');
  await navigate(incidentUrl);
  check(!(await evaluate(`!!document.querySelector('.orange-notice-content')`)), 'Acceso directo a incidencia ajena denegado');
  await navigate('/local/orangeportal/reports.php');
  check(!(await evaluate(`!!document.querySelector('.orange-intro')`)), 'Informes denegados a agente');
  await logout(); await login('editor.orange', env.ORANGE_DEMO_PASSWORD);
  await navigate('/local/orangeportal/manage.php?kind=notifications&new=1');
  check(await evaluate(`!!document.querySelector('input[name="title"]')`), 'Publicador delegado puede publicar sin ser administrador completo');
  await evaluate(`document.querySelector('input[name="title"]').value='Ejemplo · Comunicado publicado desde el portal';
    const body=document.querySelector('textarea[name="body[text]"]');
    body.value='<p>Comunicado de ejemplo creado con el formulario de administración. Confirma su lectura para probar la trazabilidad.</p>';
    if(window.tinymce?.get(body.id)){window.tinymce.get(body.id).setContent(body.value);window.tinymce.get(body.id).save();}
    const category=document.querySelector('select[name="categoryid"]');
    category.value=Array.from(category.options).find(o=>o.text==='Comunicado').value;
    const recipients=document.querySelector('select[name="users[]"]');
    for(const option of recipients.options){option.selected=option.text.includes('agente.orange');}
    recipients.dispatchEvent(new Event('change',{bubbles:true}));
    document.querySelector('input[name="published"][type="checkbox"]').checked=true;
    document.querySelector('input[name="submitbutton"]').click();`);
  await wait(`!document.querySelector('input[name="title"]') && document.readyState!=='loading'`);
  check(await evaluate(`document.body.innerText.includes('Ejemplo · Comunicado publicado desde el portal')`), 'Publicación real mediante formulario administrativo');
  await screenshot('gestion-publicaciones');
  await navigate('/local/orangeportal/reports.php');
  check(!(await evaluate(`!!document.querySelector('.orange-intro')`)), 'Publicador no obtiene permisos de resultados');
  await logout(); await login('coordinador.orange', env.ORANGE_DEMO_PASSWORD);
  const coordinatorAdminLink = await evaluate(`Array.from(document.querySelectorAll('.primary-navigation a.nav-link')).find(n=>n.textContent.trim()==='Administración').href`);
  check(coordinatorAdminLink.includes('/local/orangeportal/reports.php'), 'Administración del coordinador enlaza a resultados autorizados');
  await navigate(coordinatorAdminLink);
  check(await evaluate(`document.body.innerText.includes('Operativa Orange B2B') && document.body.innerText.includes('Agente Orange')`), 'Coordinación consulta resultados y confirmaciones');
  await screenshot('resultados-coordinador');
  await writeFile('backups/previews/browser-checks.json', JSON.stringify({date: new Date().toISOString(), results}, null, 2));
  console.log('Verificaciones completas: ' + results.length);
  }
} finally {
  if (ws) ws.close(); chrome.kill('SIGTERM');
}
