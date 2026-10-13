#!/usr/bin/env python3
"""Extract only the populated matrix cells without third-party dependencies.

Usage: python3 scripts/extract-library-matrix.py /path/to/Matriz.xlsx
The output is private working data, excluded from Git.
"""
import json
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

source = Path(sys.argv[1])
ns = {"s": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
with zipfile.ZipFile(source) as workbook:
    strings = ["".join(t.text or "" for t in entry.findall(".//s:t", ns))
               for entry in ET.fromstring(workbook.read("xl/sharedStrings.xml")).findall("s:si", ns)]
    rows = []
    for row in ET.fromstring(workbook.read("xl/worksheets/sheet1.xml")).findall(".//s:row", ns):
        values = {}
        for cell in row.findall("s:c", ns):
            value = cell.find("s:v", ns)
            if value is None or value.text is None:
                continue
            text = strings[int(value.text)] if cell.get("t") == "s" else value.text
            if text.strip():
                values[cell.get("r")] = " ".join(text.split())
        if values:
            rows.append(values)
output = Path(__file__).resolve().parent.parent / "assets/library-matrix-rows.json"
output.parent.mkdir(parents=True, exist_ok=True)
output.write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"{len(rows)} filas extraídas en {output}")
