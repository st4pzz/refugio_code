# Pedidos de reserva via WhatsApp

O painel em `/admin/pedidos-whatsapp` oferece um fluxo administrativo em duas etapas para contatos atendidos pelo WhatsApp.

## Etapa 1: proposta

O atendente informa cliente, período, adultos, crianças, valores, validade e condições comerciais. O sistema:

1. valida os dados e a disponibilidade atual;
2. cria uma reserva manual em `AGUARDANDO_APROVACAO`;
3. não bloqueia as datas nessa etapa;
4. salva o preço como snapshot no PDF versionado;
5. gera um pedido de reserva A4 em armazenamento privado.

O PDF deixa explícito que ainda não existe reserva confirmada. Uma nova versão pode ser emitida com outra validade enquanto o pedido aguarda o aceite.

## Etapa 2: cobrança

Depois que o cliente aprova pelo atendimento, o operador informa forma de cobrança, valor do sinal, vencimento, Pix Copia e Cola ou chave Pix, imagem opcional do QR Code e orientações.

A aprovação reutiliza a transação do fluxo de reservas:

- bloqueia o mutex de aprovação;
- confere novamente reservas, bloqueios, iCal e holds;
- cria uma ou mais cobranças;
- altera o pedido para `AGUARDANDO_PAGAMENTO`;
- bloqueia temporariamente as datas;
- emite um novo PDF com Pix, vencimentos, total, sinal e saldo.

O pagamento continua sujeito à conferência manual. A reserva só muda para `RESERVA_CONFIRMADA` quando o operador confirma o pagamento no painel.

## Envio

Os arquivos ficam em `storage/reservation-documents`, negado pelo Apache, e só podem ser abertos por uma rota administrativa autenticada.
Ao anonimizar o cliente, o sistema também exclui os snapshots e os PDFs que ainda contêm os dados pessoais.

Quando existe uma conversa com o mesmo telefone e a janela de atendimento de 24 horas está aberta, o painel envia o PDF como documento pela WhatsApp Cloud API e registra:

- mensagem na conversa;
- entrega associada à versão exata do PDF;
- identificador externo;
- sucesso ou falha;
- evento no histórico da reserva.

Sem janela ativa, o painel não tenta contornar a política do WhatsApp. O operador visualiza ou baixa o PDF, abre uma mensagem pronta em `wa.me` e anexa o arquivo manualmente. Fora da janela, também pode ser usado um template aprovado já configurado na central de conversas.

## Instalação

Execute a migration `009_create_whatsapp_reservation_documents.sql` com o runner padrão:

```bash
php scripts/migrate.php
```

O gerador usa `dompdf/dompdf` no mesmo processo PHP dos contratos. Na implantação, execute `composer2 install --no-dev --optimize-autoloader`; não é necessário instalar Python nem configurar variável para o binário. O worker não é necessário para esta emissão: o PDF é produzido na ação administrativa para ficar disponível imediatamente.
