#!/usr/bin/env python3
"""Generate the installation manual as matching Word and print HTML."""
import sys
sys.dont_write_bytecode = True
from pathlib import Path
from datetime import datetime, timezone
import importlib.util, html, json, argparse
from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.image.image import Image

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/manuales/20261112/instalacion'
spec = importlib.util.spec_from_file_location('contenido', OUT/'fuentes/contenido.py')
content = importlib.util.module_from_spec(spec)
spec.loader.exec_module(content)

def shade(cell,fill):
    element=OxmlElement('w:shd');element.set(qn('w:fill'),fill);cell._tc.get_or_add_tcPr().append(element)

def field(paragraph,name):
    element=OxmlElement('w:fldSimple');element.set(qn('w:instr'),name);paragraph._p.append(element)

def word_table(doc, block):
    t=doc.add_table(rows=1, cols=len(block['headers']));t.style='Table Grid'
    repeat=OxmlElement('w:tblHeader');t.rows[0]._tr.get_or_add_trPr().append(repeat)
    for cell,text in zip(t.rows[0].cells,block['headers']):
        shade(cell,'202020');r=cell.paragraphs[0].add_run(text);r.bold=True;r.font.color.rgb=RGBColor(255,255,255)
    for i,row in enumerate(block['rows']):
        cells=t.add_row().cells
        for cell,text in zip(cells,row):
            cell.text=text
            if i%2==0:shade(cell,'F5F5F5')
    for row in t.rows:
        row._tr.get_or_add_trPr().append(OxmlElement('w:cantSplit'))
        for cell in row.cells:
            for p in cell.paragraphs:
                p.paragraph_format.space_after=Pt(5);p.paragraph_format.space_before=Pt(5)
                for r in p.runs:r.font.size=Pt(9)
    doc.add_paragraph().paragraph_format.space_after=Pt(0)

def word_block(doc,b):
    kind=b['type']
    if kind=='p':
        paragraph=doc.add_paragraph(b['text'])
        paragraph.paragraph_format.keep_with_next=b.get('keep',False)
    elif kind=='steps':
        for n,item in enumerate(b['items'],1):
            p=doc.add_paragraph();p.paragraph_format.left_indent=Cm(.6);p.paragraph_format.first_line_indent=Cm(-.6)
            p.add_run(str(n)+'. ').bold=True;p.add_run(item)
    elif kind=='table':word_table(doc,b)
    elif kind=='note':
        p=doc.add_paragraph();p.paragraph_format.keep_with_next=True
        r=p.add_run(b['title']);r.bold=True;r.font.color.rgb=RGBColor.from_string('A44200')
        doc.add_paragraph(b['text'])
    elif kind=='code':
        paragraph=doc.add_paragraph();paragraph.paragraph_format.keep_together=True
        paragraph.paragraph_format.space_before=Pt(5);paragraph.paragraph_format.space_after=Pt(10)
        run=paragraph.add_run(b['text']);run.font.name='Courier New';run.font.size=Pt(8)
        bg=OxmlElement('w:shd');bg.set(qn('w:fill'),'F4F4F4');paragraph._p.get_or_add_pPr().append(bg)
    elif kind=='image':
        path=OUT/'imagenes'/b['file'];picture=Image.from_file(str(path))
        scale=min(Cm(16.2)/picture.width,Cm(b.get('height',98)/10)/picture.height)
        p=doc.add_paragraph();p.alignment=1;p.paragraph_format.keep_with_next=True
        p.add_run().add_picture(str(path),width=int(picture.width*scale),height=int(picture.height*scale))
        caption=doc.add_paragraph(b['caption']);caption.alignment=1
        for r in caption.runs:r.italic=True;r.font.size=Pt(8.5);r.font.color.rgb=RGBColor.from_string('555555')
    else:raise ValueError(kind)

