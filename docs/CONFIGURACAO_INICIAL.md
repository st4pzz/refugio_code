# Configuração inicial

## Checklist bloqueante

- [ ] Backup lógico e teste de restauração concluídos.
- [ ] Migrações 007 a 013 aplicadas primeiro em homologação.
- [ ] `APP_KEY` forte e `PORTAL_TOKEN_ENCRYPTION_KEY` com 32 bytes Base64.
- [ ] `composer2 install --no-dev --optimize-autoloader` concluído e extensões PHP `dom`, `mbstring`, `fileinfo` e `gd` ativas.
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
2. Publique o código sem executar drop scripts.
3. Na raiz publicada, rode `composer2 install --no-dev --optimize-autoloader`.
4. Rode `php scripts/migrate.php` com credenciais de homologação e depois produção.
5. Rode `php tests/run.php`.
6. Instale template/regras pelo painel; versões nascem não aprovadas.
7. Ative worker e crons gradualmente; observe `jobs`, logs iCal e notificações.
8. Somente depois libere `public_pricing_enabled`.

## Backup e rollback

Os arquivos `007_drop_*` e `008_drop_*` são destrutivos e nunca devem ser executados automaticamente. Para rollback seguro, pare workers, exporte as tabelas novas, restaure o dump anterior e reverta o código. Os drop scripts servem apenas para ambiente descartável ou rollback aprovado com backup validado.

## Limitações conhecidas

Sem credenciais MySQL locais, testes E2E exigem ambiente separado. A geração de PDF exige que as dependências do `composer.lock` estejam instaladas. iCal não expande `RRULE`. A versão sugerida do contrato não deve ser aprovada sem preencher os campos e concluir revisão jurídica/administrativa.
