#!/usr/bin/env python3
"""Check documentation, commands and component integrity; build the installation kit."""
import sys
sys.dont_write_bytecode=True
from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import hashlib,importlib.util,json,re,subprocess,unicodedata
from docx import Document
from pypdf import PdfReader
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/manuales/20261112/instalacion'
spec=importlib.util.spec_from_file_location('install_content',OUT/'fuentes/contenido.py')
content=importlib.util.module_from_spec(spec);spec.loader.exec_module(content)
m=content.manuals[0]
def norm(s):return re.sub(r'\s+','',unicodedata.normalize('NFKC',s)).replace('\u200b','').replace('\u00ad','')
def fragments():
    yield m['title'];yield m['subtitle']
    for ch in m['chapters']:
        yield ch['title']
        for b in ch['blocks']:
            if b['type'] in ['p','code']:yield b['text']
            elif b['type']=='steps':yield from b['items']
            elif b['type']=='note':yield b['title'];yield b['text']
            elif b['type']=='image':yield b['caption']
            elif b['type']=='table':
                yield from b['headers']
                for row in b['rows']:yield from row
reader=PdfReader(OUT/(m['id']+'.pdf'));doc=Document(OUT/(m['id']+'.docx'))
pdftext=norm('\n'.join(p.extract_text() or '' for p in reader.pages))
wordtext=norm('\n'.join([p.text for p in doc.paragraphs]+[c.text for t in doc.tables for row in t.rows for c in row.cells]))
samples=list(fragments())
for sample in samples:
    assert norm(sample) in pdftext,('Falta en PDF',sample[:120])
    assert norm(sample) in wordtext,('Falta en Word',sample[:120])
figures=[b for ch in m['chapters'] for b in ch['blocks'] if b['type']=='image']
assert len(doc.inline_shapes)==len(figures)
for b in figures:assert (OUT/'imagenes'/b['file']).is_file(),b['file']
for i,page in enumerate(reader.pages,1):
    assert len(page.extract_text() or '')>170,('Página casi vacía',i)
    assert abs(float(page.mediabox.width)-595.28)<2 and abs(float(page.mediabox.height)-841.89)<2

commands=(OUT/'Comandos_instalacion_Orange.txt').read_text()
shellblocks=0
for ch in m['chapters']:
    for b in ch['blocks']:
        if b['type']!='code':continue
        assert b['text'] in commands
        if b['text'].lstrip().startswith('{'):
            json.loads(b['text']);continue
        if b['text'].startswith('* * * * *'):continue
        subprocess.run(['bash','-n'],input=b['text'],text=True,check=True,capture_output=True);shellblocks+=1
subprocess.run(['bash','-n',str(OUT/'material/cron/activar-cron-orange.sh')],check=True,capture_output=True)
assert (OUT/'material/cron/activar-cron-orange.sh').read_bytes()==(ROOT/'scripts/activar-cron-pre.sh').read_bytes()
template=json.loads((OUT/'material/configuracion/configuracion-orange.ejemplo.json').read_text())
assert template['teams']['enabled'] is False and template['allow_scope_change'] is False
assert template['catalog_file'] is None and template['administrators']==[]
payload=json.loads((OUT/'material/configuracion/ejemplo-peticion-teams.json').read_text())
schema=json.loads((OUT/'material/configuracion/esquema-teams.json').read_text())
assert set(payload)==set(schema['required'])=={'titulo','fechaPublicacion','servicioAfectado','contenido','url'}
assert all(isinstance(v,str) for v in payload.values())

components=json.loads((OUT/'fuentes/componentes.json').read_text())
roots={'orangeportal-2026111100.zip':ROOT/'moodle/local/orangeportal',
    'orange-2026102900.zip':ROOT/'moodle/theme/orange',
    'moove-4.5.1-2024100801-opcional.zip':ROOT/'moodle/theme/moove'}
