// Local browser smoke test using Chrome DevTools; no npm dependencies.
import {spawn} from 'node:child_process';
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
  if (process.argv.includes('--training-columns-only')) {
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
    check(await evaluate(`document.querySelectorAll('a[href*="export.php"]').length===2`),'Exportaciones de lecturas y formación disponibles');
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
    check(await evaluate(`['q','category','impact','datefrom','dateto','pending','unread'].every(n=>document.querySelector('.orange-communications-filters [name="'+n+'"]'))`), 'Búsqueda, categoría, fechas, confirmación y lectura en lateral');
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
    check(await evaluate(`!!document.querySelector('[name="pending"]')&&!!document.querySelector('.orange-communications-filters')`),'Formador conserva filtro de confirmación y lateral');
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
      await navigate('/local/orangeportal/reports.php?lang='+language);
      check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en?'Results and confirmations':'Resultados y confirmaciones')} && document.querySelector('.orange-admin-links .active').textContent===${JSON.stringify(en?'Confirmations and results':'Confirmaciones y resultados')}`),language+': resultados y navegación traducidos');
      check(await evaluate(`document.body.innerText.includes(${JSON.stringify(en?'Active participants':'Participantes activos')}) && document.body.innerText.includes(${JSON.stringify(en?'Receipt, reading and confirmation':'Recepción, lectura y confirmación')}) && document.body.innerText.includes(${JSON.stringify(en?'Current recipient':'Destinatario actual')})`),language+': tablas e instrucciones de informes traducidas');
      check(await evaluate(`document.querySelector('h1').textContent.trim()===${JSON.stringify(en?'Results and confirmations':'Resultados y confirmaciones')} && document.title.includes(${JSON.stringify(en?'Results and confirmations':'Resultados y confirmaciones')})`),language+': título y encabezado de informes traducidos');
      const noticeurl=await evaluate(`document.querySelector('table a[href*="noticeid="]')?.href`);
      check(!!noticeurl,language+': aviso disponible para comprobar histórico');
      await navigate(noticeurl);
      check(await evaluate(`document.body.innerText.includes(${JSON.stringify(en?'Confirmation history by version':'Histórico de confirmaciones por versión')})`),language+': histórico de confirmaciones traducido');
      if(en)await screenshot('administracion-resultados-ingles');
    }
    await logout();await login('formador.orange',env.ORANGE_DEMO_PASSWORD);
    await navigate('/local/orangeportal/add_document.php?lang=en');
    check(await evaluate(`document.querySelector('.orange-intro h2').textContent==='New document' && document.querySelector('#id_submitbutton').value==='Add document'`),'Formador: nuevo documento en inglés');
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
      const collectionTitle = language === 'es' ? 'Crear una nueva colección documental' : 'Create a new document collection';
      const collectionWord = language === 'es' ? 'colección' : 'Collection';
      const courseTitle = language === 'es' ? 'Crear un nuevo curso' : 'Add a new course';
      for (const section of ['library', 'courses']) {
        await navigate('/local/orangeportal/index.php?section=' + section + '&lang=' + language);
        const target = await evaluate(`document.querySelector('.orange-create-course').href`) + '&lang=' + language;
        await navigate(target);
        await wait(`!!document.querySelector('#id_fullname')`);
        if (section === 'library') {
          await wait(`Array.from(document.querySelectorAll('h1,h2,h3')).some(h=>h.textContent.trim()===${JSON.stringify(collectionTitle)})`);
          check(await evaluate(`document.querySelector('label[for="id_fullname"]').textContent.includes(${JSON.stringify(collectionWord)})`), 'Biblioteca: nombres de colección en formulario');
          check(await evaluate(`document.title.includes(${JSON.stringify(collectionTitle)})`), 'Biblioteca: título de colección');
          check(await evaluate(`!(/\\bcurso(s)?\\b/i.test(document.querySelector('form.mform').innerText))`), 'Biblioteca: formulario sin términos de curso');
          check(await evaluate(`!document.querySelector('select[name="showgrades"]') && !document.querySelector('select[name="enablecompletion"]') && !document.querySelector('[data-formatchooser-field="selector"]')`), 'Biblioteca: sin controles académicos');
          check(await evaluate(`document.querySelector('input[name="format"]').value==='topics' && document.querySelector('input[name="enablecompletion"]').value==='0'`), 'Biblioteca: valores documentales nativos');
        } else {
          check(await evaluate(`Array.from(document.querySelectorAll('h1,h2,h3')).some(h=>h.textContent.includes(${JSON.stringify(courseTitle)}))`), 'Cursos conserva el título de curso');
        }
        check(await evaluate(`!document.querySelector('.secondary-navigation .nav-link')`), section + ': sin barra Categoría/Más');
        check(await evaluate(`!document.querySelector('.breadcrumb')?.textContent.includes('Administrar cursos y categorías')`), section + ': ruta sin administración de categorías');
        const breadcrumb = await evaluate(`Array.from(document.querySelectorAll('.breadcrumb li')).map(li=>({text:li.textContent.trim(), href:li.querySelector('a')?.href || ''}))`);
        const sectionLabel = language === 'es' ? (section === 'library' ? 'Biblioteca' : 'Formaciones') : (section === 'library' ? 'Library' : 'Training');
        check(breadcrumb.length === 2 && breadcrumb[0].text === sectionLabel &&
          breadcrumb[0].href.includes('section=' + section) && !breadcrumb.some(item=>item.text.includes('Página Principal')),
          section + ': breadcrumb empieza en la sección correcta');
        await navigate(breadcrumb[0].href);
        check(await evaluate(`location.pathname==='/local/orangeportal/index.php' && new URLSearchParams(location.search).get('section')===${JSON.stringify(section)}`), section + ': enlace del breadcrumb vuelve a su sección');
        await navigate(target);
        await wait(`!!document.querySelector('#id_fullname')`);
        check(await evaluate(`!!document.querySelector('.primary-navigation .nav-link.active[href*="section=${section}"]')`), section + ': menú principal activo');
        const options = await evaluate(`Array.from(document.querySelectorAll('select[name="category"] option')).filter(o=>o.value && o.value!=='0').map(o=>o.textContent)`);
        check(options.length > 0 && options.every(t=>t.includes(section === 'library' ? 'Biblioteca' : 'Cursos')), section + ': categorías de su sección');
        await screenshot('crear-' + section + '-' + language);
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
  const canConfirm = await evaluate(`!!document.querySelector('input[name="action"][value="confirm"]')`);
  if (canConfirm) {
    await evaluate(`document.querySelector('input[name="action"][value="confirm"]').form.querySelector('button').click()`);
    await wait(`document.body.innerText.includes('Lectura confirmada el')`);
  }
  check(await evaluate(`document.body.innerText.includes('Lectura confirmada el')`), 'Confirmación explícita de incidencia');
  await evaluate(`const f=document.querySelector('input[name="action"][value="state"]').form; f.querySelector('select').value='progress'; f.querySelector('button').click()`);
  await wait(`document.body.innerText.includes('Estado: En seguimiento')`);
  check(await evaluate(`document.body.innerText.includes('Agente Orange')`), 'Estado compartido con autor en histórico');
  await screenshot('incidencia-agente');
  await navigate('/local/orangeportal/index.php?section=notifications');
  check(await evaluate(`document.querySelectorAll('.orange-notifications-table tbody tr').length>=2`), 'Noticias por grupo y comunicados por cohorte');
  const notificationUrl = await evaluate(`document.querySelector('.orange-notification-title').href`);
  await navigate(notificationUrl);
  if (await evaluate(`!!document.querySelector('input[name="action"][value="confirm"]')`)) {
    await evaluate(`document.querySelector('input[name="action"][value="confirm"]').form.querySelector('button').click()`);
    await wait(`document.body.innerText.includes('Lectura confirmada el')`);
  }
  check(await evaluate(`document.body.innerText.includes('Lectura confirmada el')`), 'Confirmación explícita de notificación');
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
