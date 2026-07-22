#!/usr/bin/env python3
"""Gera PDF determinístico do snapshot HTML de um contrato.

Dependência de produção: Python 3.10+ e ReportLab 4.x.
O processo PHP chama este script com caminhos explícitos, sem shell.
"""

from __future__ import annotations

import argparse
import hashlib
import html
import json
import os
import re
import sys
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


def register_fonts() -> tuple[str, str]:
    candidates = [
        ("C:/Windows/Fonts/arial.ttf", "C:/Windows/Fonts/arialbd.ttf"),
        ("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"),
        ("/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf", "/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf"),
    ]
    for regular, bold in candidates:
        if os.path.isfile(regular) and os.path.isfile(bold):
            pdfmetrics.registerFont(TTFont("ContractRegular", regular))
            pdfmetrics.registerFont(TTFont("ContractBold", bold))
            return "ContractRegular", "ContractBold"
    return "Helvetica", "Helvetica-Bold"


def plain(fragment: str) -> str:
    fragment = re.sub(r"<br\s*/?>", "\n", fragment, flags=re.I)
    fragment = re.sub(r"<[^>]+>", "", fragment)
    return " ".join(html.unescape(fragment).split())


def table_story(fragment: str, styles: dict[str, ParagraphStyle], available: float):
    rows = []
    for row_fragment in re.findall(r"<tr[^>]*>(.*?)</tr>", fragment, flags=re.I | re.S):
        cells = re.findall(r"<(?:td|th)[^>]*>(.*?)</(?:td|th)>", row_fragment, flags=re.I | re.S)
        if cells:
            rows.append([Paragraph(escape(plain(cell) or " "), styles["cell"]) for cell in cells])
    if not rows:
        return None
    column_count = max(len(row) for row in rows)
    for row in rows:
        row.extend([Paragraph(" ", styles["cell"]) for _ in range(column_count - len(row))])
    if column_count == 5:
        widths = [available * 0.28, available * 0.09, available * 0.18, available * 0.16, available * 0.29]
    elif column_count == 4:
        widths = [available * 0.30, available * 0.30, available * 0.16, available * 0.24]
    else:
        widths = [available / column_count] * column_count
    table = Table(rows, colWidths=widths, repeatRows=1, hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#E9E2D2")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.HexColor("#243229")),
                ("FONTNAME", (0, 0), (-1, 0), styles["cell"].fontName),
                ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#AAA28F")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 3),
                ("RIGHTPADDING", (0, 0), (-1, -1), 3),
                ("TOPPADDING", (0, 0), (-1, -1), 3),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ]
        )
    )
    return table


def build_story(contract_html: str, styles: dict[str, ParagraphStyle], available: float):
    story = []
    token_pattern = re.compile(r"<(h1|h2|h3|p|table)(?:\s[^>]*)?>(.*?)</\1>", re.I | re.S)
    for match in token_pattern.finditer(contract_html):
        tag = match.group(1).lower()
        content = match.group(2)
        if tag == "table":
            table = table_story(content, styles, available)
            if table:
                story.extend([table, Spacer(1, 4 * mm)])
            continue
        text = plain(content)
        if not text:
            continue
        if tag == "h1":
            story.extend([Paragraph(escape(text), styles["h1"]), Spacer(1, 3 * mm)])
        elif tag == "h2":
            if text.startswith("ANEXO"):
                story.append(PageBreak())
            story.extend([Paragraph(escape(text), styles["h2"]), Spacer(1, 1.5 * mm)])
        elif tag == "h3":
            story.append(Paragraph(escape(text), styles["h3"]))
        else:
            paragraph_style = styles["document_hash"] if "document-hash" in match.group(0) else styles["body"]
            story.extend([Paragraph(escape(text), paragraph_style), Spacer(1, 1.4 * mm)])
    signature = Table(
        [
            [Paragraph("__________________________________<br/>LOCADOR(A)", styles["signature"]), Paragraph("__________________________________<br/>LOCATÁRIO(A)", styles["signature"])],
            [Paragraph("__________________________________<br/>TESTEMUNHA 1", styles["signature"]), Paragraph("__________________________________<br/>TESTEMUNHA 2", styles["signature"])],
        ],
        colWidths=[available / 2, available / 2],
        rowHeights=[24 * mm, 24 * mm],
    )
    signature.setStyle(TableStyle([("VALIGN", (0, 0), (-1, -1), "BOTTOM"), ("ALIGN", (0, 0), (-1, -1), "CENTER")]))
    story.extend([Spacer(1, 8 * mm), KeepTogether(signature)])
    return story


