#!/usr/bin/env python3
"""Manual operativo basado en Portal Orange 2026111006. Sin servicios externos."""
from pathlib import Path
from datetime import datetime, timezone
import html, json, subprocess
from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/manuales/teams-20261110'
STEM = 'Manual_configuracion_y_revision_Teams_Orange'
chapters = []
def chapter(title, *blocks): chapters.append({'title': title, 'blocks': list(blocks)})
def p(text): return ('p', text)
def steps(*items): return ('steps', items)
def note(text): return ('note', text)
def code(text): return ('code', text)
def table(headers, rows): return ('table', headers, rows)
chapter('Antes de empezar: responsables y funcionamiento',
 p('Este manual está dirigido a los servidores de preproducción y producción. Explica cómo configurar, revisar y diagnosticar los avisos de Microsoft Teams del portal Orange. Está basado en Moodle 4.5.1 y local_orangeportal 2026111006, revisión 9ea45bb, a fecha 10 de noviembre de 2026.'),
 table(['Responsable', 'Qué necesita hacer'], [
 ['Administrador general de Moodle', 'Configurar la URL y los tipos de aviso, PHP CLI y los registros de tareas. Requiere moodle/site:config.'],
 ['Sistemas', 'Copiar el plugin, ejecutar la actualización, identificar PHP y mantener el cron y la conectividad HTTPS.'],
 ['Formador o gestor autorizado', 'Guardar contenido visible y marcar Avisar por Teams al guardar cuando corresponda.'],
 ['Responsable de Power Automate / Teams', 'Mantener el flujo y sus conexiones, elegir el destino y revisar la entrega del mensaje.']]),
 note('El perfil Administrador Orange no concede por sí solo acceso a la configuración general de Moodle ni a la URL del servicio.'),
 p('Recorrido del aviso: guardar contenido visible con Teams marcado → tarea pendiente en Moodle → ejecución por cron o por un administrador → POST HTTPS a Power Automate → publicación del mensaje por el flujo en Teams.'),
 p('Los destinatarios de Moodle controlan quién puede consultar el contenido. No se convierten automáticamente en destinatarios de Teams: el chat, persona o canal se configura en el flujo. Los enlaces siguen requiriendo autenticación y permisos Orange.'))
chapter('Instalar o actualizar la versión necesaria',
 steps('Sistemas guarda una copia de la carpeta actual del plugin y de la base de datos del ambiente.',
 'Copiar la carpeta completa moodle/local/orangeportal del proyecto en local/orangeportal dentro de la instalación de Moodle del servidor.',
 'Comprobar que el archivo final es /var/www/moodle/local/orangeportal/version.php si esa es la raíz real de Moodle. Evitar una carpeta orangeportal anidada dentro de otra.',
 'Desde la raíz de Moodle ejecutar los comandos siguientes con el usuario de servicio que utilice ese servidor.',
 'En Administración del sitio → Plugins → Vista general de plugins, verificar Portal Orange / local_orangeportal, versión 2026111006.'),
 code('cd /var/www/moodle\nphp admin/cli/upgrade.php --non-interactive\nphp admin/cli/purge_caches.php'),
 note('/var/www/moodle es el ejemplo usado en pre. Sustituirlo por la ruta real si es diferente. Para esta revisión no cambia theme/orange. No copiar config.php, .env, moodledata ni la base de datos de otro ambiente.'),
 p('Si pre ya tiene 2026111005, la revisión 2026111006 solo añade diagnóstico y no crea tablas. Si tiene una versión anterior, el actualizador ejecuta también las migraciones pendientes. No es necesario reimportar los catálogos por actualizar estos logs.'),
 p('La URL firmada se configura por ambiente. Preproducción y producción deben mantener cada una su propia URL y su propio destino Teams.'),
 p('La actualización de archivos y base de datos no completa el despliegue de Teams. Antes de darlo por terminado:'),
 steps('Identificar PHP CLI y guardar su ruta en Moodle como indica el capítulo 3.',
 'Revisar la URL y activación de Teams del ambiente como indica el capítulo 5.',
 'Sistemas debe comprobar que el cron de esta instalación está programado cada minuto; si ya existe, no crear otro. Si falta, configurarlo con las rutas y el usuario reales como indica el capítulo 4.',
 'Tras revisar la configuración del ambiente, se puede ejecutar una comprobación puntual desde la raíz de Moodle con el comando siguiente.',
 'Confirmar que las siguientes tareas se procesan automáticamente y consultar su registro. Si todas siguen pendientes por falta de cron, la integración no queda operativa.'),
 code('php admin/cli/cron.php'),
 note('Esta ejecución procesa tareas pendientes de todo el campus y puede enviar mensajes reales. Ejecutarlo una vez no programa el cron: además debe quedar configurada su ejecución automática cada minuto. Para un único aviso, puede ejecutarse solo su tarea desde Moodle.'))
