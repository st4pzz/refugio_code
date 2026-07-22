# Pré-check-in

O fluxo público fica em `/minha-reserva/{token}/pre-checkin`. Estados: `NOT_STARTED`, `IN_PROGRESS`, `SUBMITTED`, `UNDER_REVIEW`, `APPROVED`, `CORRECTION_REQUESTED`, `REJECTED`.

São registrados responsável, até dez hóspedes, veículos, pets, horário estimado e observações. O limite é aplicado na interface, serviço e índice `sort_order BETWEEN 1 AND 10`; o serviço recusa a décima primeira pessoa. Placas são normalizadas e pets obedecem `PETS_ALLOWED` e `MAX_PETS`.

O envio exige aceites individuais de capacidade, visitantes, horários, sossego, eventos, piscina, churrasqueira, fumo, pets, móveis, lixo, portões e emergência. `house_rule_versions` torna o texto versionado; `house_rule_acceptances` preserva itens, hash, responsável, CPF, IP, user-agent e data.

Antes do primeiro envio, crie a versão inicial e aprove-a em `/admin/pre-checkins`. Atendimento pode aprovar, pedir correção ou rejeitar; correção/rejeição exigem motivo. Dados de hóspedes são pessoais: limite acesso por permissão, retenha apenas pelo período necessário e inclua o fluxo no processo LGPD da operação.
