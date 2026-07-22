# Motor de preços

`PricingEngine` é uma classe pura: recebe solicitação, configuração e regras e devolve diárias, itens, regras aplicadas e totais. Valores são convertidos para centavos; não há soma monetária em `float`.

Ordem por noite: diária base → data especial (substitui a base) ou temporada → regras ordenadas por prioridade. Depois entram limpeza, hóspede extra, pet e cupom. Regras não acumuláveis encerram a sua cadeia. Datas usam check-in inclusivo e checkout exclusivo.

Configuração principal: `property_pricing_settings`. Temporadas, datas especiais, regras e cupons ficam, respectivamente, em `pricing_seasons`, `pricing_special_dates`, `pricing_rules` e `pricing_coupons`.

O cálculo público permanece bloqueado até que o administrador defina:

- `GUESTS_INCLUDED_IN_BASE_RATE` / `guests_included_in_base_rate`;
- `EXTRA_GUEST_FEE_MODE` / `extra_guest_fee_mode`, com `PER_NIGHT` ou `PER_STAY`;
- `public_pricing_enabled = 1`.

Simulação administrativa pode rodar antes disso e retorna `configuration_complete=false`, mas não deve ser enviada como preço definitivo. O cliente nunca envia total: a API recalcula no servidor.

Testes cobrem cálculo base, limpeza, adicional, data especial, limite de dez pessoas e falha fechada. Novos tipos de regra devem manter cálculo determinístico e adicionar testes de precedência e arredondamento.
