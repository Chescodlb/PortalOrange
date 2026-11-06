#!/usr/bin/env python3
"""Generate matching editable Word documents and print HTML from reviewed role content."""
import sys
sys.dont_write_bytecode = True
from pathlib import Path
from datetime import datetime, timezone
import importlib.util, html, json, subprocess
from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/manuales/20261106'
SOURCE = OUT / 'fuentes/contenido_manuales.py'
spec = importlib.util.spec_from_file_location('orange_manual_content', SOURCE)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
DATE = '6 de noviembre de 2026'
REV = '48461c2'
BRAND = 'FF7900'

def shade(cell, fill):
    pr=cell._tc.get_or_add_tcPr(); el=OxmlElement('w:shd');el.set(qn('w:fill'),fill);pr.append(el)
def cant_split(row):
    pr=row._tr.get_or_add_trPr();pr.append(OxmlElement('w:cantSplit'))
def field(paragraph,name):
    f=OxmlElement('w:fldSimple');f.set(qn('w:instr'),name)
    r=OxmlElement('w:r');t=OxmlElement('w:t');t.text='1';r.append(t);f.append(r);paragraph._p.append(f)
def add_table(doc, headers, rows):
    t=doc.add_table(rows=1, cols=len(headers));t.autofit=False
    widths={2:[6.0,10.2],3:[4.2,5.7,6.3]}[len(headers)]
    for col,width in zip(t.columns,widths):col.width=Cm(width)
    trpr=t.rows[0]._tr.get_or_add_trPr();trpr.append(OxmlElement('w:tblHeader'))
    for cell,text in zip(t.rows[0].cells,headers):
        shade(cell,'000000');r=cell.paragraphs[0].add_run(text);r.bold=True;r.font.color.rgb=RGBColor(255,255,255)
    for i,row in enumerate(rows):
        cells=t.add_row().cells
        for cell,text,width in zip(cells,row,widths):
            cell.width=Cm(width);cell.text=str(text);shade(cell,'F4F4F4' if i%2==0 else 'FFFFFF')
        cant_split(t.rows[-1])
    for row in t.rows:
        for cell in row.cells:
            for p in cell.paragraphs:
                p.paragraph_format.space_after=Pt(5);p.paragraph_format.space_before=Pt(5)
                for r in p.runs:r.font.name='Arial';r.font.size=Pt(9.5)
    doc.add_paragraph().paragraph_format.space_after=Pt(0)