def generate(payload: dict, output_path: Path) -> None:
    contract_html = str(payload.get("html", ""))
    if not contract_html.strip():
        raise ValueError("Snapshot HTML vazio.")
    if "{{" in contract_html or re.search(r"«[^»]+»", contract_html):
        raise ValueError("O documento contém placeholders não resolvidos.")
    document_hash = str(payload.get("document_hash", ""))
    if not re.fullmatch(r"[a-f0-9]{64}", document_hash):
        raise ValueError("Hash documental inválido.")

    output_path.parent.mkdir(parents=True, exist_ok=True)
    regular, bold = register_fonts()
    sample = getSampleStyleSheet()
    styles = {
        "h1": ParagraphStyle("ContractH1", parent=sample["Title"], fontName=bold, fontSize=15, leading=18, alignment=TA_CENTER, textColor=colors.HexColor("#243229"), spaceAfter=4),
        "h2": ParagraphStyle("ContractH2", parent=sample["Heading2"], fontName=bold, fontSize=10.5, leading=13, textColor=colors.HexColor("#31493A"), spaceBefore=5, keepWithNext=True),
        "h3": ParagraphStyle("ContractH3", parent=sample["Heading3"], fontName=bold, fontSize=9.5, leading=12, textColor=colors.HexColor("#31493A"), keepWithNext=True),
        "body": ParagraphStyle("ContractBody", parent=sample["BodyText"], fontName=regular, fontSize=8.8, leading=12, alignment=TA_JUSTIFY, textColor=colors.HexColor("#1F2621"), splitLongWords=True),
        "cell": ParagraphStyle("ContractCell", parent=sample["BodyText"], fontName=regular, fontSize=7, leading=8.6, alignment=TA_LEFT),
        "signature": ParagraphStyle("ContractSignature", parent=sample["BodyText"], fontName=regular, fontSize=8, leading=11, alignment=TA_CENTER),
        "document_hash": ParagraphStyle("ContractDocumentHash", parent=sample["BodyText"], fontName=regular, fontSize=6.8, leading=9, alignment=TA_LEFT, textColor=colors.HexColor("#56645A"), splitLongWords=True),
    }
    left = right = 18 * mm
    top = 22 * mm
    bottom = 18 * mm
    doc = SimpleDocTemplate(
        str(output_path),
        pagesize=A4,
        leftMargin=left,
        rightMargin=right,
        topMargin=top,
        bottomMargin=bottom,
        title=str(payload.get("title", "Contrato de locação temporária")),
        author="Refúgio do Cuscuzeiro",
        subject=f"Documento SHA-256 {document_hash}",
        creator="Refúgio ContractPdfService",
        pageCompression=1,
    )
    available = A4[0] - left - right

    def page(canvas, document):
        canvas.saveState()
        canvas.setFont(regular, 7)
        canvas.setFillColor(colors.HexColor("#56645A"))
        canvas.drawString(left, A4[1] - 11 * mm, "REFÚGIO DO CUSCUZEIRO · CONTRATO DE LOCAÇÃO TEMPORÁRIA")
        canvas.drawRightString(A4[0] - right, 9 * mm, f"Página {document.page}")
        canvas.setFont(regular, 5.6)
        canvas.drawString(left, 9 * mm, f"SHA-256 do snapshot: {document_hash}")
        canvas.setStrokeColor(colors.HexColor("#D6CFBF"))
        canvas.line(left, A4[1] - 13 * mm, A4[0] - right, A4[1] - 13 * mm)
        canvas.restoreState()

    story = build_story(contract_html, styles, available)
    doc.build(story, onFirstPage=page, onLaterPages=page)
    if not output_path.is_file() or output_path.stat().st_size < 1000:
        raise RuntimeError("PDF não foi produzido corretamente.")
    if output_path.read_bytes()[:5] != b"%PDF-":
        raise RuntimeError("Saída não é um PDF.")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    try:
        payload_bytes = Path(args.input).read_bytes()
        if len(payload_bytes) > 10 * 1024 * 1024:
            raise ValueError("Payload excede 10 MB.")
        payload = json.loads(payload_bytes.decode("utf-8"))
        generate(payload, Path(args.output).resolve())
        print(json.dumps({"ok": True, "sha256": hashlib.sha256(Path(args.output).read_bytes()).hexdigest()}, ensure_ascii=False))
        return 0
    except Exception as exc:  # saída consumida pelo serviço PHP
        print(f"Falha ao gerar contrato: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
