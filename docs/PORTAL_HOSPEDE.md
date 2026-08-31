# Portal do hóspede

Rota: `/minha-reserva/{token}`. Não há conta e o ID interno nunca aparece. O token possui 256 bits, hash pesquisável, cópia AES-256-GCM para lembretes, expiração opcional, revogação, regeneração e contador de uso. Configure `PORTAL_TOKEN_ENCRYPTION_KEY` com 32 bytes em Base64; rotação exige plano de recriptografia.

Liberação progressiva:

1. Antes do pagamento: resumo, valor, pagamento, política e contato.
2. Após pagamento: contrato, assinatura, pré-check-in e regras.
3. Perto do check-in: endereço, acesso, Wi-Fi, como chegar e emergência, somente se as exigências de contrato/pré-check-in estiverem satisfeitas.

O prazo da terceira etapa usa `CHECKIN_INSTRUCTIONS_RELEASE_HOURS`. `CONTRACT_REQUIRED_BEFORE_CHECKIN` e `PRECHECKIN_REQUIRED` controlam as dependências. Conteúdo sensível recebe `Cache-Control: private, no-store` e `noindex`.

Jobs armazenam apenas `run_id`; o token é descriptografado em memória durante o envio. Links persistidos em notificações são redigidos. Se um link vazar, use “Novo link portal”: o token anterior é revogado imediatamente.

O link pode ser criado tanto na reserva quanto em **Contratos e portal**, mesmo antes de existir contrato. O endereço completo é mostrado uma única vez; depois disso o painel conserva apenas o prefixo e os metadados de uso. Abrir o portal não marca o contrato como visualizado: esse evento só é registrado quando o hóspede solicita o PDF.
