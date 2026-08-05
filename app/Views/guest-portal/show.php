<?php
$title='Minha reserva '.$portal['code'];
$contractStatusLabels=[
    'READY'=>'Disponível para assinatura',
    'SENT'=>'Disponível para assinatura',
    'VIEWED'=>'Aguardando seu envio',
    'SIGNED_BY_GUEST'=>'Recebido do hóspede',
    'FULLY_SIGNED'=>'Concluído pelas duas partes',
];
$portalSuccess=flash('success');
$portalError=flash('error');
require BASE_PATH.'/app/Views/public/_top.php';
?>
<section class="reservation-card portal-hero">
    <p class="eyebrow">Minha reserva</p>
    <h1>Olá, <?= e($portal['guest_first_name']) ?></h1>
    <p>Acompanhe cada etapa da estadia <strong><?= e($portal['code']) ?></strong>.</p>
    <?php if(!$portal['contract']&&$portalSuccess):?><div class="alert alert-success" role="status"><?= e($portalSuccess) ?></div><?php endif;?>
    <?php if(!$portal['contract']&&$portalError):?><div class="alert alert-error" role="alert"><?= e($portalError) ?></div><?php endif;?>
</section>

<section class="reservation-card">
    <h2>Jornada</h2>
    <ol class="portal-timeline"><?php foreach($portal['timeline'] as $step):?><li class="<?= $step['done']?'done':'' ?>"><span><?= $step['done']?'✓':'○' ?></span><?= e($step['label']) ?></li><?php endforeach;?></ol>
</section>

<section class="reservation-card">
    <h2>Resumo</h2>
    <dl class="summary summary-grid"><div><dt>Check-in</dt><dd><?= date('d/m/Y',strtotime($portal['checkin'])) ?></dd></div><div><dt>Check-out</dt><dd><?= date('d/m/Y',strtotime($portal['checkout'])) ?></dd></div><div><dt>Hóspedes</dt><dd><?= (int)$portal['guests'] ?></dd></div><div><dt>Valor</dt><dd><?= money($portal['total']) ?></dd></div></dl>
</section>

<section class="reservation-card">
    <h2>Pagamentos</h2>
    <?php if(!$portal['payments']):?><p>Nenhuma cobrança disponível.</p><?php endif;?>
    <?php foreach($portal['payments'] as $payment):?><div class="portal-row"><strong><?= e($payment['tipo']) ?> · <?= money($payment['valor']) ?></strong><span class="status-badge"><?= e($payment['status']) ?></span></div><?php endforeach;?>
</section>

