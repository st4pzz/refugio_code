# Configuração inicial

## Checklist bloqueante

- [ ] Backup lógico e teste de restauração concluídos.
- [ ] Migrações 007 e 008 aplicadas primeiro em homologação.
- [ ] `APP_KEY` forte e `PORTAL_TOKEN_ENCRYPTION_KEY` com 32 bytes Base64.
- [ ] `PDF_PYTHON_BINARY`, Python 3.10+ e ReportLab 4.x disponíveis no worker.
- [ ] Dados completos do locador, endereço/CEP do imóvel, comarca, contatos e emergência.
- [ ] Check-in, checkout, silêncio, estadia mínima/máxima e prazos de hold/cotação.
- [ ] `GUESTS_INCLUDED_IN_BASE_RATE` e `EXTRA_GUEST_FEE_MODE` definidos.
- [ ] Caução e pets decididos; valores e limites preenchidos.
- [ ] Política de cancelamento revisada e `CANCELLATION_POLICY_APPROVED` ativada.
- [ ] Inventário real cadastrado, com quantidade, estado, evidências e valores orientativos.
- [ ] Acesso, Wi-Fi e orientações preenchidos e testados na liberação progressiva.
- [ ] Regras da casa e template contratual aprovados por perfis autorizados.
- [ ] SMTP, WhatsApp, templates e consentimento testados sem dados reais.
- [ ] Fontes iCal cadastradas; importação, cancelamento e exportação testados.
- [ ] Crons/worker monitorados; relógio e timezone `America/Sao_Paulo` validados.
- [ ] Fluxo completo em homologação: cotação → solicitação → aprovação → pagamento → contrato → assinatura → pré-check-in → chegada → avaliação.

## Implantação

1. Faça backup e registre a versão atual.
2. Publique código e dependências sem executar drop scripts.
3. Rode `php scripts/migrate.php` com credenciais de homologação e depois produção.
4. Rode `php tests/run.php`.
5. Instale template/regras pelo painel; versões nascem não aprovadas.
6. Ative worker e crons gradualmente; observe `jobs`, logs iCal e notificações.
7. Somente depois libere `public_pricing_enabled`.

## Backup e rollback

Os arquivos `007_drop_*` e `008_drop_*` são destrutivos e nunca devem ser executados automaticamente. Para rollback seguro, pare workers, exporte as tabelas novas, restaure o dump anterior e reverta o código. Os drop scripts servem apenas para ambiente descartável ou rollback aprovado com backup validado.

## Limitações conhecidas

Sem credenciais MySQL locais, testes E2E exigem ambiente separado. O PDF depende de runtime Python. iCal não expande `RRULE`. A versão sugerida do contrato não deve ser aprovada sem preencher os campos e concluir revisão jurídica/administrativa.
