#!/usr/bin/env python3
"""Gera proposta ou instruções de pagamento de uma reserva.

Dependência de produção: Python 3.10+ e ReportLab 4.x.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import sys
from datetime import datetime
from decimal import Decimal, InvalidOperation
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    HRFlowable,
    Image,
    KeepTogether,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


OLIVE = colors.HexColor("#424735")
DARK = colors.HexColor("#252A22")
MUTED = colors.HexColor("#667064")
CREAM = colors.HexColor("#F6F1E7")
SAND = colors.HexColor("#E6D9C4")
GREEN = colors.HexColor("#DDEBDD")
WHITE = colors.white
LINE = colors.HexColor("#D8D4CA")


def register_fonts() -> tuple[str, str, str]:
    candidates = [
        (
            "C:/Windows/Fonts/arial.ttf",
            "C:/Windows/Fonts/arialbd.ttf",
            "C:/Windows/Fonts/consola.ttf",
        ),
        (
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf",
        ),
        (
            "/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf",
            "/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation2/LiberationMono-Regular.ttf",
        ),
    ]
    for regular, bold, mono in candidates:
        if all(os.path.isfile(path) for path in (regular, bold, mono)):
            pdfmetrics.registerFont(TTFont("ReservationRegular", regular))
            pdfmetrics.registerFont(TTFont("ReservationBold", bold))
            pdfmetrics.registerFont(TTFont("ReservationMono", mono))
            return "ReservationRegular", "ReservationBold", "ReservationMono"
    return "Helvetica", "Helvetica-Bold", "Courier"


def brl(value: object) -> str:
    try:
        amount = Decimal(str(value or "0"))
    except InvalidOperation:
        amount = Decimal("0")
    formatted = f"{abs(amount):,.2f}".replace(",", "_").replace(".", ",").replace("_", ".")
    return ("-R$ " if amount < 0 else "R$ ") + formatted


def date_br(value: object, with_time: bool = False) -> str:
    raw = str(value or "")
    if not raw:
        return "Não informado"
    for pattern in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M", "%Y-%m-%d"):
        try:
            parsed = datetime.strptime(raw, pattern)
            return parsed.strftime("%d/%m/%Y %H:%M" if with_time or " " in raw or "T" in raw else "%d/%m/%Y")
        except ValueError:
            continue
    return raw


def safe(value: object, fallback: str = "Não informado") -> str:
    text = " ".join(str(value or "").split())
    return escape(text if text else fallback)


def p(value: object, style: ParagraphStyle, fallback: str = "Não informado") -> Paragraph:
    return Paragraph(safe(value, fallback), style)


def section_title(text: str, styles: dict[str, ParagraphStyle]) -> list:
    return [
        Spacer(1, 2.8 * mm),
        Paragraph(escape(text.upper()), styles["section"]),
        Spacer(1, 1 * mm),
    ]


def label_value(label: str, value: object, styles: dict[str, ParagraphStyle]) -> Paragraph:
    return Paragraph(f"<b>{escape(label)}</b><br/>{safe(value)}", styles["cell"])


def two_column_details(rows: list[tuple[tuple[str, object], tuple[str, object]]], styles, width):
    data = [
        [
            label_value(left[0], left[1], styles),
            label_value(right[0], right[1], styles),
        ]
        for left, right in rows
    ]
    table = Table(data, colWidths=[width / 2, width / 2], hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("BOX", (0, 0), (-1, -1), 0.55, LINE),
                ("INNERGRID", (0, 0), (-1, -1), 0.4, LINE),
                ("BACKGROUND", (0, 0), (-1, -1), WHITE),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return table


def price_table(items: list[dict], total: object, styles, width):
    rows = [[Paragraph("<b>Descrição</b>", styles["table_head"]), Paragraph("<b>Valor</b>", styles["table_head"])]]
    for item in items:
        rows.append(
            [
                p(item.get("description"), styles["table_cell"], "Item"),
                Paragraph(escape(brl(item.get("amount"))), styles["money_cell"]),
            ]
        )
    rows.append(
        [
            Paragraph("<b>VALOR TOTAL</b>", styles["table_total"]),
            Paragraph(f"<b>{escape(brl(total))}</b>", styles["money_total"]),
        ]
    )
    table = Table(rows, colWidths=[width * 0.72, width * 0.28], hAlign="LEFT")
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), OLIVE),
                ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
                ("BACKGROUND", (0, -1), (-1, -1), CREAM),
                ("BOX", (0, 0), (-1, -1), 0.6, LINE),
                ("INNERGRID", (0, 0), (-1, -2), 0.35, LINE),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return table


def payment_value_summary(reservation: dict, styles, width):
    values = [
        ("Valor total", reservation.get("total")),
        ("Sinal", reservation.get("signal")),
        ("Saldo", reservation.get("remaining")),
    ]
    cells = [
        Paragraph(
            f"<b>{escape(label)}</b><br/><font size='12'>{escape(brl(value))}</font>",
            styles["cell"],
        )
        for label, value in values
    ]
    table = Table([cells], colWidths=[width / 3] * 3)
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), CREAM),
                ("BOX", (0, 0), (-1, -1), 0.6, LINE),
                ("INNERGRID", (0, 0), (-1, -1), 0.35, LINE),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ]
        )
    )
    return table


def payment_story(payload: dict, styles, width):
    payments = [
        payment
        for payment in payload.get("payments", [])
        if payment.get("status") in ("PENDENTE", "COMPROVANTE_ENVIADO")
    ]
    if not payments:
        raise ValueError("Nenhuma cobrança pendente encontrada.")
    primary = payments[0]
    story = section_title("Pagamento", styles)
    payment_rows = [
        [
            p("Cobrança", styles["table_head"]),
            p("Valor", styles["table_head"]),
            p("Vencimento", styles["table_head"]),
        ]
    ]
    for payment in payments:
        payment_rows.append(
            [
                p(str(payment.get("type", "")).replace("_", " ").title(), styles["table_cell"]),
                Paragraph(escape(brl(payment.get("amount"))), styles["money_cell"]),
                p(date_br(payment.get("due_at"), True), styles["table_cell"]),
            ]
        )
    table = Table(payment_rows, colWidths=[width * 0.32, width * 0.27, width * 0.41])
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), OLIVE),
                ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
                ("BOX", (0, 0), (-1, -1), 0.6, LINE),
                ("INNERGRID", (0, 0), (-1, -1), 0.35, LINE),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 7),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
            ]
        )
    )
    story.extend([table, Spacer(1, 3 * mm)])

    qr_path = payload.get("assets", {}).get("qr_code_path")
    pix = str(primary.get("pix_copy_paste") or "").strip()
    payment_cells = []
    widths = []
    if qr_path and Path(qr_path).is_file():
        image = Image(str(qr_path), width=42 * mm, height=42 * mm)
        payment_cells.append([image])
        widths.append(50 * mm)
    copy_parts = [
        Paragraph("<b>CHAVE PIX / PIX COPIA E COLA</b>", styles["small_label"]),
        Spacer(1, 1.5 * mm),
        Paragraph(escape(pix) if pix else "Utilize o QR Code ao lado.", styles["pix"]),
    ]
    if primary.get("notes"):
        copy_parts.extend(
            [
                Spacer(1, 3 * mm),
                Paragraph("<b>Observações</b>", styles["small_label"]),
                Spacer(1, 1 * mm),
                p(primary.get("notes"), styles["small"]),
            ]
        )
    payment_cells.append(copy_parts)
    widths.append(width - sum(widths))
    payment_box = Table([payment_cells], colWidths=widths, hAlign="LEFT")
    payment_box.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), GREEN),
                ("BOX", (0, 0), (-1, -1), 0.7, colors.HexColor("#AFC8AF")),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 7),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
            ]
        )
    )
    story.extend(
        [
            KeepTogether(payment_box),
            Spacer(1, 3 * mm),
            Paragraph(
                "<b>Importante:</b> a reserva somente será confirmada após a identificação do pagamento. "
                "Confira o favorecido antes de concluir o Pix e envie o comprovante pelo canal de atendimento.",
                styles["notice"],
            ),
        ]
    )
    return story


def generate(payload: dict, output_path: Path) -> None:
    document = payload.get("document", {})
    reservation = payload.get("reservation", {})
    property_data = payload.get("property", {})
    doc_type = document.get("type")
    if doc_type not in ("PROPOSAL", "PAYMENT_REQUEST"):
        raise ValueError("Tipo de documento inválido.")
    if not reservation.get("code") or not reservation.get("customer_name"):
        raise ValueError("Dados essenciais da reserva ausentes.")

    output_path.parent.mkdir(parents=True, exist_ok=True)
    regular, bold, mono = register_fonts()
    sample = getSampleStyleSheet()
    styles = {
        "brand": ParagraphStyle("Brand", parent=sample["Title"], fontName=bold, fontSize=14, leading=17, textColor=OLIVE),
        "brand_meta": ParagraphStyle("BrandMeta", parent=sample["BodyText"], fontName=regular, fontSize=7.5, leading=10, textColor=MUTED),
        "title": ParagraphStyle("Title", parent=sample["Title"], fontName=bold, fontSize=17.5, leading=21, alignment=TA_LEFT, textColor=DARK, spaceAfter=1),
        "subtitle": ParagraphStyle("Subtitle", parent=sample["BodyText"], fontName=regular, fontSize=9.5, leading=13, textColor=MUTED),
        "badge": ParagraphStyle("Badge", parent=sample["BodyText"], fontName=bold, fontSize=8, leading=10, alignment=TA_CENTER, textColor=OLIVE),
        "section": ParagraphStyle("Section", parent=sample["Heading2"], fontName=bold, fontSize=8.5, leading=11, textColor=OLIVE, letterSpacing=0.7, keepWithNext=True),
        "cell": ParagraphStyle("Cell", parent=sample["BodyText"], fontName=regular, fontSize=8.5, leading=11.5, textColor=DARK),
        "table_head": ParagraphStyle("TableHead", parent=sample["BodyText"], fontName=bold, fontSize=8, leading=10, textColor=WHITE),
        "table_cell": ParagraphStyle("TableCell", parent=sample["BodyText"], fontName=regular, fontSize=8.5, leading=11, textColor=DARK),
        "money_cell": ParagraphStyle("MoneyCell", parent=sample["BodyText"], fontName=regular, fontSize=8.5, leading=11, alignment=TA_RIGHT, textColor=DARK),
        "table_total": ParagraphStyle("TableTotal", parent=sample["BodyText"], fontName=bold, fontSize=9.5, leading=12, textColor=DARK),
        "money_total": ParagraphStyle("MoneyTotal", parent=sample["BodyText"], fontName=bold, fontSize=11, leading=13, alignment=TA_RIGHT, textColor=OLIVE),
        "body": ParagraphStyle("Body", parent=sample["BodyText"], fontName=regular, fontSize=8.7, leading=12.3, textColor=DARK),
        "small": ParagraphStyle("Small", parent=sample["BodyText"], fontName=regular, fontSize=7.6, leading=10.5, textColor=DARK),
        "small_label": ParagraphStyle("SmallLabel", parent=sample["BodyText"], fontName=bold, fontSize=7.6, leading=10, textColor=OLIVE),
        "pix": ParagraphStyle("Pix", parent=sample["Code"], fontName=mono, fontSize=6.6, leading=9, textColor=DARK, splitLongWords=True),
        "notice": ParagraphStyle("Notice", parent=sample["BodyText"], fontName=regular, fontSize=8.2, leading=11.5, textColor=DARK, backColor=CREAM, borderColor=SAND, borderWidth=0.7, borderPadding=9),
        "footer": ParagraphStyle("Footer", parent=sample["BodyText"], fontName=regular, fontSize=7, leading=9, textColor=MUTED, alignment=TA_CENTER),
    }

    left = right = 18 * mm
    top = 17 * mm
    bottom = 16 * mm
    width = A4[0] - left - right
    title = "Pedido de reserva" if doc_type == "PROPOSAL" else "Instruções de pagamento"
    doc = SimpleDocTemplate(
        str(output_path),
        pagesize=A4,
        leftMargin=left,
        rightMargin=right,
        topMargin=top,
        bottomMargin=bottom,
        title=f"{title} {reservation['code']}",
        author=str(property_data.get("name") or "Refúgio do Cuscuzeiro"),
        subject=f"{title} para {reservation['customer_name']}",
        creator="Refúgio ReservationDocumentService",
        pageCompression=1,
    )

    def footer(canvas, current_doc):
        canvas.saveState()
        canvas.setStrokeColor(LINE)
        canvas.line(left, 12 * mm, A4[0] - right, 12 * mm)
        canvas.setFont(regular, 6.8)
        canvas.setFillColor(MUTED)
        canvas.drawString(left, 7.5 * mm, f"{reservation['code']} - documento v{document.get('version', 1)}")
        canvas.drawRightString(A4[0] - right, 7.5 * mm, f"Página {current_doc.page}")
        canvas.restoreState()

    logo_path = payload.get("assets", {}).get("logo_path")
    if logo_path and Path(logo_path).is_file():
        logo = Image(str(logo_path), width=21 * mm, height=21 * mm)
        logo._restrictSize(21 * mm, 21 * mm)
    else:
        logo = Paragraph("R", ParagraphStyle("Mark", fontName=bold, fontSize=26, alignment=TA_CENTER, textColor=OLIVE))
    contact = " | ".join(
        part
        for part in [
            str(property_data.get("city") or ""),
            str(property_data.get("state") or ""),
            str(property_data.get("phone") or ""),
            str(property_data.get("email") or ""),
        ]
        if part
    )
    header = Table(
        [
            [
                logo,
                [
                    p(property_data.get("name"), styles["brand"], "Refúgio do Cuscuzeiro"),
                    p(contact, styles["brand_meta"], "Atendimento de reservas"),
                ],
                Paragraph(f"<b>{escape(reservation['code'])}</b><br/>{date_br(document.get('issued_at'), True)}", styles["badge"]),
            ]
        ],
        colWidths=[26 * mm, width - 66 * mm, 40 * mm],
    )
    header.setStyle(
        TableStyle(
            [
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("ALIGN", (-1, 0), (-1, -1), "RIGHT"),
                ("LEFTPADDING", (0, 0), (-1, -1), 0),
                ("RIGHTPADDING", (0, 0), (-1, -1), 0),
                ("TOPPADDING", (0, 0), (-1, -1), 0),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ]
        )
    )

    subtitle = (
        "Proposta comercial para análise do cliente. Este documento ainda não confirma nem bloqueia a reserva."
        if doc_type == "PROPOSAL"
        else "Dados para pagamento da hospedagem. As datas permanecem retidas somente até o vencimento informado."
    )
    story = [
        header,
        Spacer(1, 2.5 * mm),
        HRFlowable(width="100%", thickness=1, color=SAND),
        Spacer(1, 3 * mm),
        Paragraph(title, styles["title"]),
        Paragraph(subtitle, styles["subtitle"]),
    ]
    story.extend(section_title("Cliente e estadia", styles))
    story.append(
        two_column_details(
            [
                (("Cliente", reservation.get("customer_name")), ("WhatsApp", reservation.get("customer_phone"))),
                (
                    ("Check-in", f"{date_br(reservation.get('checkin'))} {property_data.get('checkin_time') or ''}".strip()),
                    ("Check-out", f"{date_br(reservation.get('checkout'))} {property_data.get('checkout_time') or ''}".strip()),
                ),
                (
                    ("Período", f"{reservation.get('nights', 0)} diária(s)"),
                    (
                        "Hóspedes",
                        f"{reservation.get('adults', 0)} adulto(s), {reservation.get('children', 0)} criança(s)",
                    ),
                ),
            ],
            styles,
            width,
        )
    )
    story.extend(section_title("Valores", styles))
    story.append(
        price_table(payload.get("pricing_items", []), reservation.get("total"), styles, width)
        if doc_type == "PROPOSAL"
        else payment_value_summary(reservation, styles, width)
    )

    if doc_type == "PAYMENT_REQUEST":
        story.extend(payment_story(payload, styles, width))
    else:
        story.extend(
            [
                Spacer(1, 3 * mm),
                Paragraph(
                    f"<b>Validade da proposta:</b> {escape(date_br(document.get('valid_until'), True))}. "
                    "Para seguir, responda ao atendimento confirmando o interesse. A disponibilidade será verificada novamente antes da emissão da cobrança.",
                    styles["notice"],
                ),
            ]
        )

    notes = reservation.get("commercial_notes")
    cancellation = reservation.get("cancellation_policy")
    if notes or cancellation:
        story.extend(section_title("Condições", styles))
        if notes:
            story.extend([Paragraph("<b>Observações comerciais</b>", styles["small_label"]), Spacer(1, .7 * mm), p(notes, styles["body"]), Spacer(1, 1.8 * mm)])
        if cancellation:
            story.extend([Paragraph("<b>Política de cancelamento</b>", styles["small_label"]), Spacer(1, .7 * mm), p(cancellation, styles["body"])])

    if doc_type == "PROPOSAL":
        story.extend(
            [
                Spacer(1, 4 * mm),
                HRFlowable(width="100%", thickness=0.6, color=LINE),
                Spacer(1, 3 * mm),
                Paragraph(
                    "Documento emitido eletronicamente pelo painel administrativo. Em caso de divergência, fale com o atendimento antes de efetuar qualquer pagamento.",
                    styles["footer"],
                ),
            ]
        )
    doc.build(story, onFirstPage=footer, onLaterPages=footer)
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
        source = Path(args.input)
        if source.stat().st_size > 5 * 1024 * 1024:
            raise ValueError("Payload excede 5 MB.")
        payload = json.loads(source.read_text(encoding="utf-8"))
        output = Path(args.output).resolve()
        generate(payload, output)
        print(json.dumps({"ok": True, "sha256": hashlib.sha256(output.read_bytes()).hexdigest()}))
        return 0
    except Exception as exc:
        print(f"Falha ao gerar PDF de reserva: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