chapter('Configurar la ruta de PHP CLI',
 p('Esta ruta permite lanzar tareas desde la interfaz web. Sistemas debe identificar el ejecutable PHP utilizado por el servidor de Moodle de cada ambiente. La consulta se realiza en el entorno que ejecuta Moodle y con el usuario de servicio correspondiente.'),
 steps('Solicitar acceso a Sistemas para revisar el servidor de preproducción o producción correspondiente.',
 'Sistemas ejecuta command -v php en ese servidor. Si existe aislamiento de procesos o varios nodos, debe comprobar la ruta dentro del entorno de ejecución de Moodle y en los nodos que ejecuten tareas.',
 'Copiar la ruta devuelta. Puede ser /usr/bin/php, /usr/local/bin/php u otra. Debe corresponder a la versión PHP compatible que ya utiliza ese campus.',
 'Entrar como administrador general en Administración del sitio → Servidor → Rutas del sistema.',
 'En Ruta hacia PHP CLI pegar únicamente la ruta completa obtenida, sin argumentos ni comillas.',
 'Guardar cambios. Comprobar con Sistemas que el usuario del servidor web pueda ejecutar ese archivo.',
 'Repetir la comprobación en producción: no asumir que tiene la misma ruta que preproducción.'),
 code('command -v php'),
 p('Ruta directa de ajustes: /admin/settings.php?section=systempaths. Las rutas web de este manual se añaden al dominio del campus correspondiente.'),
 note('Ejemplo: si el comando devuelve /usr/bin/php, escribir exactamente /usr/bin/php en Ruta hacia PHP CLI. No copiar ese ejemplo sin comprobar la salida real.'),
 p('Si command -v php no devuelve nada, Sistemas debe localizar el ejecutable PHP CLI instalado y comprobar los permisos y el PATH del usuario de servicio. Configurar esta ruta no instala PHP ni modifica la versión utilizada por Moodle.'))
chapter('Comprobar el cron: ejecución automática',
 p('Configurar PHP CLI y configurar cron son dos tareas distintas. La primera permite ejecutar desde la web; cron procesa automáticamente las tareas pendientes. Los avisos esperan al menos un minuto desde el guardado y después dependen de cuándo los recoja Moodle.'),
 steps('Pedir a Sistemas que identifique el programador de tareas existente del campus y el usuario que lo ejecuta: cron, temporizador o planificador de la infraestructura.',
 'Confirmar que ejecuta admin/cli/cron.php de la instalación Moodle correcta cada minuto. Revisar la ruta completa de PHP, la raíz de Moodle, los permisos y las variables de entorno.',
 'Revisar la configuración existente antes de añadir nada. Si el campus ya procesa tareas, aprovechar el cron de esa instalación.',
 'Revisar la última ejecución y la salida del cron. Una salida sin errores del servidor web no demuestra que cron funcione.',
 'En instalaciones con varios nodos, comprobar con Sistemas qué nodo o servicio procesa las tareas y que utiliza el mismo código y configuración del campus.',
 'Con un aviso autorizado en la cola, comprobar que se procesa automáticamente sin pulsar Ejecutar desde la web.'),
 p('Ejemplo de entrada para el crontab del usuario de servicio, solo si las rutas reales son esas. Sistemas debe adaptarla y conservar la política habitual de registro de salida del campus:'),
 code('* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php'),
 note('Este es un ejemplo de configuración, no una orden para pegar en la consola. Sustituir /usr/bin/php por la ruta confirmada y /var/www/moodle por la raíz real. No crear un segundo cron si ya existe uno para ese campus.'),
 p('Si se requiere una ejecución puntual desde el servidor, Sistemas puede utilizar el siguiente ejemplo con el usuario y las rutas reales:'),
 code('/usr/bin/php /var/www/moodle/admin/cli/cron.php --keep-alive=0'),
 p('La opción --keep-alive=0 evita la espera adicional al terminar las tareas. Sin esa opción, Moodle 4.5 mantiene por defecto el proceso hasta tres minutos comprobando nuevas tareas; una tarea activa puede tardar más.'),
 note('Ejecutar cron procesa tareas pendientes de todo el campus y puede enviar mensajes reales. Para comprobar un único aviso, utilizar la tarea concreta de Teams desde la interfaz.'),
 p('Anotar dónde conserva Sistemas la salida del cron y quién recibe las alertas de fallo. Si el cron se detiene, el portal puede seguir funcionando mientras los avisos se acumulan.'))
