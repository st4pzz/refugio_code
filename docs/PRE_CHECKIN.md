# Pré-check-in

O fluxo público fica em `/minha-reserva/{token}/pre-checkin`. Estados: `NOT_STARTED`, `IN_PROGRESS`, `SUBMITTED`, `UNDER_REVIEW`, `APPROVED`, `CORRECTION_REQUESTED`, `REJECTED`.

São registrados responsável, hóspedes até o menor limite entre `MAX_GUESTS` e dez, veículos, pets, horário estimado e observações. O limite é aplicado na interface, serviço e índice `sort_order BETWEEN 1 AND 10`. Placas são normalizadas e pets obedecem `PETS_ALLOWED` e `MAX_PETS`.

O envio exige aceites individuais de capacidade, visitantes, horários, sossego, eventos, piscina, churrasqueira, fumo, pets, móveis, lixo, portões e emergência. A interface exibe o texto real da versão aprovada. `house_rule_versions` torna o texto versionado; cada submissão cria um novo `house_rule_acceptances`, preservando texto, hash, responsável, CPF, IP, user-agent e data. Fichas enviadas, aprovadas ou rejeitadas não podem ser alteradas pelo portal; somente `CORRECTION_REQUESTED` reabre a edição.

Antes do primeiro envio, crie a versão inicial e aprove-a em `/admin/pre-checkins`. Atendimento pode aprovar, pedir correção ou rejeitar; correção/rejeição exigem motivo. Dados de hóspedes são pessoais: limite acesso por permissão, retenha apenas pelo período necessário e inclua o fluxo no processo LGPD da operação.
