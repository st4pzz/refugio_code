<?php $title='Link de avaliação indisponível'; require BASE_PATH.'/app/Views/public/_top.php'; ?>
<section class="reservation-card success-card"><h1><?= !empty($alreadyReviewed)?'Avaliação já registrada':'Link indisponível' ?></h1><p><?= !empty($alreadyReviewed)?'Esta reserva já possui uma avaliação registrada. Obrigado por compartilhar sua experiência.':'Este link de avaliação é inválido, expirou ou foi revogado.' ?></p><a class="primary-button inline" href="<?= e(base_url()) ?>">Voltar ao site</a></section>
<?php require BASE_PATH.'/app/Views/public/_bottom.php'; ?>
