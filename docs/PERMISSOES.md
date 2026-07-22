# Permissões administrativas

`AuthorizationService` é o único ponto de decisão. Controllers pedem capacidades (`financeiro.manage`), nunca comparam nomes de perfil. `modulo.*` cobre todas as capacidades do módulo e `*` cobre o sistema.

| Perfil | Reservas/clientes | Conversas | Marketing | Financeiro | Avaliações | Configurações sensíveis |
|---|---|---|---|---|---|---|
| `SUPER_ADMIN` | total | total | total | total | total | sim |
| `ADMIN` | total | total | ver/sincronizar | total | total | não por padrão |
| `ATENDIMENTO` | ver/criar e atualizar cliente | ver/responder/gerir | não | não | ver | não |
| `MARKETING` | somente ver | não | ver/sincronizar/atribuição | não | não | não |
| `FINANCEIRO` | somente ver | não | não | ver/alterar/exportar | não | não |
| `LEITURA` | somente ver | somente ver | somente ver | somente ver | somente ver | não |

Capacidades usadas:

```text
dashboard.view
reservas.view, reservas.create, reservas.manage
clientes.view, clientes.update, clientes.merge, clientes.export, clientes.anonymize
conversas.view, conversas.reply, conversas.manage
marketing.view, marketing.sync, marketing.attribution, marketing.connect
financeiro.view, financeiro.manage, financeiro.export
avaliacoes.view, avaliacoes.manage
configuracoes.view, configuracoes.sensitive
```

Os perfis são semeados em `003_create_core_operacao.sql`. Administradores existentes recebem `SUPER_ADMIN` na primeira migration para evitar bloqueio. Novos usuários são criados com perfil explícito:

```bash
php scripts/create_admin.php pessoa@dominio.com "Nome" ATENDIMENTO
```

O painel de Configurações permite trocar um perfil somente com `configuracoes.sensitive` e impede remover o último `SUPER_ADMIN` ativo. Mudança do próprio perfil invalida o cache de permissões da sessão.

Para criar perfil personalizado, insira uma linha em `perfis_admin` com JSON de capacidades e atribua em `usuarios_admin_perfis`. Faça isso por migration versionada; não espalhe condicionais por controllers/views.

Todos os POSTs autenticados usam CSRF. Views também escondem ações não autorizadas, mas a decisão final sempre ocorre no servidor.