def word_manual(m):
    d=Document();sec=d.sections[0];sec.page_width=Cm(21);sec.page_height=Cm(29.7)
    sec.top_margin=Cm(1.7);sec.bottom_margin=Cm(1.7);sec.left_margin=Cm(2.2);sec.right_margin=Cm(2.2)
    sec.header_distance=Cm(.7);sec.footer_distance=Cm(.7);sec.different_first_page_header_footer=True
    normal=d.styles['Normal'];normal.font.name='Arial';normal.font.size=Pt(10.5)
    normal.paragraph_format.line_spacing=1.1;normal.paragraph_format.space_after=Pt(7)
    for name,size in [('Title',32),('Heading 1',21),('Heading 2',14)]:
        style=d.styles[name];style.font.name='Arial';style.font.size=Pt(size);style.font.bold=True
        style.font.color.rgb=RGBColor.from_string('202020');style.paragraph_format.keep_with_next=True
    h=sec.header.paragraphs[0];h.add_run('ORANGE / GUÍA PASO A PASO').font.color.rgb=RGBColor.from_string('A44200')
    h.runs[0].font.size=Pt(8)
    f=sec.footer.paragraphs[0];f.add_run(m['title']+' · 12/11/2026   |   ');field(f,'PAGE');f.add_run(' / ');field(f,'NUMPAGES')
    for r in f.runs:r.font.size=Pt(8)
    d.core_properties.title=m['title'];d.core_properties.author='Portal Orange'
    d.core_properties.subject='Primera instalación de Portal Orange 2026111100 en Moodle.'
    d.core_properties.created=datetime(2026,11,12,tzinfo=timezone.utc);d.core_properties.modified=d.core_properties.created
    brand=d.add_paragraph();brand.paragraph_format.space_after=Pt(60)
    r=brand.add_run('orange');r.bold=True;r.font.size=Pt(34);r.font.color.rgb=RGBColor.from_string('FF7900')
    d.add_paragraph('GUÍA PARA EMPEZAR DESDE CERO').runs[0].bold=True
    d.add_heading(m['title'],0);d.add_paragraph(m['subtitle'])
    d.add_paragraph('Para: '+m['audience']);d.add_paragraph('Edición nueva · 12 de noviembre de 2026\nPDF y Word con el mismo contenido')
    d.add_page_break();d.add_heading('Índice de tareas',1)
    for i,ch in enumerate(m['chapters'],1):d.add_paragraph(f'{i:02d}. {ch["title"]}')
    for i,ch in enumerate(m['chapters'],1):
        d.add_page_break();d.add_heading(f'{i:02d}. {ch["title"]}',1)
        for b in ch['blocks']:word_block(d,b)
    d.save(OUT/(m['id']+'.docx'))

CSS='''
pre{white-space:pre-wrap;overflow-wrap:anywhere;font:8pt/1.3 'Courier New',monospace;background:#f4f4f4;padding:10pt;border-left:3px solid #999;break-inside:avoid;margin:10pt 0 13pt}
@page{size:A4;margin:17mm 21mm 19mm}*{box-sizing:border-box}body{margin:0;color:#202020;font:10.5pt/1.3 Arial,Helvetica,sans-serif}p{margin:0 0 8pt;orphans:3;widows:3}h1{font-size:21pt;line-height:1.12;margin:0 0 13pt;break-after:avoid}h2{font-size:14pt;margin:16pt 0 8pt;break-after:avoid}a{color:inherit;text-decoration:none}.cover{break-after:page;padding-top:8mm}.brand{font-size:36pt;color:#ff7900;font-weight:bold;margin:0 0 28mm}.eyebrow{color:#a44200;font-weight:bold;font-size:10pt;letter-spacing:1pt}.cover h1{font-size:34pt;line-height:1.1;margin:15pt 0}.subtitle{font-size:17pt;line-height:1.3;margin-bottom:24pt}.meta{border-top:4px solid #ff7900;padding-top:12pt;margin-bottom:20pt}.small{font-size:10pt;color:#555}.toc{break-after:page}.toc ol{list-style:none;padding:0}.toc li{padding:5pt 0;border-bottom:1px solid #eee;margin:0}.num{color:#a44200;font-weight:bold;display:inline-block;width:24pt}.chapter{break-before:page}li{margin-bottom:7pt;orphans:3;widows:3}ol{padding-left:20pt;margin:9pt 0 12pt}li::marker{font-weight:bold}table{border-collapse:collapse;width:100%;table-layout:fixed;font-size:9pt;line-height:1.25;margin:10pt 0 14pt}thead{display:table-header-group}th{background:#202020;color:white;text-align:left}th,td{border:1px solid #ddd;padding:7pt;vertical-align:top;overflow-wrap:anywhere}tr:nth-child(even){background:#f5f5f5}tr{break-inside:avoid}.note{border-left:4px solid #ff7900;background:#f6f6f6;padding:9pt 11pt;margin:11pt 0;break-inside:avoid}.note strong{display:block;color:#8b3900;margin-bottom:4pt}.note p{margin:0}figure{margin:10pt 0 12pt;break-inside:avoid;text-align:center}figure img{display:block;width:100%;height:auto;max-height:98mm;object-fit:contain;border:1px solid #ddd;background:#fafafa}figcaption{margin-top:5pt;font-size:8.5pt;line-height:1.25;color:#555}
'''
def esc(text):return html.escape(str(text))
def html_block(b):
    kind=b['type']
    if kind=='p':return ('<p style="break-after:avoid">' if b.get('keep') else '<p>')+esc(b['text'])+'</p>'
    if kind=='steps':return '<ol>'+''.join('<li>'+esc(x)+'</li>' for x in b['items'])+'</ol>'
    if kind=='note':return '<aside class="note"><strong>'+esc(b['title'])+'</strong><p>'+esc(b['text'])+'</p></aside>'
    if kind=='code':return '<pre>'+esc(b['text'])+'</pre>'
    if kind=='image':return '<figure><img style="max-height:'+str(b.get('height',98))+'mm" src="../imagenes/'+esc(b['file'])+'" alt="'+esc(b['caption'])+'"><figcaption>'+esc(b['caption'])+'</figcaption></figure>'
    if kind=='table':return '<table><thead><tr>'+''.join('<th>'+esc(x)+'</th>' for x in b['headers'])+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+esc(x)+'</td>' for x in row)+'</tr>' for row in b['rows'])+'</tbody></table>'
    raise ValueError(kind)
