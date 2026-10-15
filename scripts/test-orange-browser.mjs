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
  if (process.argv.includes('--admin-languages-only')) {
    await login('editor.orange', env.ORANGE_DEMO_PASSWORD);
    for (const language of ['es', 'en']) {
      const en = language === 'en';
      for (const kind of ['incidents', 'notifications']) {
        await navigate('/local/orangeportal/manage.php?kind=' + kind + '&lang=' + language);
        check(await evaluate(`document.querySelector('.orange-intro h2').textContent===${JSON.stringify(en ? 'Orange publications' : 'Publicaciones Orange')} && document.querySelector('.orange-admin-links .active').textContent===${JSON.stringify(en ? (kind==='incidents'?'Incidents':'Notifications') : (kind==='incidents'?'Incidencias':'Notificaciones'))}`), language+' / '+kind+': cabecera y pestaña traducidas');
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
        const label=en?({library:'Library',courses:'Courses',incidents:'Incidents',notifications:'Notifications'})[kind]:({library:'Biblioteca',courses:'Cursos',incidents:'Incidencias',notifications:'Notificaciones'})[kind];
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
          const label = language === 'es' ? (section === 'library' ? 'Biblioteca' : 'Cursos') : (section === 'library' ? 'Library' : 'Courses');
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
        const sectionLabel = language === 'es' ? (section === 'library' ? 'Biblioteca' : 'Cursos') : (section === 'library' ? 'Library' : 'Courses');
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
    const openMenu = async section => {
      await wait(`!!document.querySelector('.primary-navigation [aria-controls="orange-menu-${section}"]')`);
      await evaluate(`document.querySelector('.primary-navigation [aria-controls="orange-menu-${section}"]').click()`);
      await wait(`!document.getElementById('orange-menu-${section}').hidden`);
    };
    await login(env.MOODLE_ADMIN_USER || 'admin', env.MOODLE_ADMIN_PASSWORD);
    for (const section of ['library', 'courses', 'incidents', 'notifications']) {
      await openMenu(section);
      check(await evaluate(`document.querySelector('#orange-menu-${section} .orange-tree-all').href.includes('section=${section}')`), 'Desplegable ' + section + ': Ver todo');
      if (section === 'library') {
        check(await evaluate(`document.querySelector('#orange-menu-library').innerText.includes('RPV/CENTREX') && document.querySelectorAll('#orange-menu-library details').length>10`), 'Árbol completo de Biblioteca en barra superior');
        globalThis.adminTreeCount = await evaluate(`document.querySelectorAll('#orange-menu-library .orange-tree-link').length`);
        await screenshot('menu-biblioteca-admin');
      }
      if (section === 'incidents') {
        check(await evaluate(`document.querySelector('#orange-menu-incidents .orange-count').textContent !== ''`), 'Contadores de incidencias en desplegable');
      }
      await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
      check(await evaluate(`document.getElementById('orange-menu-${section}').hidden && document.activeElement.getAttribute('aria-expanded')==='false'`), 'Escape cierra y devuelve foco: ' + section);
    }
    await openMenu('library');
    await evaluate(`document.querySelector('#orange-menu-library .orange-tree-link').click()`);
    await wait(`location.search.includes('category=') && !!document.querySelector('.orange-intro')`);
    check(await evaluate(`new URL(location.href).searchParams.get('category')>0`), 'Categoría del menú navega con filtro');
    await logout(); await login('agente.orange', env.ORANGE_DEMO_PASSWORD);
    await openMenu('library');
    check(await evaluate(`document.querySelectorAll('#orange-menu-library .orange-tree-link').length===${globalThis.adminTreeCount}`), 'Agente ve el mismo árbol completo que administrador');
    await screenshot('menu-biblioteca-agente');
    await navigate('/local/orangeportal/index.php?section=incidents');
    await openMenu('incidents');
    check(await evaluate(`document.querySelectorAll('.orange-incidents-table tbody tr').length>=2 && JSON.stringify(Array.from(document.querySelectorAll('#orange-menu-incidents .orange-count')).map(n=>n.textContent))===JSON.stringify(Array.from(document.querySelectorAll('.orange-sidebar .orange-count')).map(n=>n.textContent))`), 'Contadores del menú coinciden con las incidencias accesibles');
    await send('Emulation.setDeviceMetricsOverride', {width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/local/orangeportal/index.php?section=library');
    await evaluate(`document.querySelector('[data-target="theme_moove-drawers-primary"]').click()`);
    await wait(`!!document.querySelector('.drawer-primary.show')`);
    await evaluate(`document.querySelector('.drawer-primary [aria-controls="orange-menu-library"]').click()`);
    await wait(`!document.getElementById('orange-menu-library').hidden`);
    check(await evaluate(`document.getElementById('orange-menu-library').getBoundingClientRect().right<=innerWidth && document.documentElement.scrollWidth<=innerWidth+2`), 'Desplegable móvil sin desbordamiento');
    await screenshot('menu-biblioteca-movil');
    await send('Emulation.clearDeviceMetricsOverride');
    await logout(); await login('agente.otro', env.ORANGE_DEMO_PASSWORD);
    await openMenu('library');
    check(await evaluate(`document.querySelectorAll('#orange-menu-library .orange-tree-link').length===${globalThis.adminTreeCount} && document.querySelectorAll('.orange-card').length===0`), 'Otro equipo ve árbol completo sin contenidos restringidos');
    await openMenu('incidents');
    check(await evaluate(`Array.from(document.querySelectorAll('#orange-menu-incidents .orange-count')).every(n=>n.textContent==='0')`), 'Otro equipo no ve contadores ajenos');
    await writeFile('backups/previews/navigation-checks.json', JSON.stringify({date:new Date().toISOString(),results},null,2));
    console.log('Verificaciones de navegación completas: ' + results.length);
  } else {
  await login(env.MOODLE_ADMIN_USER || 'admin', env.MOODLE_ADMIN_PASSWORD);
  console.log('NAV ADMIN ' + JSON.stringify(await nav()));
  check((await nav()).join('|') === 'Biblioteca|Cursos|Incidencias|Notificaciones|Administración', 'Menú administrador: cuatro secciones y Administración');
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
  check((await nav()).join('|') === 'Biblioteca|Cursos|Incidencias|Notificaciones', 'Agente sin menú de administración ni Inicio');
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