def word_manual(manual):
    d=Document();sec=d.sections[0];sec.page_width=Cm(21);sec.page_height=Cm(29.7)
    sec.top_margin=Cm(1.9);sec.bottom_margin=Cm(1.8);sec.left_margin=Cm(2.3);sec.right_margin=Cm(2.3)
    sec.header_distance=Cm(.8);sec.footer_distance=Cm(.8);sec.different_first_page_header_footer=True
    normal=d.styles['Normal'];normal.font.name='Arial';normal.font.size=Pt(10.5)
    normal.paragraph_format.line_spacing=1.12;normal.paragraph_format.space_after=Pt(7)
    for name,size in [('Title',30),('Heading 1',21),('Heading 2',14)]:
        st=d.styles[name];st.font.name='Arial';st.font.size=Pt(size);st.font.color.rgb=RGBColor.from_string('000000');st.font.bold=True
        st.paragraph_format.keep_with_next=True
    d.styles['Heading 1'].paragraph_format.space_after=Pt(15)
    header=sec.header.paragraphs[0];r=header.add_run('ORANGE  /  PORTAL DE CONOCIMIENTO');r.bold=True;r.font.color.rgb=RGBColor.from_string(BRAND);r.font.size=Pt(8)
    footer=sec.footer.paragraphs[0];footer.paragraph_format.space_before=Pt(0)
    r=footer.add_run('Uso interno  ·  '+manual['title']+'  |  ');r.font.size=Pt(8)
    field(footer,'PAGE');footer.add_run(' / ');field(footer,'NUMPAGES')
    d.core_properties.title='Manual de usuario · '+manual['title'];d.core_properties.subject=manual['subtitle'];d.core_properties.author='Portal Orange'
    d.core_properties.keywords='Orange, Moodle, manual, '+manual['title']
    d.core_properties.created=datetime(2026,11,6,0,0,tzinfo=timezone.utc);d.core_properties.modified=d.core_properties.created
    d.core_properties.comments='Contenido verificado con Portal Orange 2026110601, revisión '+REV+'. Ejemplos ficticios.'
    p=d.add_paragraph();p.paragraph_format.space_after=Pt(80);r=p.add_run('orange');r.bold=True;r.font.size=Pt(28);r.font.color.rgb=RGBColor.from_string(BRAND)
    p=d.add_paragraph('MANUAL DE USUARIO');p.runs[0].font.color.rgb=RGBColor.from_string(BRAND);p.runs[0].bold=True
    d.add_heading(manual['title'],0);d.add_paragraph(manual['subtitle'])
    d.add_paragraph('Versión 1.0\n'+DATE+'\nPortal Orange 2026110601 · Moodle 4.5.1\nRevisión de código: '+REV)
    d.add_paragraph('Guía paso a paso. Las herramientas visibles dependen de los permisos y de la configuración del ambiente. Los ejemplos son ficticios y no incluyen credenciales reales.')
    d.add_page_break();d.add_heading('Cómo utilizar este manual',1)
    d.add_paragraph('Busca tu tarea en el índice. Los pasos numerados describen el orden de trabajo; los recuadros aclaran límites y comprobaciones. Si no aparece una acción, revisa tu perfil con el responsable del portal.')
    d.add_paragraph('Las rutas se indican con >. Los nombres pueden variar con el idioma de Moodle o los catálogos del proyecto. Las notas sobre permisos describen la configuración acordada; otros roles asignados a la misma cuenta pueden ampliar sus acciones.')
    d.add_heading('Índice de tareas',2)
    for i,ch in enumerate(manual['chapters'],1):d.add_paragraph(f'{i:02d}  {ch["title"]}')
    for i,ch in enumerate(manual['chapters'],1):
        d.add_page_break();d.add_heading(f'{i:02d}  {ch["title"]}',1)
        for block in ch['blocks']:
            typ=block['type']
            if typ=='p':d.add_paragraph(block['text'])
            elif typ in ('steps','bullets'):
                for n,item in enumerate(block['items'],1):
                    p=d.add_paragraph();p.paragraph_format.left_indent=Cm(.55);p.paragraph_format.first_line_indent=Cm(-.55)
                    r=p.add_run(f'{n}. ' if typ=='steps' else '• ');r.bold=True;p.add_run(item)
            elif typ=='table':add_table(d,block['headers'],block['rows'])
            elif typ=='note':
                t=d.add_table(rows=1,cols=1);cell=t.cell(0,0);shade(cell,'F4F4F4');cant_split(t.rows[0]);p=cell.paragraphs[0]
                p.add_run(block['title']+'\n').bold=True;p.add_run(block['text']);d.add_paragraph().paragraph_format.space_after=Pt(0)
            elif typ=='code':
                p=d.add_paragraph();r=p.add_run(block['text']);r.font.name='Courier New';r.font.size=Pt(8.5)
                sh=OxmlElement('w:shd');sh.set(qn('w:fill'),'F4F4F4');p._p.get_or_add_pPr().append(sh)
                p.paragraph_format.space_before=Pt(6);p.paragraph_format.space_after=Pt(12)
            else:raise ValueError(typ)
    path=OUT/(manual['id']+'.docx');d.save(path)
    subprocess.run(['textutil','-convert','doc','-output',str(OUT/(manual['id']+'.doc')),str(path)],check=True)

