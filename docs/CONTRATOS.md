# Contratos, PDF e assinatura

O PDF fornecido tem 12 páginas. A primeira é orientação editorial e foi excluída do documento assinável. O texto-base está arquivado como versão 1 `ARCHIVED`; a proposta dinâmica é versão 2 `PENDING_APPROVAL`. O original nunca é substituído silenciosamente. A versão 2 limita o Anexo II a exatamente dez hóspedes e exige aprovação em `/admin/contratos`.

Pontos obrigatórios para revisão administrativa/jurídica: cancelamento; multa compensatória de 20%; foro; caução; pets; silêncio; valor de visitante não autorizado; regras municipais e classificação do imóvel. A política recebida foi preservada e `CANCELLATION_POLICY_APPROVED` inicia falsa.

## Imutabilidade

`contract_template_versions` guarda status, resumo, notas, hash do conteúdo e hash do PDF-fonte. Cada `reservation_contracts` contém snapshot de variáveis, HTML, versão e SHA-256. Regenerar cria nova versão; a anterior passa a `SUPERSEDED`. `contract_documents` registra caminho, tamanho e hash. Placeholders ausentes ou editoriais `«…»` impedem geração.

## PDF

`ContractPdfService` renderiza o snapshot HTML com `dompdf/dompdf` dentro do próprio processo PHP. Instale o `composer.lock` com `composer2 install --no-dev --optimize-autoloader`; não há variável de ambiente nem runtime Python. O renderizador bloqueia conteúdo remoto, PHP e JavaScript, valida assinatura `%PDF-`, tamanho e SHA-256 antes de registrar. O PDF inclui hash e paginação no rodapé. Faça QA renderizando todas as páginas com Poppler.

Todo PDF termina em uma folha exclusiva de assinaturas, com quebra de página obrigatória, identificação e CPF do locador e do locatário, áreas livres para posicionamento das assinaturas eletrônicas e dois campos opcionais de testemunhas. Essa folha facilita o envio manual ao Gov.br ou a plataforma equivalente; não representa integração automática com a API do Gov.br. Ao regenerar o PDF de um contrato existente, os dados são recuperados do snapshot imutável da própria versão contratual.

## Assinatura local auditável

`SignatureProviderInterface` permite provedor futuro. O provedor local usa código de seis dígitos, HMAC-SHA-256, validade de 15 minutos, cinco tentativas e uso único. Registra nome, CPF, canal, IP, user-agent, horário, texto aceito, hash do documento e eventos. CPF deve coincidir com o signatário. O sistema descreve fatos de autenticação e integridade, sem alegar categoria jurídica específica. Configure `APP_KEY` com pelo menos 32 caracteres.
