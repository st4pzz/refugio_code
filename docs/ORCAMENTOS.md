# Orçamentos

Uma cotação é um snapshot. `quotes` guarda período, ocupação, moeda, totais, validade e o JSON integral do cálculo. `quote_items` discrimina cobranças; `quote_applied_rules` preserva a regra aplicada no momento. Alterar tarifa futura não muda orçamento ou reserva já criada.

O formulário público chama `POST /api/orcamentos/calcular` com CSRF e rate limit. A resposta é estimativa sujeita à aprovação administrativa; ela não confirma reserva. O painel `/admin/orcamentos` permite simular e salvar snapshots mesmo durante a configuração inicial.

Estados: `DRAFT`, `READY`, `SENT`, `VIEWED`, `ACCEPTED`, `EXPIRED`, `CANCELLED`, `CONVERTED`. Tokens públicos são hashes SHA-256. Expiração padrão vem de `DEFAULT_QUOTE_EXPIRATION_HOURS`.

Ao converter uma cotação, revalide disponibilidade dentro de transação, crie um hold curto e mantenha o snapshot como origem financeira. Não aceite total, desconto ou regra enviados pelo navegador. Cupons devem ser normalizados em maiúsculas e validados por período, limite de usos e mínimo.