for item in components:
    path=OUT/'material/componentes'/item['archivo']
    assert hashlib.sha256(path.read_bytes()).hexdigest()==item['sha256']
    with ZipFile(path) as z:
        assert z.testzip() is None
        assert len(z.namelist())==item['archivos']
        for name in z.namelist():
            pieces=Path(name).parts
            assert len(pieces)>1 and '..' not in pieces and not name.startswith('/')
            assert all(not p.startswith('.') and not p.startswith('~$') for p in pieces)
            assert not any(p in ['.env','moodledata'] for p in pieces)
            if 'config.php' in pieces:
                assert tuple(pieces) in [('orange','config.php'),('moove','config.php')],('Configuración ajena al tema',name)
            local=roots[item['archivo']].joinpath(*pieces[1:])
            assert local.read_bytes()==z.read(name),('Snapshot distinta al código revisado',name)
    assert hashlib.sha256(path.read_bytes()).hexdigest()==item['sha256']
catalog=json.loads((OUT/'material/catalogo_referencia/catalogos-orange.json').read_text())
assert catalog['format']==1
assert (OUT/'material/catalogo_referencia/catalogos-orange.json').read_bytes()==(ROOT/'backups/despliegues/pase-produccion-20261110/cargas/catalogos-orange.json').read_bytes()
for short in ['orange_publicador','orange_seguimiento','orange_calidad','orange_consulta_formativa','orange_gestorcategorias','orange_creadorcontenidos']:
    assert (OUT/'material/roles_referencia'/(short+'.xml')).read_bytes()==(ROOT/'docs/roles'/(short+'.xml')).read_bytes()

zipname='Instalacion_Orange_20261112.zip'
for path in [ROOT/'docs/README.md',ROOT/'docs/manuales/README.md',OUT/'README.md']:
    for target in re.findall(r'\]\(([^)]+)\)',path.read_text()):
        if target.startswith(('https:','http:','#')) or target.endswith(zipname):continue
        assert (path.parent/target.split('#')[0]).exists(),('Enlace roto',path,target)
report={'fecha':'2026-11-12','manual':m['title'],'version_plugin':'2026111100','version_tema':'2026102900',
    'paginas_pdf':len(reader.pages),'capitulos':len(m['chapters']),'fragmentos_coincidentes_pdf_word':len(samples),
    'figuras':len(figures),'bloques_bash_verificados_sin_ejecutar':shellblocks,
    'componentes':components,'integridad_componentes':'Verificada contra el código local revisado',
    'pruebas_locales':['check_theme_compatibility.php: salida 0, Moodle/Moove 4.5.1',
        'check_deployment_config.php: 17 comprobaciones, cambios revertidos',
        'check_deployment_catalog.php: 5 comprobaciones, cambios revertidos'],
    'limites':'No desplegado en PRE ni producción. PHP CLI, rutas, usuario, cron y versiones del campus requieren validación de Sistemas. Moove 4.5.2/core local 4.5.1 tiene una incompatibilidad conocida.',
    'capturas':'Reales del entorno local, URL Teams enmascarada; muestras del PDF revisadas visualmente.'}
(OUT/'fuentes/validacion.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
files=sorted(p for p in OUT.rglob('*') if p.is_file() and p.name not in [zipname,'SHA256SUMS.txt']
    and '__pycache__' not in p.parts and not p.name.startswith(('~$','.DS_Store')))
(OUT/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.relative_to(OUT).as_posix()+'\n' for p in files),encoding='utf8')
with ZipFile(OUT/zipname,'w',ZIP_DEFLATED) as z:
    for p in files+[OUT/'SHA256SUMS.txt']:z.write(p,'Instalacion_Orange_20261112/'+p.relative_to(OUT).as_posix())
with ZipFile(OUT/zipname) as z:assert z.testzip() is None
print(f'PDF y Word: {len(reader.pages)} páginas, {len(samples)} fragmentos y {len(figures)} figuras verificados.')
print(f'Bash: {shellblocks} bloques y ayuda cron verificados sin ejecutarlos. Tres componentes íntegros; ZIP comprobado.')