def html_manual(m):
    cover='<section class="cover"><div class="brand">orange</div><p class="eyebrow">GUÍA PARA EMPEZAR DESDE CERO</p><h1>'+esc(m['title'])+'</h1><p class="subtitle">'+esc(m['subtitle'])+'</p><div class="meta">Para: '+esc(m['audience'])+'<br>Edición nueva · 12 de noviembre de 2026<br>PDF y Word con el mismo contenido</div></section>'
    toc='<section class="toc"><h1>Índice de tareas</h1><ol>'+''.join(f'<li><a href="#tarea-{i}"><span class="num">{i:02d}.</span>{esc(ch["title"])}</a></li>' for i,ch in enumerate(m['chapters'],1))+'</ol></section>'
    chapters=''.join(f'<section class="chapter" id="tarea-{i}"><h1>{i:02d}. {esc(ch["title"])}</h1>'+''.join(html_block(b) for b in ch['blocks'])+'</section>' for i,ch in enumerate(m['chapters'],1))
    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>'+esc(m['title'])+'</title><style>'+CSS+'</style></head><body>'+cover+toc+chapters+'</body></html>'

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--manual', choices=[m['id'] for m in content.manuals])
args = parser.parse_args()
for m in content.manuals:
    if args.manual and m['id'] != args.manual: continue
    word_manual(m)
    (OUT/'fuentes'/(m['id']+'.html')).write_text(html_manual(m),encoding='utf8')
    print(m['id']+': Word y HTML generados')
(OUT/'fuentes/manifest.json').write_text(json.dumps([{'id':m['id'],'title':m['title'],'chapters':len(m['chapters'])} for m in content.manuals],ensure_ascii=False,indent=2),encoding='utf8')

commands=['COMANDOS DE REFERENCIA POR CAPITULO - NO EJECUTAR COMO SCRIPT COMPLETO','Sustituir rutas, usuarios, URL e IDs. Elegir vía A o vía B. Los bloques JSON y crontab no son órdenes de consola.','']
for manual in content.manuals:
    for i,ch in enumerate(manual['chapters'],1):
        blocks=[b['text'] for b in ch['blocks'] if b['type']=='code']
        if blocks:commands += ['# '+str(i).zfill(2)+' '+ch['title'],'']+[x+'\n' for x in blocks]
(OUT/'Comandos_instalacion_Orange.txt').write_text('\n'.join(commands),encoding='utf8')
