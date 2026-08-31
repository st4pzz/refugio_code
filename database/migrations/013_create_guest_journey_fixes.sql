-- Corrige integridade do pre-check-in e elimina a automacao de avaliacao sem token.
SET NAMES utf8mb4;

ALTER TABLE house_rule_acceptances
    DROP INDEX uk_house_rule_acceptance,
    ADD KEY idx_house_rule_acceptance_history (precheckin_id,rule_version_id,accepted_at,id);

UPDATE automation_rules
SET ativo=0
WHERE code='REVIEW_INVITATION';

-- Normaliza dados historicos para que reservas ja encerradas nao mantenham
-- acessos, contratos pendentes ou convites de avaliacao reutilizaveis.
UPDATE guest_portal_tokens token
JOIN reservas reserva ON reserva.id=token.reservation_id
SET token.status='REVOKED',
    token.revoked_at=COALESCE(token.revoked_at,NOW()),
    token.revoked_reason=COALESCE(token.revoked_reason,'RESERVATION_TERMINATED')
WHERE reserva.status IN ('RECUSADA','EXPIRADA','CANCELADA')
  AND token.status='ACTIVE';

UPDATE reservation_contracts contrato
JOIN reservas reserva ON reserva.id=contrato.reservation_id
SET contrato.status=CASE
        WHEN reserva.status='EXPIRADA' THEN 'EXPIRED'
        ELSE 'CANCELLED'
    END
WHERE reserva.status IN ('RECUSADA','EXPIRADA','CANCELADA')
  AND contrato.status NOT IN ('SUPERSEDED','CANCELLED','EXPIRED','FULLY_SIGNED');

UPDATE convites_avaliacao convite
JOIN reservas reserva ON reserva.id=convite.reserva_id
SET convite.status='REVOGADO',
    convite.revogado_em=COALESCE(convite.revogado_em,NOW())
WHERE reserva.status IN ('RECUSADA','EXPIRADA','CANCELADA')
  AND convite.status IN ('PENDENTE','ENVIADO');