CSS='''
@page{size:A4;margin:19mm 23mm 20mm}*{box-sizing:border-box}body{margin:0;color:#161616;font:10.5pt/1.35 Arial,Helvetica,sans-serif}p{margin:0 0 9pt;orphans:3;widows:3}h1{font-size:21pt;line-height:1.15;margin:0 0 18pt;break-after:avoid}h2{font-size:14pt;margin:20pt 0 10pt;break-after:avoid}a{color:inherit;text-decoration:none}.cover{break-after:page;padding-top:2mm}.brand{color:#ff7900;font-weight:700;font-size:32pt;margin:0 0 30mm}.eyebrow{font-weight:700;letter-spacing:1.5pt;font-size:10pt;color:#a44200}.cover h1{font-size:32pt;margin:12pt 0 16pt;max-width:150mm}.subtitle{font-size:16pt;line-height:1.3;margin-bottom:22pt}.meta{border-top:4px solid #ff7900;padding-top:13pt;font-size:10pt;line-height:1.7}.cover .guide{margin-top:25pt;max-width:140mm;color:#555}.toc{break-after:page}.toc ol{list-style:none;padding:0}.toc li{padding:5pt 0;border-bottom:1px solid #eee;font-size:10.5pt}.toc .number{display:inline-block;width:24pt;color:#a44200;font-weight:700}.chapter{break-before:page}.chapter:first-child{break-before:auto}li{margin-bottom:8pt;orphans:3;widows:3}ol,ul{padding-left:18pt;margin:10pt 0 14pt}li::marker{font-weight:700}table{border-collapse:collapse;width:100%;margin:12pt 0 16pt;font-size:9.5pt;line-height:1.3;table-layout:fixed}thead{display:table-header-group}th{text-align:left;background:#000;color:white;font-weight:700}td,th{padding:7pt 8pt;vertical-align:top;border:1px solid #dedede;overflow-wrap:anywhere}tbody tr:nth-child(odd){background:#f4f4f4}tr{break-inside:avoid}.note{border-left:4px solid #ff7900;padding:10pt 12pt;background:#f5f5f5;margin:13pt 0;break-inside:avoid}.note strong{display:block;margin-bottom:5pt}.note p{margin:0}pre{font:8.5pt/1.4 'Courier New',monospace;white-space:pre-wrap;overflow-wrap:anywhere;padding:11pt;background:#f4f4f4;border-left:3px solid #aaa;orphans:3;widows:3}section{scroll-margin-top:10px}
'''
def esc(x):return html.escape(str(x))
def html_block(b):
    typ=b['type']
    if typ=='p':return '<p>'+esc(b['text'])+'</p>'
    if typ in ('steps','bullets'):
        tag='ol' if typ=='steps' else 'ul';return '<'+tag+'>'+''.join('<li>'+esc(i)+'</li>' for i in b['items'])+'</'+tag+'>'
    if typ=='note':return '<aside class="note"><strong>'+esc(b['title'])+'</strong><p>'+esc(b['text'])+'</p></aside>'
    if typ=='code':return '<pre>'+esc(b['text'])+'</pre>'
    if typ=='table':return '<table><thead><tr>'+''.join('<th>'+esc(i)+'</th>' for i in b['headers'])+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+esc(i)+'</td>' for i in r)+'</tr>' for r in b['rows'])+'</tbody></table>'
    raise ValueError(typ)
def html_manual(m):
    cover='<section class="cover"><div class="brand">orange</div><p class="eyebrow">MANUAL DE USUARIO</p><h1>'+esc(m['title'])+'</h1><p class="subtitle">'+esc(m['subtitle'])+'</p><div class="meta">Versión 1.0 · '+DATE+'<br>Portal Orange 2026110601 · Moodle 4.5.1<br>Revisión de código: '+REV+'</div><p class="guide">Guía paso a paso. Las herramientas visibles dependen de los permisos y de la configuración del ambiente. Los ejemplos son ficticios y no incluyen credenciales reales.</p></section>'
    toc='<section class="toc"><h1>Cómo utilizar este manual</h1><p>Busca tu tarea en el índice. Los pasos numerados describen el orden de trabajo; los recuadros aclaran límites y comprobaciones. Si no aparece una acción, revisa tu perfil con el responsable del portal.</p><p>Las rutas se indican con &gt;. Los nombres pueden variar con el idioma de Moodle o los catálogos del proyecto. Las notas sobre permisos describen la configuración acordada; otros roles asignados a la misma cuenta pueden ampliar sus acciones.</p><h2>Índice de tareas</h2><ol>'+''.join(f'<li><a href="#chapter-{i}"><span class="number">{i:02d}</span>{esc(ch["title"])}</a></li>' for i,ch in enumerate(m['chapters'],1))+'</ol></section>'
    chapters=''.join(f'<section class="chapter" id="chapter-{i}"><h1>{i:02d}  {esc(ch["title"])}</h1>'+''.join(html_block(b) for b in ch['blocks'])+'</section>' for i,ch in enumerate(m['chapters'],1))
    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Manual · '+esc(m['title'])+'</title><style>'+CSS+'</style></head><body>'+cover+toc+chapters+'</body></html>'

OUT.mkdir(parents=True,exist_ok=True)
for manual in module.manuals:
    word_manual(manual)
    (OUT/'fuentes'/(manual['id']+'.html')).write_text(html_manual(manual),encoding='utf-8')
    print(manual['id']+': DOC y DOCX generados; HTML listo para PDF')
(OUT/'fuentes/manifest.json').write_text(json.dumps([{'id':m['id'],'title':m['title'],'chapters':len(m['chapters'])} for m in module.manuals],ensure_ascii=False,indent=2),encoding='utf-8')
