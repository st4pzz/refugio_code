#!/usr/bin/env python3
"""Gera um contrato fictício somente para QA visual do renderer."""
from __future__ import annotations

import hashlib
import re
from pathlib import Path

from generate_contract_pdf import generate


ROOT = Path(__file__).resolve().parents[1]
template = (ROOT / "resources/contracts/contract-v2-suggested.html").read_text(encoding="utf-8")
values = {
    "owner_full_name": "Cristina Ferreira (DADO FICTÍCIO DE HOMOLOGAÇÃO)",
    "owner_nationality": "brasileira",
    "owner_marital_status": "casada",
    "owner_profession": "administradora",
    "owner_rg": "00.000.000-0",
    "owner_cpf": "000.000.000-00",
    "owner_address": "Rua de Homologação, 100, Analândia/SP",
    "owner_phone": "+55 16 90000-0000",
    "owner_email": "homologacao@example.test",
    "guest_full_name": "Marina Oliveira (DADO FICTÍCIO)",
    "guest_nationality": "brasileira",
    "guest_marital_status": "solteira",
    "guest_profession": "arquiteta",
    "guest_rg": "11.111.111-1",
    "guest_cpf": "111.111.111-11",
    "guest_address": "Avenida de Teste, 200, Campinas/SP",
    "guest_phone": "+55 19 90000-0000",
    "guest_email": "hospede@example.test",
    "property_name": "Refúgio do Cuscuzeiro",
    "property_full_address": "Endereço fictício para homologação, Analândia/SP, 13550-000",
    "checkin_at": "10/08/2026 às 15:00",
    "checkout_at": "13/08/2026 às 11:00",
    "number_of_nights": "3",
    "total_amount": "R$ 3.280,00",
    "rental_amount": "R$ 2.400,00",
    "cleaning_fee": "R$ 280,00",
    "extra_guest_amount": "R$ 600,00",
    "pet_fee_amount": "R$ 0,00",
    "other_charges": "R$ 0,00",
    "deposit_amount": "R$ 1.640,00",
    "deposit_due_at": "24/07/2026 às 18:00",
    "balance_amount": "R$ 1.640,00",
    "balance_due_at": "05/08/2026 às 18:00",
    "payment_method": "Pix",
    "unauthorized_visitor_fee": "R$ 250,00",
    "security_deposit_description": "caução de R$ 800,00, por Pix antes da entrega do acesso",
    "cancellation_policy": "30 dias ou mais: devolução de 90%; de 15 a 29 dias: devolução de 50%; menos de 15 dias: sem devolução, ressalvadas hipóteses legais ou acordo; se o período for novamente locado, restituição do líquido recuperado, descontados 10% e valores já devolvidos.",
    "quiet_hours": "22:00 às 08:00",
    "pets_policy": "Não permitidos nesta reserva.",
    "contract_forum_city": "Rio Claro/SP (FICTÍCIO — REVISAR)",
    "contract_city": "Analândia/SP",
    "contract_date_long": "22 de julho de 2026",
    "checkin_time": "15:00",
    "checkout_time": "11:00",
    "emergency_contact": "+55 16 90000-0000 (FICTÍCIO)",
    "security_deposit_amount": "R$ 800,00",
    "security_deductions": "R$ 0,00",
    "security_balance": "R$ 800,00",
    "security_return_date": "até 18/08/2026",
    "contract_number": "CTR-HOMOLOGACAO-0001",
    "contract_version": "1",
    "document_hash": "__DOCUMENT_HASH__",
}
inventory = [
    ("Chaves, controles e tags", "2", "Bom", "R$ 350,00", "Fotos INV-001 a INV-003"),
    ("Sala — sofás, mesas e televisão", "1 conjunto", "Bom", "R$ 8.000,00", "Foto INV-010"),
    ("Cozinha e eletrodomésticos", "1 conjunto", "Bom", "R$ 12.000,00", "Fotos INV-020 a INV-026"),
    ("Quartos e enxoval", "4 conjuntos", "Bom", "R$ 15.000,00", "Fotos INV-030 a INV-044"),
    ("Piscina e área externa", "1 conjunto", "Bom", "R$ 20.000,00", "Fotos INV-050 a INV-060"),
]
values["inventory_rows"] = "".join("<tr>" + "".join(f"<td>{cell}</td>" for cell in row) + "</tr>" for row in inventory)
guests = [
    (1, "Marina Oliveira", "111.111.111-11", "10/02/1990", "+55 19 90000-0000"),
    (2, "Rafael Oliveira", "222.222.222-22", "21/04/1989", "+55 19 90000-0001"),
    (3, "Clara Oliveira", "Documento 333", "05/06/2015", ""),
    (4, "Tiago Oliveira", "Documento 444", "12/09/2018", ""),
] + [(index, "", "", "", "") for index in range(5, 11)]
values["guest_rows"] = "".join("<tr>" + "".join(f"<td>{cell}</td>" for cell in row) + "</tr>" for row in guests)
values["vehicle_rows"] = "<tr><td>Marina Oliveira</td><td>Veículo de teste</td><td>Prata</td><td>ABC1D23</td></tr>"

rendered = template
for key, value in values.items():
    rendered = rendered.replace("{{" + key + "}}", str(value))
if re.search(r"\{\{[^}]+\}\}", rendered):
    missing = sorted(set(re.findall(r"\{\{\s*([^} ]+)\s*\}\}", rendered)))
    raise RuntimeError(f"Variáveis de exemplo ausentes: {', '.join(missing)}")
document_hash = hashlib.sha256(rendered.encode("utf-8")).hexdigest()
rendered = rendered.replace("__DOCUMENT_HASH__", document_hash)
output = ROOT / "output/pdf/contrato-exemplo-refugio.pdf"
generate({"title": "Contrato fictício de homologação", "html": rendered, "document_hash": document_hash}, output)
print(output)
