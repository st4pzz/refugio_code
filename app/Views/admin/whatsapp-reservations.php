<?php
declare(strict_types=1);
$title = 'Pedidos via WhatsApp';
require __DIR__ . '/_top.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Atendimento direto</p>
        <h1>Pedidos via WhatsApp</h1>
        <p>Emita uma proposta em PDF e, após o aceite do cliente, gere a cobrança Pix em um segundo documento.</p>
    </div>
</div>

<div class="reservation-order-layout">
    <section class="admin-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Etapa 1</p>
                <h2>Novo pedido de reserva</h2>
            </div>
        </div>
        <p class="privacy-note">A proposta não bloqueia datas. A disponibilidade será conferida novamente antes de criar a cobrança.</p>
        <?php if (can('reservas.create')): ?>
            <form class="admin-form form-grid" method="post" action="<?= e(base_url('admin/pedidos-whatsapp/criar')) ?>" data-reservation-order-form>
                <?= csrf_field() ?>
                <input type="hidden" name="request_key" value="<?= e($requestKey) ?>">

                <fieldset class="full">
                    <legend>Cliente</legend>
                    <div class="form-grid nested-form-grid">
                        <label>Nome completo<input name="nome" maxlength="160" autocomplete="name" required></label>
                        <label>WhatsApp com DDD<input name="telefone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="(16) 99999-9999" required></label>
                        <label>E-mail<input type="email" name="email" maxlength="190" autocomplete="email" required></label>
                        <label>CPF <small>Opcional, salvo se informado</small><input name="cpf" maxlength="14" inputmode="numeric"></label>
                    </div>
                </fieldset>

                <fieldset class="full">
                    <legend>Estadia</legend>
                    <div class="form-grid nested-form-grid">
                        <label>Check-in<input type="date" name="checkin" min="<?= date('Y-m-d') ?>" required></label>
                        <label>Check-out<input type="date" name="checkout" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></label>
                        <label>Adultos<input type="number" name="adultos" min="1" max="<?= (int) $config['max_guests'] ?>" value="2" required></label>
                        <label>Crianças<input type="number" name="criancas" min="0" max="<?= (int) $config['max_guests'] ?>" value="0" required></label>
                    </div>
                    <label>Observações do cliente<textarea name="observacoes" rows="2" maxlength="3000" placeholder="Motivo da viagem, necessidades especiais, pets ou outras informações"></textarea></label>
                </fieldset>

                <fieldset class="full">
                    <legend>Valores da proposta</legend>
                    <div class="form-grid nested-form-grid">
                        <label>Hospedagem<input name="valor_hospedagem" inputmode="decimal" placeholder="0,00" data-order-money required></label>
                        <label>Taxa de limpeza<input name="taxa_limpeza" inputmode="decimal" value="<?= e(number_format((float) $defaultCleaningFee, 2, ',', '.')) ?>" data-order-money></label>
                        <label>Outros serviços<input name="outros_valores" inputmode="decimal" value="0,00" data-order-money></label>
                        <label>Desconto<input name="desconto" inputmode="decimal" value="0,00" data-order-money data-order-discount></label>
                    </div>
                    <div class="order-total-preview" aria-live="polite">Total da proposta <strong data-order-total>R$ 0,00</strong></div>
                </fieldset>

                <label>Validade da proposta<input type="datetime-local" name="validade_proposta" value="<?= e($defaultValidity) ?>" required></label>
                <label class="full">Condições e observações comerciais<textarea name="observacoes_comerciais" rows="3" maxlength="3000" placeholder="Forma de aceite, inclusões, horários ou condições especiais"></textarea></label>
                <label class="full">Política de cancelamento aplicável<textarea name="politica_cancelamento" rows="5" maxlength="5000" required><?= e($defaultPolicy) ?></textarea></label>
                <button class="admin-primary full" type="submit">Criar pedido e emitir PDF inicial</button>
            </form>
        <?php else: ?>
            <p>Seu perfil pode consultar pedidos, mas não pode criá-los.</p>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Acompanhamento</p>
                <h2>Pedidos emitidos</h2>
            </div>
            <span class="admin-status"><?= count($requests) ?> registro(s)</span>
        </div>
        <?php if (!$requests): ?>
            <div class="empty-state"><strong>Nenhum pedido emitido</strong><p>O primeiro aparecerá aqui depois que o PDF for criado.</p></div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($requests as $request): $requestStatus = \Refugio\Models\ReservationStatus::from($request['status']); ?>
                    <a class="order-list-item" href="<?= e(base_url('admin/pedidos-whatsapp/' . $request['id'])) ?>">
                        <span>
                            <strong><?= e($request['nome_cliente']) ?></strong>
                            <small><?= e($request['codigo']) ?> · <?= date('d/m/Y', strtotime($request['checkin'])) ?> a <?= date('d/m/Y', strtotime($request['checkout'])) ?></small>
                            <small><?= (int) $request['quantidade_hospedes'] ?> hóspede(s) · <?= (int) $request['document_count'] ?> PDF(s)</small>
                        </span>
                        <span>
                            <b><?= money($request['valor_total']) ?></b>
                            <small class="admin-status status-<?= strtolower($requestStatus->value) ?>"><?= e($requestStatus->label()) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{const form=document.querySelector('[data-reservation-order-form]');if(!form)return;const fields=[...form.querySelectorAll('[data-order-money]')];const output=form.querySelector('[data-order-total]');const parse=(value)=>{let raw=String(value||'').replace(/\s/g,'');if(raw.includes(','))raw=raw.replace(/\./g,'').replace(',','.');return Number(raw)||0};const render=()=>{const total=fields.reduce((sum,field)=>sum+(field.hasAttribute('data-order-discount')?-1:1)*parse(field.value),0);output.textContent=Math.max(0,total).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})};fields.forEach(field=>field.addEventListener('input',render));render()});
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
