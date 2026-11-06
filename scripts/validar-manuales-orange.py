#!/usr/bin/env python3
"""Check that every reviewed content fragment survives DOC/DOCX/PDF generation."""
import sys
sys.dont_write_bytecode=True
from pathlib import Path
import importlib.util,re,subprocess,json,hashlib
from zipfile import ZipFile
from pypdf import PdfReader
from docx import Document
ROOT=Path(__file__).resolve().parents[1];OUT=ROOT/'docs/manuales/20261106'
spec=importlib.util.spec_from_file_location('orange_manual_content',OUT/'fuentes/contenido_manuales.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def normalized(s):return re.sub(r'\s+','',s).replace('\u200b','')
def fragments(manual):
 for chapter in manual['chapters']:
  yield chapter['title']
  for block in chapter['blocks']:
   kind=block['type']
   if kind=='p' or kind=='code':yield block['text']
   elif kind in ['steps','bullets']:yield from block['items']
   elif kind=='note':yield block['title'];yield block['text']
   elif kind=='table':
    yield from block['headers']
    for row in block['rows']:yield from row
checks=[]
for manual in m.manuals:
 stem=manual['id'];pdf=OUT/(stem+'.pdf');reader=PdfReader(pdf)
 pdftext=normalized('\n'.join(p.extract_text() or '' for p in reader.pages))
 assert all(len(p.extract_text() or '')>150 for p in reader.pages),(stem,'empty page')
 assert all(abs(float(p.mediabox.width)-595.28)<2 for p in reader.pages),(stem,'not A4')
 doc=OUT/(stem+'.doc');assert doc.read_bytes()[:8]==bytes.fromhex('d0cf11e0a1b11ae1'),(stem,'not a binary DOC')
 doctext=normalized(subprocess.check_output(['textutil','-convert','txt','-stdout',str(doc)],text=True))
 document=Document(OUT/(stem+'.docx'))
 docxtext=normalized('\n'.join([p.text for p in document.paragraphs]+[c.text for t in document.tables for r in t.rows for c in r.cells]))
 samples=list(fragments(manual))
 for sample in samples:
  for fmt,text in [('PDF',pdftext),('DOC',doctext),('DOCX',docxtext)]:
   assert normalized(sample) in text,(stem,fmt,sample[:100])
 checks.append({'manual':manual['title'],'file':stem,'pages_pdf':len(reader.pages),'content_fragments_verified':len(samples),'docx_tables':len(document.tables)})
 print(stem+': contenido completo en DOC, DOCX y PDF; '+str(len(reader.pages))+' páginas PDF')
(OUT/'fuentes/validacion.json').write_text(json.dumps({'review_date':'2026-11-06','code_revision':'48461c2','checks':checks},ensure_ascii=False,indent=2),encoding='utf8')
