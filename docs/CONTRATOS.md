# Contratos, PDF e assinatura

O PDF fornecido tem 12 páginas. A primeira é orientação editorial e foi excluída do documento assinável. O texto-base está arquivado como versão 1 `ARCHIVED`; a proposta dinâmica é versão 2 `PENDING_APPROVAL`. O original nunca é substituído silenciosamente. A versão 2 limita o Anexo II a exatamente dez hóspedes e exige aprovação em `/admin/contratos`.

Pontos obrigatórios para revisão administrativa/jurídica: cancelamento; multa compensatória de 20%; foro; caução; pets; silêncio; valor de visitante não autorizado; regras municipais e classificação do imóvel. A política recebida foi preservada e `CANCELLATION_POLICY_APPROVED` inicia falsa.

## Imutabilidade

`contract_template_versions` guarda status, resumo, notas, hash do conteúdo e hash do PDF-fonte. Cada `reservation_contracts` contém snapshot de variáveis, HTML, versão e SHA-256. Regenerar cria nova versão; a anterior passa a `SUPERSEDED`. `contract_documents` registra caminho, tamanho e hash. Placeholders ausentes ou editoriais `«…»` impedem geração.

## PDF

`ContractPdfService` invoca `scripts/generate_contract_pdf.py` sem shell. Produção requer Python 3.10+ e ReportLab 4.x; configure `PDF_PYTHON_BINARY`. O processo valida assinatura `%PDF-`, tamanho e SHA-256 antes de registrar. O PDF inclui hash no rodapé. Faça QA renderizando todas as páginas com Poppler.

## Assinatura local auditável

`SignatureProviderInterface` permite provedor futuro. O provedor local usa código de seis dígitos, HMAC-SHA-256, validade de 15 minutos, cinco tentativas e uso único. Registra nome, CPF, canal, IP, user-agent, horário, texto aceito, hash do documento e eventos. CPF deve coincidir com o signatário. O sistema descreve fatos de autenticação e integridade, sem alegar categoria jurídica específica. Configure `APP_KEY` com pelo menos 32 caracteres.
