# Contratos, PDF e assinatura

O PDF fornecido tem 12 páginas. A primeira é orientação editorial e foi excluída do documento assinável. O texto-base está arquivado como versão 1 `ARCHIVED`; a proposta dinâmica é versão 2 `PENDING_APPROVAL`. O original nunca é substituído silenciosamente. A versão 2 limita o Anexo II a exatamente dez hóspedes e exige aprovação em `/admin/contratos`.

Pontos obrigatórios para revisão administrativa/jurídica: cancelamento; multa compensatória de 20%; foro; caução; pets; silêncio; valor de visitante não autorizado; regras municipais e classificação do imóvel. A política recebida foi preservada e `CANCELLATION_POLICY_APPROVED` inicia falsa.

## Imutabilidade

`contract_template_versions` guarda status, resumo, notas, hash do conteúdo e hash do PDF-fonte. Cada `reservation_contracts` contém snapshot de variáveis, HTML, versão e SHA-256. Regenerar cria nova versão; a anterior passa a `SUPERSEDED`. `contract_documents` registra caminho, tamanho e hash. Placeholders ausentes ou editoriais `«…»` impedem geração.

A geração exige pagamento confirmado e pré-check-in enviado, em análise ou aprovado. Assim, o Anexo II nasce com a lista de hóspedes efetivamente informada, em vez de congelar linhas vazias antes do preenchimento da jornada.

## PDF

`ContractPdfService` renderiza o snapshot HTML com `dompdf/dompdf` dentro do próprio processo PHP. Instale o `composer.lock` com `composer2 install --no-dev --optimize-autoloader`; não há variável de ambiente nem runtime Python. O renderizador bloqueia conteúdo remoto, PHP e JavaScript, valida assinatura `%PDF-`, tamanho e SHA-256 antes de registrar. O PDF inclui hash e paginação no rodapé. Faça QA renderizando todas as páginas com Poppler.

Todo PDF termina em uma folha exclusiva de assinaturas, com quebra de página obrigatória, identificação e CPF do locador e do locatário, áreas livres para posicionamento das assinaturas eletrônicas e dois campos opcionais de testemunhas. Essa folha facilita o envio manual ao Gov.br ou a plataforma equivalente; não representa integração automática com a API do Gov.br. Ao regenerar o PDF de um contrato existente, os dados são recuperados do snapshot imutável da própria versão contratual.

## Fluxo de assinatura externa pelo Gov.br

O portal não simula nem substitui a assinatura eletrônica do Gov.br. O fluxo operacional é:

1. o hóspede baixa o PDF original no portal;
2. assina o arquivo no aplicativo ou serviço oficial do Gov.br;
3. envia o PDF assinado no mesmo portal;
4. o administrador baixa a versão recebida, assina esse arquivo no Gov.br e envia a versão final;
5. o hóspede e o administrador podem baixar o PDF final registrado.

A migration `011_create_contract_signature_documents.sql` cria o armazenamento lógico das revisões. Execute `php scripts/migrate.php` depois da implantação. Os arquivos ficam no armazenamento privado `storage/contracts/{contract_id}` e nunca são expostos diretamente pelo servidor web.

Cada envio registra etapa, revisão, nome original, tamanho, SHA-256, papel do remetente, administrador responsável quando aplicável, IP, user-agent e horário. Reenvios não apagam versões anteriores. O sistema recusa o PDF do hóspede quando ele é idêntico ao original e recusa o PDF final quando ele é idêntico ao arquivo recebido do hóspede. Isso ajuda a evitar enganos, mas não comprova criptograficamente a assinatura: a autenticidade deve ser conferida no validador oficial do Gov.br.

O provedor local por código permanece apenas como código legado para compatibilidade histórica e não está ligado às rotas nem à interface do portal.
