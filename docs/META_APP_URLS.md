# URLs do App Meta — Refugio_Site

Use sempre HTTPS e o host canônico com `www`.

## Configurações básicas do aplicativo

| Campo no App Meta | Valor |
|---|---|
| Domínios do aplicativo | `refugiodocuscuzeiro.com.br` |
| URL do site | `https://www.refugiodocuscuzeiro.com.br/` |
| URL da Política de Privacidade | `https://www.refugiodocuscuzeiro.com.br/politicas/privacidade` |
| URL dos Termos de Serviço | `https://www.refugiodocuscuzeiro.com.br/politicas/termos` |
| Exclusão de dados do usuário | Selecione **URL de instruções de exclusão de dados** |
| URL das instruções de exclusão | `https://www.refugiodocuscuzeiro.com.br/politicas/exclusao-de-dados` |

Não informe protocolo, caminho ou barra no campo **Domínios do aplicativo**. O namespace não é uma URL e pode permanecer vazio; se a Meta exigir um, use um identificador disponível, como `refugio_site`.

## Facebook Login / Marketing API

Cadastre exatamente esta URL em **URIs de redirecionamento OAuth válidos**:

```text
https://www.refugiodocuscuzeiro.com.br/admin/configuracoes/integracoes/meta/callback
```

Ela também deve ser o valor de produção:

```dotenv
META_REDIRECT_URI=https://www.refugiodocuscuzeiro.com.br/admin/configuracoes/integracoes/meta/callback
```

O sistema solicita `ads_read` e `business_management`. Não adicione curingas, parâmetros ou barra final ao callback.

## WhatsApp Cloud API

Use como **URL de retorno de chamada do webhook**:

```text
https://www.refugiodocuscuzeiro.com.br/api/whatsapp/webhook
```

O token de verificação não é uma URL. Crie um valor aleatório, salve-o como `WHATSAPP_VERIFY_TOKEN` na hospedagem e cole exatamente o mesmo valor no painel Meta. Assine o objeto `whatsapp_business_account` e o campo `messages`.

## Valores relacionados no ambiente

```dotenv
APP_URL=https://www.refugiodocuscuzeiro.com.br
META_APP_ID=1048908150884314
META_APP_SECRET=SEGREDO_DO_APP
META_REDIRECT_URI=https://www.refugiodocuscuzeiro.com.br/admin/configuracoes/integracoes/meta/callback
WHATSAPP_APP_SECRET=O_MESMO_SEGREDO_DO_APP_META
WHATSAPP_VERIFY_TOKEN=TOKEN_ALEATORIO_CRIADO_POR_VOCE
```

Nunca publique a chave secreta, o access token do WhatsApp ou o conteúdo do `.env`.
