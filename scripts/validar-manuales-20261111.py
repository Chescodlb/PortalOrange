#!/usr/bin/env python3
"""Validate source parity, images, links and archival integrity; package the new manuals."""
import sys
sys.dont_write_bytecode=True
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib, importlib.util, json, re, unicodedata
from docx import Document
from pypdf import PdfReader

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/manuales/20261111'
ARCHIVE=ROOT/'docs/archivo/manuales-hasta-20261111'
spec=importlib.util.spec_from_file_location('contenido',OUT/'fuentes/contenido.py')
content=importlib.util.module_from_spec(spec);spec.loader.exec_module(content)
def norm(s):return re.sub(r'\s+','',unicodedata.normalize('NFKC',s)).replace('\u200b','').replace('\u00ad','')
def fragments(m):
    yield m['title'];yield m['subtitle']
    for c in m['chapters']:
        yield c['title']
        for b in c['blocks']:
            if b['type']=='p':yield b['text']
            elif b['type']=='steps':yield from b['items']
            elif b['type']=='note':yield b['title'];yield b['text']
            elif b['type']=='image':yield b['caption']
            elif b['type']=='table':
                yield from b['headers']
                for row in b['rows']:yield from row
checks=[]
for m in content.manuals:
    reader=PdfReader(OUT/(m['id']+'.pdf'))
    pdftext=norm('\n'.join(p.extract_text() or '' for p in reader.pages))
    doc=Document(OUT/(m['id']+'.docx'))
    wordtext=norm('\n'.join([p.text for p in doc.paragraphs]+[c.text for t in doc.tables for row in t.rows for c in row.cells]))
    samples=list(fragments(m))
    for sample in samples:
        assert norm(sample) in pdftext,(m['id'],'Falta en PDF',sample[:100])
        assert norm(sample) in wordtext,(m['id'],'Falta en Word',sample[:100])
    figures=[b for c in m['chapters'] for b in c['blocks'] if b['type']=='image']
    assert len(doc.inline_shapes)==len(figures),(m['id'],'Imágenes Word')
    for b in figures:assert (OUT/'imagenes'/b['file']).is_file(),b['file']
    pdfimages=0
    for i,page in enumerate(reader.pages,1):
        assert len(page.extract_text() or '')>170,(m['id'],'Página casi vacía',i)
        assert abs(float(page.mediabox.width)-595.28)<2 and abs(float(page.mediabox.height)-841.89)<2,(m['id'],'Formato no A4',i)
        for obj in page.get('/Resources',{}).get('/XObject',{}).values():
            if obj.get_object().get('/Subtype')=='/Image':pdfimages+=1
    assert pdfimages>=len(figures),(m['id'],'Imágenes PDF',pdfimages,len(figures))
    checks.append({'manual':m['title'],'archivo':m['id'],'paginas_pdf':len(reader.pages),
        'tareas':len(m['chapters']),'fragmentos_coincidentes_pdf_word':len(samples),'figuras':len(figures)})
    print(f'{m["id"]}: {len(reader.pages)} páginas; {len(samples)} fragmentos coinciden; {len(figures)} figuras.')

archived=json.loads((ARCHIVE/'ARCHIVO_INTEGRIDAD.json').read_text())
for item in archived:
    path=ARCHIVE/item['ruta']
    assert path.is_file(),('Archivo anterior ausente',item['ruta'])
    assert hashlib.sha256(path.read_bytes()).hexdigest()==item['sha256'],('Archivo anterior alterado',item['ruta'])

# Active indices must point to real files; the original archived index keeps historical paths.
indices=[ROOT/'docs/README.md',ROOT/'docs/manuales/README.md',OUT/'README.md',ARCHIVE/'README.md']
broken=[]
zipname='Manuales_Orange_PRE_20261111.zip'
for path in indices:
    for target in re.findall(r'\]\(([^)]+)\)',path.read_text()):
        if target.startswith(('http:','https:','#')):continue
        target=target.split('#')[0]
        if target.endswith(zipname):continue # Created immediately below.
        if not (path.parent/target).exists():broken.append((str(path),target))
assert not broken,broken

captures=json.loads((OUT/'fuentes/capturas.json').read_text())
images={b['file'] for m in content.manuals for c in m['chapters'] for b in c['blocks'] if b['type']=='image'}
assert {x['id']+'.png' for x in captures['capturas']}==images,'Proveniencia incompleta de capturas'
assert len(list((OUT/'roles_para_importar').glob('*.xml')))==6
report={'fecha':'2026-11-11','objetivo':'PRE actual; asignación de roles desde Moodle',
    'origen_imagenes':'Capturas reales del Moodle local de demostración, sin acceso al servidor PRE; leyenda explicativa con iconos originales del portal',
    'comprobacion_visual':'Revisión de capturas utilizadas y muestras de páginas renderizadas del PDF',
    'archivos_anteriores_verificados_sha256':len(archived),'capturas_distintas':len(images),
    'enlaces_indices_activos':'correctos','manuales':checks}
(OUT/'fuentes/validacion.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
files=sorted(p for p in OUT.rglob('*') if p.is_file() and p.name not in [zipname,'SHA256SUMS.txt'] and not p.name.startswith('~$') and '__pycache__' not in p.parts)
(OUT/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.relative_to(OUT).as_posix()+'\n' for p in files),encoding='utf8')
with ZipFile(OUT/zipname,'w',ZIP_DEFLATED) as archive:
    for p in files+[OUT/'SHA256SUMS.txt']:
        archive.write(p,'Manuales_Orange_PRE_20261111/'+p.relative_to(OUT).as_posix())
with ZipFile(OUT/zipname) as archive:assert archive.testzip() is None
print(f'Archivo anterior: {len(archived)} archivos intactos. Capturas: {len(images)}. ZIP generado y comprobado.')