chapter('Configurar el servicio de Teams desde Moodle',
 steps('Entrar con un administrador general de Moodle.',
 'Abrir Administración del sitio → Portal Orange → Mensajería Teams.',
 'En URL del servicio Power Automate pegar la URL HTTPS completa del disparador proporcionada para ese ambiente, incluida su firma. No usar los enlaces de ejemplo de este manual.',
 'Seleccionar los tipos que deben avisar: Incidencias, Comunicados, Biblioteca y Formaciones.',
 'Revisar Avisar de nuevas publicaciones y Avisar de modificaciones de contenido. Ambas opciones deben estar activas si se quieren cubrir altas y modificaciones.',
 'Marcar Activar envíos a Teams y guardar cambios.',
 'Revisar el Estado de la integración: último intento, último resultado y última petición aceptada. Ese estado es global, no el historial de una publicación concreta.'),
 code('/admin/settings.php?section=local_orangeportal_teams'),
 note('Guardar estos ajustes no envía una prueba ni recorre publicaciones antiguas. La instalación nueva tiene el servicio desactivado y la URL vacía; una actualización conserva la configuración existente.'),
 p('La URL firmada es una credencial: compartirla solo con quienes administran el flujo. Los registros ampliados muestran el destino sin parámetros de URL y ocultan firmas y tokens identificables.'))
chapter('Solicitar un aviso al publicar o editar',
 steps('Abrir o crear el comunicado, incidencia, documento de Biblioteca o formación con un perfil autorizado.',
 'Completar el título y el contenido que corresponda y revisar el bloque Destinatarios.',
 'Seleccionar usuarios, colectivos o grupos Orange si se desea limitar el acceso. Sin destinatarios específicos, el acceso queda dentro de la cohorte Orange. En Formaciones, la matrícula sigue siendo independiente.',
 'Marcar Visible para sus destinatarios. Guardar como borrador no genera aviso.',
 'Marcar Avisar por Teams al guardar. Esta opción empieza desmarcada en cada edición y solo solicita el aviso de ese guardado.',
 'Guardar. Esperar al menos un minuto y el siguiente procesamiento del cron.',
 'Revisar la cola y después el registro de la tarea correspondiente.'),
 table(['Caso', 'Comportamiento'], [
 ['Visible y Teams marcado', 'Se solicita el aviso si el tipo y la acción están habilitados en la configuración general.'],
 ['Visible sin Teams marcado', 'Se guarda y publica, sin solicitar ese aviso.'],
 ['Borrador, oculto o retirado', 'No se envía, aunque se intente marcar Teams.'],
 ['Cambio solo de estado de una incidencia', 'No genera aviso de Teams.'],
 ['Añadir recursos dentro de una formación o un comunicado', 'No avisa por sí solo; solicitarlo desde el formulario de la publicación o formación.'],
 ['Lecturas, notas, finalizaciones y eliminación', 'No generan estos avisos de contenido.']]),
 p('Si Teams aparece deshabilitado, revisar la activación general, la URL válida y las opciones de tipo y acción.'))