<?php if($portal['contract']):$contract=$portal['contract'];$guestDocument=$contract['guest_signed_document']??null;$finalDocument=$contract['fully_signed_document']??null;?>
<section class="reservation-card contract-workflow" id="contrato">
    <div class="contract-heading">
        <div><p class="eyebrow">Assinatura externa pelo Gov.br</p><h2>Contrato</h2></div>
        <span class="contract-status"><?= e($contractStatusLabels[$contract['status']]??$contract['status']) ?></span>
    </div>

    <?php if($portalSuccess):?><div class="alert alert-success contract-upload-feedback" id="contract-upload-success" role="status"><?= e($portalSuccess) ?></div><?php endif;?>
    <?php if($portalError):?><div class="alert alert-error contract-upload-feedback" id="contract-upload-error" role="alert"><?= e($portalError) ?></div><?php endif;?>

    <?php if($finalDocument):?>
        <div class="contract-complete" role="status">
            <strong>Contrato concluído e registrado</strong>
            <p>A versão assinada pelo hóspede e pelo proprietário está disponível para download.</p>
            <a class="primary-button inline" href="<?= e(base_url('minha-reserva/'.rawurlencode($token).'/contrato/assinado.pdf')) ?>">Baixar contrato final</a>
            <small>Revisão <?= (int)$finalDocument['revision_no'] ?> · SHA-256 <?= e(substr($finalDocument['sha256'],0,16)) ?>… · <?= date('d/m/Y H:i',strtotime($finalDocument['created_at'])) ?></small>
        </div>
    <?php else:?>
        <ol class="contract-steps">
            <li class="done"><span>1</span><div><strong>Baixe o contrato</strong><p>Salve o PDF original no celular ou computador.</p><?php if($contract['pdf_path']):?><a class="secondary-button inline" href="<?= e(base_url('minha-reserva/'.rawurlencode($token).'/contrato.pdf')) ?>">Baixar PDF para assinar</a><?php else:?><small>O PDF está sendo preparado. Atualize esta página em instantes.</small><?php endif;?></div></li>
            <li class="<?= $guestDocument?'done':'current' ?>"><span>2</span><div><strong>Assine no Gov.br</strong><p>Abra o PDF no aplicativo ou serviço de assinatura do Gov.br, posicione sua assinatura e baixe o novo arquivo assinado.</p></div></li>
            <li class="<?= $guestDocument?'done':'current' ?>"><span>3</span><div><strong>Envie o PDF assinado</strong><p>Selecione abaixo exatamente o arquivo devolvido pelo Gov.br.</p></div></li>
            <li class="<?= $guestDocument?'current':'' ?>"><span>4</span><div><strong>Aguarde a assinatura do proprietário</strong><p>Depois do seu envio, o proprietário baixará o documento, assinará pelo Gov.br e registrará a versão final aqui.</p></div></li>
        </ol>

        <?php if($guestDocument):?>
            <div class="contract-received" role="status">
                <strong>Seu contrato assinado foi recebido</strong>
                <p>Enviado em <?= date('d/m/Y H:i',strtotime($guestDocument['created_at'])) ?>. Estamos aguardando a assinatura do proprietário.</p>
                <a class="secondary-button inline" href="<?= e(base_url('minha-reserva/'.rawurlencode($token).'/contrato/assinado.pdf')) ?>">Baixar arquivo enviado</a>
                <small>Revisão <?= (int)$guestDocument['revision_no'] ?> · SHA-256 <?= e(substr($guestDocument['sha256'],0,16)) ?>…</small>
            </div>
            <details class="contract-replace"><summary>Enviei o arquivo errado</summary>
        <?php endif;?>
        <form class="contract-upload-form" action="<?= e(base_url('minha-reserva/'.rawurlencode($token).'/contrato/enviar-assinado')) ?>" method="post" enctype="multipart/form-data" data-upload-form>
            <?= csrf_field() ?>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)($uploadMaxBytes??8*1024*1024) ?>">
            <label>Contrato assinado em PDF<input type="file" name="signed_contract" accept=".pdf,application/pdf" required></label>
            <label class="contract-confirm"><input type="checkbox" name="signed_on_gov" value="1" required><span>Confirmo que este é o PDF assinado por mim no Gov.br e que conferi o documento antes do envio.</span></label>
            <button class="primary-button" type="submit"><?= $guestDocument?'Substituir PDF enviado':'Enviar contrato assinado' ?></button>
            <progress value="0" max="100" hidden aria-label="Progresso do envio"></progress>
            <small>Envie apenas PDF de até <?= max(1,(int)floor(($uploadMaxBytes??8*1024*1024)/1048576)) ?> MB. O sistema valida o formato e registra o hash do arquivo. A autenticidade da assinatura deve ser conferida no serviço oficial do Gov.br.</small>
        </form>
        <?php if($guestDocument):?></details><?php endif;?>
    <?php endif;?>
</section>
<?php endif;?>

<?php if($portal['precheckin']):?><section class="reservation-card"><h2>Pré-check-in</h2><p>Status: <strong><?= e($portal['precheckin']['status']) ?></strong></p><a class="primary-button inline" href="<?= e(base_url(ltrim($portal['precheckin_path'],'/'))) ?>">Abrir pré-check-in</a></section><?php endif;?>
<?php if($portal['arrival']):?><section class="reservation-card"><h2>Chegada</h2><p><strong>Endereço:</strong> <?= e($portal['arrival']['address']) ?></p><p><?= nl2br(e($portal['arrival']['directions'])) ?></p><p><strong>Acesso:</strong> <?= nl2br(e($portal['arrival']['access'])) ?></p><p><strong>Wi-Fi:</strong> <?= e($portal['arrival']['wifi_name']) ?> · <?= e($portal['arrival']['wifi_password']) ?></p><p><strong>Emergência:</strong> <?= e($portal['arrival']['emergency_contact']) ?></p></section><?php else:?><section class="reservation-card"><h2>Instruções de chegada</h2><p>Serão liberadas perto do check-in quando pagamento, contrato e pré-check-in atenderem às regras configuradas.</p></section><?php endif;?>
<?php require BASE_PATH.'/app/Views/public/_bottom.php';?>
