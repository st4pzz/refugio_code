<?php
use Refugio\Support\ReviewValidator;
$title='Avaliar sua estadia';
$months=[1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$checkout=new DateTimeImmutable($reservation['checkout']);
$stayLabel=$months[(int)$checkout->format('n')].' de '.$checkout->format('Y');
$nameOptions=[
    'PRIMEIRO_NOME'=>'Primeiro nome — '.ReviewValidator::displayName($reservation['nome_cliente'],'PRIMEIRO_NOME'),
    'NOME_ABREVIADO'=>'Primeiro nome e inicial — '.ReviewValidator::displayName($reservation['nome_cliente'],'NOME_ABREVIADO'),
    'NOME_COMPLETO'=>'Nome completo',
    'ANONIMO'=>'Anônimo',
];
require BASE_PATH.'/app/Views/public/_top.php';
?>
<section class="reservation-card review-form-card">
    <div class="eyebrow">Estadia verificada</div>
    <h1>Como foi sua experiência?</h1>
    <p>Obrigado por se hospedar no Refúgio do Cuscuzeiro em <strong><?= e($stayLabel) ?></strong>. Sua opinião ajuda outros hóspedes e nossa equipe.</p>
    <p class="privacy-notice">Seu telefone, e-mail, CPF e dados de pagamento nunca serão publicados.</p>
    <?php if($message=flash('error')): ?><div class="alert alert-error" role="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if($errors): ?><div class="alert alert-error" role="alert">Revise os campos destacados.</div><?php endif; ?>
    <form class="review-form" method="post" action="<?= e(base_url('api/avaliacoes/'.$token)) ?>" data-review-form novalidate>
        <?= csrf_field() ?>
        <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset><legend>Sua nota geral</legend><?php review_stars('nota_geral','Nota geral',$old,$errors,true); ?></fieldset>
        <fieldset><legend>Conte sobre cada aspecto</legend><div class="review-categories">
            <?php review_stars('nota_limpeza','Limpeza',$old,$errors); ?>
            <?php review_stars('nota_localizacao','Localização',$old,$errors); ?>
            <?php review_stars('nota_conforto','Conforto',$old,$errors); ?>
            <?php review_stars('nota_comunicacao','Comunicação',$old,$errors); ?>
            <?php review_stars('nota_custo_beneficio','Custo-benefício',$old,$errors); ?>
        </div></fieldset>
        <fieldset><legend>Sua avaliação</legend>
            <label>Comentário<textarea name="comentario" rows="7" minlength="10" maxlength="2000" required data-review-comment aria-describedby="comment-count error-comentario"><?= e($old['comentario']??'') ?></textarea></label>
            <div class="comment-meta"><small id="error-comentario" class="field-error"><?= e($errors['comentario']??'') ?></small><small id="comment-count" data-comment-count>0 / 2.000</small></div>
            <label>Como seu nome deve aparecer?<select name="nome_exibicao_modo" required><?php foreach($nameOptions as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($old['nome_exibicao_modo']??'NOME_ABREVIADO')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <small class="field-error"><?= e($errors['nome_exibicao_modo']??'') ?></small>
        </fieldset>
        <fieldset class="review-consent"><legend>Publicação</legend>
            <label><input type="checkbox" name="autoriza_publicacao" value="1" required <?= !empty($old['autoriza_publicacao'])?'checked':'' ?>> Autorizo a publicação desta avaliação no site do Refúgio do Cuscuzeiro.</label>
            <small class="field-error"><?= e($errors['autoriza_publicacao']??'') ?></small>
            <label><input type="checkbox" name="anonima" value="1" <?= !empty($old['anonima'])?'checked':'' ?>> Desejo publicar minha avaliação de forma anônima.</label>
        </fieldset>
        <button class="primary-button" type="submit"><span class="button-label">Enviar avaliação</span><span class="button-loading">Enviando...</span></button>
        <p class="privacy-copy">A avaliação ficará pendente de moderação antes de aparecer no site. Consulte nossa <a href="<?= e(base_url('politicas/privacidade')) ?>">política de privacidade</a>.</p>
    </form>
</section>
<?php require BASE_PATH.'/app/Views/public/_bottom.php'; ?>
<?php
function review_stars(string $name,string $label,array $old,array $errors,bool $large=false): void { ?>
<div class="star-field <?= $large?'star-field-large':'' ?>"><span id="label-<?= e($name) ?>"><?= e($label) ?></span><div class="star-input" role="radiogroup" aria-labelledby="label-<?= e($name) ?>"><?php for($rating=5;$rating>=1;$rating--): ?><input type="radio" id="<?= e($name.'-'.$rating) ?>" name="<?= e($name) ?>" value="<?= $rating ?>" required <?= (string)($old[$name]??'')===(string)$rating?'checked':'' ?>><label for="<?= e($name.'-'.$rating) ?>" aria-label="<?= $rating ?> de 5 estrelas">★</label><?php endfor; ?></div><small class="field-error"><?= e($errors[$name]??'') ?></small></div>
<?php }