chapter('Revisar pendientes y ejecutar una tarea concreta',
 steps('Entrar en Administración del sitio → Servidor → Tareas → Tareas ad hoc, o usar Ver cola de envíos desde Mensajería Teams.',
 'Buscar la clase local_orangeportal\\task\\send_teams.',
 'Anotar el ID de la tarea, la fecha prevista y los datos kind, id y action. id identifica la publicación o curso, no la tarea.',
 'Si todavía no llegó la hora prevista, esperar. Si todas siguen pendientes después de varios minutos, revisar cron.',
 'Para una comprobación manual autorizada, ejecutar únicamente la tarea de Teams que se quiera comprobar. No usar Ejecutar todo para diagnosticar un único aviso.',
 'Abrir después su registro y comprobar si realmente hubo una llamada HTTP o se omitió con un motivo.'),
 code('/admin/tool/task/adhoctasks.php'),
 table(['Dato de la tarea', 'Significado'], [
 ['kind', 'notifications = Comunicado; incidents = Incidencia; library = Documento; courses = Formación.'],
 ['id', 'ID del contenido al que corresponde el aviso.'],
 ['action', 'created = alta o primera publicación; updated = actualización.'],
 ['endpoint', 'Huella de la URL configurada; no es la URL de destino.'],
 ['contenthash', 'Huella del mensaje autorizado al guardar; no contiene el JSON legible.'],
 ['generation', 'En formaciones identifica la publicación vigente y evita reactivar avisos retirados.']]),
 note('Una ejecución manual puede enviar un mensaje real al destino configurado. Completar una tarea no significa siempre enviar: también puede completarse porque ya no corresponde enviarla.'))
chapter('Leer los logs de solicitud y respuesta',
 steps('Abrir Administración del sitio → Servidor → Tareas → Registros de tareas.',
 'Buscar Enviar aviso Orange a Teams o la clase local_orangeportal\\task\\send_teams y localizar la ejecución por ID y fecha.',
 'Abrir el detalle y leer publicación, destino, JSON, respuesta HTTP y cuerpo de respuesta.',
 'Si no aparecen tareas correctas, revisar la configuración de Registro de tareas para registrar todas las ejecuciones, no solo fallos. También comprobar el período de conservación.'),
 code('/admin/tasklogs.php\n/admin/settings.php?section=tasklogging'),
 p('Ejemplo ficticio de una ejecución aceptada. El destino real tendrá su propio dominio y ruta; los parámetros firmados no se muestran:'),
 code('Teams: publicación notifications #399 · updated.\nTeams: destino POST https://servicio.example.invalid/invoke (parámetros de URL ocultos).\nTeams: JSON enviado: {"titulo":"Comunicado actualizado: Ejemplo","contenido":"Contenido de prueba","url":"https://campus.example.invalid/local/orangeportal/view.php?id=399"}\nTeams: identificador de envío: ...\nTeams: respuesta HTTP 202 (249 ms).\nTeams: cuerpo de respuesta: (vacío)\nTeams: HTTP request accepted.'),
 table(['Línea', 'Qué demuestra'], [
 ['Destino POST', 'Servidor y ruta utilizados; consultar la configuración para la URL firmada completa.'],
 ['JSON enviado', 'Los campos titulo, contenido y url del aviso concreto; sus valores proceden de la publicación.'],
 ['Respuesta HTTP', 'Código devuelto y duración de la petición.'],
 ['Cuerpo de respuesta', 'Texto o JSON devuelto por el servicio, depurado y limitado a 4.000 caracteres.'],
 ['Error de transporte', 'Fallo de conexión, TLS, DNS, proxy o política HTTP; incluye código y detalle depurado.'],
 ['Omitido', 'La tarea terminó sin llamar al servicio; leer el motivo.']]),
 note('Los detalles ampliados requieren 2026111006 y solo existen para ejecuciones nuevas. Los detalles que no se guardaron en ejecuciones anteriores no pueden reconstruirse. No hace falta reenviar un mensaje ya entregado solo para obtener un registro nuevo.'))
