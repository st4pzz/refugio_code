<section class="admin-panel" id="booking-cobrancas">
    <div class="panel-heading"><div><p class="eyebrow">Pagamento ao anfitrião</p><h2>Booking · contatos e cobranças</h2><p>Reservas importadas do mês selecionado. Cadastre o contato encontrado no Booking e o valor acordado na plataforma. Salvar não envia mensagens.</p></div></div>
    <?php if (!$bookingBillingEvents): ?><p class="empty-state">Nenhuma reserva ou bloqueio Booking neste mês. Confira as fontes importadas abaixo ou selecione outro mês.</p><?php endif; ?>
    <?php foreach ($bookingBillingEvents as $booking): ?>
    <details class="admin-panel" id="booking-<?= (int) $booking['id'] ?>">
        <summary><strong><?= e(date('d/m/Y', strtotime($booking['starts_at']))) ?> → <?= e(date('d/m/Y', strtotime($booking['ends_at']))) ?></strong> · <?= e($booking['guest_name'] ?: $booking['summary'] ?: 'Reserva Booking') ?> · <?= !empty($booking['paid']) ? 'Pagamento conferido' : 'A conferir / cobrar' ?></summary>
        <form class="admin-form form-grid" method="post" action="<?= e(base_url('admin/operacoes/calendar-booking-save')) ?>" data-booking-billing data-template="<?= e(\Refugio\Services\BookingBillingService::templateBody()) ?>" data-checkin="<?= e(date('d/m/Y', strtotime($booking['starts_at']))) ?>" data-checkout="<?= e(date('d/m/Y', strtotime($booking['ends_at']))) ?>">
            <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $booking['id'] ?>"><input type="hidden" name="return_month" value="<?= e($start->format('Y-m')) ?>"><input type="hidden" name="request_key" value="<?= e(bin2hex(random_bytes(16))) ?>">
            <label>Nome do hóspede<input name="guest_name" maxlength="160" value="<?= e($booking['guest_name'] ?? '') ?>" required></label>
            <label>WhatsApp<input name="phone" type="tel" maxlength="30" placeholder="(19) 99999-9999" value="<?= e($booking['phone'] ?? '') ?>"><small>Para estrangeiros, use + e o código do país.</small></label>
            <label>E-mail<input name="email" type="email" maxlength="190" value="<?= e($booking['email'] ?? '') ?>"></label>
            <label>Valor desta cobrança (R$)<input name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="<?= e($booking['amount'] ?? '') ?>"><small>Informe somente o valor ainda devido, conforme acordado no Booking.</small></label>
            <label>Vencimento<input type="date" name="due_on" value="<?= e($booking['due_on'] ?? '') ?>"></label>
            <label><input type="checkbox" name="paid" value="1" <?= !empty($booking['paid']) ? 'checked' : '' ?>> Pagamento já conferido — não cobrar</label>
            <div class="full"><button class="admin-secondary" type="submit">Salvar contato e cobrança</button></div>
            <fieldset class="full"><legend>Enviar cobrança</legend>
                <label><input type="checkbox" name="channels[]" value="WHATSAPP"> WhatsApp (template aprovado)</label>
                <label><input type="checkbox" name="channels[]" value="EMAIL"> E-mail</label>
                <label><input type="checkbox" name="confirmed_booking" value="1"> Conferi no Booking: é uma reserva real e este valor deve ser pago diretamente ao anfitrião.</label>
                <p>O iCal também pode conter bloqueios sem hóspede. Não cobre esses eventos nem valores já pagos.</p>
                <details><summary>Prévia da mensagem</summary><pre data-booking-preview style="white-space:pre-wrap;font:inherit"></pre><noscript>Mensagem: nome, entrada, saída, valor e vencimento, com Pix CPF 74373137820 — Sergio Ramella, Banco Safra. Solicita comprovante e orienta a não pagar novamente caso já tenha pago.</noscript></details>
                <button type="submit" class="admin-primary" formaction="<?= e(base_url('admin/operacoes/calendar-booking-send')) ?>">Salvar e enviar cobrança</button>
            </fieldset>
        </form>
        <h3>Histórico de envios</h3>
        <p>Enviado ao provedor não significa pagamento recebido. Se houver falha ou envio em andamento por muito tempo, confira no provedor antes de tentar novamente.</p>
        <?php if (!$booking['deliveries']): ?><p>Nenhuma cobrança enviada.</p><?php endif; ?>
        <?php foreach ($booking['deliveries'] as $delivery): $labels = ['PENDING'=>'Na fila','SENDING'=>'Envio iniciado — conferir se não atualizar','SENT'=>'Enviado ao provedor','REVIEW_REQUIRED'=>'Falha — conferir antes de reenviar']; ?>
            <div class="block-row"><strong><?= e($delivery['channel']) ?> · <?= e($delivery['recipient']) ?></strong><small><?= e($labels[$delivery['status']] ?? $delivery['status']) ?> · <?= e($delivery['sent_at'] ?: $delivery['created_at']) ?></small><?php if ($delivery['error_message']): ?><small class="message-error"><?= e($delivery['error_message']) ?></small><?php endif; ?></div>
        <?php endforeach; ?>
    </details>
    <?php endforeach; ?>
</section>
<script src="<?= e(base_url('assets/js/booking-billing.js?v=1')) ?>" defer></script>