chapter('Interpretar errores y tareas omitidas',
 table(['Resultado o síntoma', 'Qué revisar'], [
 ['No se encuentra PHP CLI', 'Ejecutar command -v php en el entorno de ejecución del servidor Moodle correcto y guardar esa ruta en Rutas del sistema.'],
 ['Todas las tareas pendientes', 'Hora prevista, cron activo, última ejecución y configuración de PHP CLI para ejecuciones web.'],
 ['HTTP 200, 201 o 202', 'La llamada fue aceptada por el servicio. Comprobar la entrega posterior en Power Automate. Un cuerpo vacío es válido.'],
 ['HTTP 400', 'Leer el cuerpo del error; revisar el contrato JSON y la validación del disparador.'],
 ['HTTP 401 o 403', 'Comprobar la URL completa, su firma y los requisitos de acceso del flujo.'],
 ['HTTP 404', 'Comprobar ruta del disparador, ambiente y que el flujo siga existiendo.'],
 ['HTTP 429 o 5xx', 'Posibles límites o fallo del servicio. Revisar el flujo y los reintentos de Moodle.'],
 ['HTTP 3xx', 'La integración no sigue redirecciones; usar la URL HTTPS directa del disparador.'],
 ['HTTP 0 / error de transporte', 'Leer el detalle; Sistemas revisa DNS, salida HTTPS, certificados, proxy y restricciones HTTP de Moodle. No desactivar TLS para resolverlo.'],
 ['Integración, tipo o acción desactivados', 'Revisar las casillas del panel y la validez de la URL.'],
 ['La URL configurada cambió', 'La tarea antigua se descarta. Revisar el destino nuevo y solicitar un nuevo aviso guardando la publicación si corresponde.'],
 ['Publicación anterior de la formación', 'El curso se retiró o volvió a publicar; la tarea corresponde a una generación anterior.'],
 ['Contenido no publicado, visible o disponible', 'Comprobar borrador, retirada, eliminación y visibilidad de curso, categoría y recurso.'],
 ['Contenido cambió después del guardado', 'La tarea antigua no anunciará una edición silenciosa posterior. Solicitar un nuevo aviso desde el contenido vigente si corresponde.']]),
 p('Moodle reintenta fallos HTTP y de transporte con espera creciente, hasta cinco intentos. Las tareas omitidas no son errores HTTP y no se reenvían automáticamente. Si una tarea agota sus intentos, corregir primero la causa y revisar sus opciones de reprogramación en Moodle.'),
 p('Desactivar el servicio no congela la cola: las tareas que se ejecuten mientras está desactivado pueden descartarse. Cambiar la URL tampoco traslada los pendientes al nuevo destino.'))
chapter('Si Moodle acepta la llamada pero no aparece en Teams',
 steps('Anotar fecha, hora y zona horaria de la ejecución Moodle, título del mensaje, código HTTP e identificador de envío.',
 'Pedir al responsable del flujo que localice esa ejecución en el historial de Power Automate.',
 'Comprobar si el disparador recibió el JSON y si las acciones posteriores terminaron correctamente.',
 'Revisar las conexiones y permisos utilizados por la acción de Teams, además del chat, canal o persona configurados.',
 'Si no hay ejecución del flujo, confirmar que Moodle llamó al ambiente y disparador correctos. Comparar dominio y ruta del log con los ajustes, sin enviar la firma en capturas.',
 'Confirmar la recepción en el destino esperado antes de considerar terminada la prueba.'),
 note('HTTP 202 Accepted significa aceptación por el disparador; no confirma que la acción final de Teams haya publicado el mensaje.'),
 p('Ante un timeout, el flujo podría haber recibido la petición aunque Moodle registre un fallo. La cabecera X-Orange-Event-Id es estable en los reintentos de una tarea. El flujo puede utilizarla para deduplicar; sin esa lógica no se garantiza ausencia de duplicados.'))
chapter('Prueba completa y parte para Sistemas',
 steps('Confirmar versión del plugin, URL del ambiente, tipos/acciones habilitados, PHP CLI y cron.',
 'Acordar una publicación y un destino de prueba con las personas responsables: esta validación puede publicar un mensaje real.',
 'Crear un comunicado de prueba para los destinatarios Orange previstos y marcar Visible para sus destinatarios y Avisar por Teams al guardar.',
 'Guardar, anotar título y hora, y comprobar la tarea en la cola.',
 'Esperar al procesamiento o ejecutar solo esa tarea de forma autorizada.',
 'Abrir el log: verificar destino, JSON, respuesta HTTP y cuerpo.',
 'Verificar la ejecución de Power Automate y la recepción en Teams.',
 'Editar la publicación sin marcar Teams y comprobar que ese guardado no solicita un nuevo aviso.',
 'Guardar un borrador sin publicar y comprobar que tampoco solicita un aviso.',
 'Documentar el resultado; retirar el contenido de prueba cuando deje de ser necesario.'),
 table(['Dato a recoger', 'Valor'], [
 ['Ambiente y URL del campus', '____________________________'],
 ['Versión local_orangeportal', '____________________________'],
 ['Ruta PHP CLI / estado de cron', '____________________________'],
 ['Tipo, ID y título del contenido', '____________________________'],
 ['ID de tarea Moodle / fecha / zona horaria', '____________________________'],
 ['HTTP / error de transporte / motivo omitido', '____________________________'],
 ['Identificador de envío y ejecución del flujo', '____________________________'],
 ['Destino Teams y resultado final', '____________________________']]),
 p('Enviar a Sistemas o al responsable del flujo estos datos y el log depurado. No incluir contraseñas, firmas de la URL ni copias completas de la configuración.'),
 p('La validación debe realizarse primero en preproducción y repetirse en producción con los datos propios de ese ambiente. La preparación de este documento no ejecuta el cron ni reenvía avisos del servidor.'))

def build(title='Manual de configuración y revisión de Teams', scope='Servidores de preproducción y producción', manualversion='1.2', pluginversion='2026111006', reference='9ea45bb', description='Guía para Sistemas y administración general: configurar el servicio y PHP CLI, comprobar cron y revisar cada envío en los servidores del campus.'):
    OUT.mkdir(parents=True, exist_ok=True); (OUT/'fuentes').mkdir(exist_ok=True)
    d=Document(); sec=d.sections[0];sec.page_width=Cm(21);sec.page_height=Cm(29.7)
    sec.top_margin=Cm(1.8);sec.bottom_margin=Cm(1.8);sec.left_margin=Cm(2);sec.right_margin=Cm(2)
    d.styles['Normal'].font.name='Arial';d.styles['Normal'].font.size=Pt(10)
    d.styles['Normal'].paragraph_format.space_after=Pt(6)
    for name in ['Title','Heading 1','Heading 2']:
        d.styles[name].font.name='Arial';d.styles[name].font.color.rgb=RGBColor.from_string('111111')
    d.core_properties.title=title+' · Portal Orange'
    d.core_properties.author='Portal Orange';d.core_properties.created=datetime(2026,11,10,tzinfo=timezone.utc)
    sec.header.paragraphs[0].text='ORANGE · '+title.upper()
    footer=sec.footer.paragraphs[0];footer.add_run('Uso interno · 10/11/2026 · Página ')
    field=OxmlElement('w:fldSimple');field.set(qn('w:instr'),'PAGE');footer._p.append(field)
    d.add_heading('Portal Orange',0);d.add_heading(title,0)
    d.add_paragraph(scope+' · Administración general y Sistemas')
    d.add_paragraph('Versión del manual '+manualversion+' · 10 de noviembre de 2026\nPlugin local_orangeportal '+pluginversion+' · Moodle 4.5.1\nReferencia de código '+reference)
    d.add_paragraph('Instrucciones para configurar y revisar el servicio en los servidores del campus. Todos los ejemplos de mensajes y dominios son ficticios; no contiene la URL firmada del servicio.')
    d.add_heading('Índice',1)
    for i,ch in enumerate(chapters,1): d.add_paragraph(f'{i}. {ch["title"]}')
    sections=[]
    for i,ch in enumerate(chapters,1):
        d.add_page_break();d.add_heading(f'{i}. {ch["title"]}',1);blocks=[]
        for block in ch['blocks']:
            typ=block[0]
            if typ in ['p','note']:
                para=d.add_paragraph(block[1]);para.runs[0].bold=typ=='note'
                blocks.append('<p class="'+typ+'">'+html.escape(block[1])+'</p>')
            elif typ=='steps':
                for n,t in enumerate(block[1],1):d.add_paragraph(f'{n}. {t}')
                blocks.append('<ol>'+''.join('<li>'+html.escape(t)+'</li>' for t in block[1])+'</ol>')
            elif typ=='code':
                para=d.add_paragraph();run=para.add_run(block[1]);run.font.name='Courier New';run.font.size=Pt(8)
                blocks.append('<pre>'+html.escape(block[1])+'</pre>')
            elif typ=='table':
                t=d.add_table(rows=1,cols=2);t.style='Table Grid'
                for cell,text in zip(t.rows[0].cells,block[1]):
                    cell.text=text
                    fill=OxmlElement('w:shd');fill.set(qn('w:fill'),'222222');cell._tc.get_or_add_tcPr().append(fill)
                    for run in cell.paragraphs[0].runs:run.font.color.rgb=RGBColor(255,255,255);run.bold=True
                tr=t.rows[0]._tr.get_or_add_trPr();tr.append(OxmlElement('w:tblHeader'))
                for row in block[2]:
                    cells=t.add_row().cells
                    for cell,text in zip(cells,row):
                        cell.text=text
                        for para in cell.paragraphs:
                            for r in para.runs:r.font.size=Pt(9)
                    cells[0]._tc.getparent().get_or_add_trPr().append(OxmlElement('w:cantSplit'))
                blocks.append('<table><thead><tr>'+''.join('<th>'+html.escape(v)+'</th>' for v in block[1])+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+html.escape(v)+'</td>' for v in row)+'</tr>' for row in block[2])+'</tbody></table>')
        sections.append('<section class="chapter"><h1>'+html.escape(f'{i}. {ch["title"]}')+'</h1>'+''.join(blocks)+'</section>')
    d.save(OUT/(STEM+'.docx'))
    subprocess.run(['textutil','-convert','doc','-output',str(OUT/(STEM+'.doc')),str(OUT/(STEM+'.docx'))],check=True)
    css='''@page{size:A4;margin:19mm 20mm 18mm}*{box-sizing:border-box}body{font:10pt Arial,sans-serif;color:#222;line-height:1.36}h1{font-size:19pt;line-height:1.15;margin:0 0 15px;color:#111}h2{font-size:14pt}.cover h1{font-size:29pt;color:#111}.brand{font-size:24pt;color:#ff7900;font-weight:bold;margin:45px 0}.meta{color:#555}section{break-before:page}section.cover{break-before:auto}p{margin:0 0 10px}.note{background:#f3f3f3;border-left:3px solid #ff7900;padding:9px 11px}li{padding-left:3px;margin-bottom:7px}ol{padding-left:21px}table{width:100%;border-collapse:collapse;margin:11px 0;font-size:9pt}th{text-align:left;background:#222;color:white}th,td{padding:6px 8px;border-bottom:1px solid #ddd;vertical-align:top}td:first-child{width:32%;font-weight:bold}tr:nth-child(even){background:#f5f5f5}tr{break-inside:avoid}thead{display:table-header-group}pre{font:8pt/1.45 Courier New,monospace;background:#f4f4f4;padding:10px;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word}h1,h2{break-after:avoid}.note{break-inside:avoid}'''
    cover='<section class="cover"><div class="brand">orange</div><h1>'+html.escape(title)+'</h1><p>Portal Orange · '+html.escape(scope)+'</p><p class="meta">Versión '+manualversion+' · 10 de noviembre de 2026<br>local_orangeportal '+pluginversion+' · Moodle 4.5.1<br>Código de referencia: '+html.escape(reference)+'</p><p>'+html.escape(description)+'</p><p>Los ejemplos son ficticios y no incluyen credenciales reales.</p><h2>Índice</h2><ol>'+''.join('<li>'+html.escape(ch['title'])+'</li>' for ch in chapters)+'</ol></section>'
    source='<!doctype html><html lang="es"><head><meta charset="utf-8"><title>'+html.escape(title)+' · Portal Orange</title><style>'+css+'</style></head><body>'+cover+''.join(sections)+'</body></html>'
    (OUT/'fuentes'/(STEM+'.html')).write_text(source)
    (OUT/'fuentes/manifest.json').write_text(json.dumps([{'id':STEM,'title':title,'chapters':len(chapters)}],ensure_ascii=False,indent=2))
    print('DOC, DOCX y fuente HTML generados:',OUT)
if __name__=='__main__':build()
