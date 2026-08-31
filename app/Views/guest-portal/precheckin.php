<?php
$title = 'Pré-check-in ' . $portal['code'];
$editable = in_array($precheckin['status'], ['NOT_STARTED', 'IN_PROGRESS', 'CORRECTION_REQUESTED'], true);
$guests = $precheckin['guests'];
for ($i = count($guests); $i < $maxGuests; $i++) $guests[] = [];
$pets = $precheckin['pets'];
for ($i = count($pets); $i < $maxPets; $i++) $pets[] = [];
$acceptedRules = array_fill_keys($precheckin['accepted_rules'] ?? [], true);
$houseRules = $houseRuleVersion['rules'] ?? [];
$disabled = $editable ? '' : ' disabled';
require BASE_PATH . '/app/Views/public/_top.php';
?>
<section class="reservation-card">
    <p class="eyebrow">Pré-check-in</p><h1>Prepare sua chegada</h1>
    <p>Status: <strong><?= e($precheckin['status']) ?></strong>. Cadastre no máximo <?= (int) $maxGuests ?> hóspedes, incluindo crianças.</p>
    <?php if (!empty($precheckin['correction_message'])): ?><div class="alert alert-error" role="alert"><strong>Correção solicitada:</strong> <?= e($precheckin['correction_message']) ?></div><?php endif; ?>
    <?php if (!$editable): ?><div class="alert alert-success" role="status">Os dados estão bloqueados enquanto este pré-check-in está em análise ou já foi concluído.</div><?php endif; ?>
    <?php if (!$houseRuleVersion): ?><div class="alert alert-error" role="alert">As regras da casa ainda não foram aprovadas. O rascunho pode ser salvo, mas o envio está indisponível.</div><?php endif; ?>
    <?php if ($message = flash('success')): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="alert alert-error" role="alert"><?= e($message) ?></div><?php endif; ?>
</section>
<form class="reservation-card admin-form" method="post" action="<?= e(base_url('minha-reserva/' . rawurlencode($token) . '/pre-checkin')) ?>">
    <?= csrf_field() ?>
    <h2>Responsável</h2>
    <label>Nome completo<input name="responsible_name" value="<?= e($precheckin['responsible_name']) ?>" required<?= $disabled ?>></label>
    <label>CPF<input name="responsible_cpf" value="<?= e($precheckin['responsible_cpf']) ?>" required<?= $disabled ?>></label>
    <label>Nascimento<input type="date" name="responsible_birth_date" value="<?= e($precheckin['responsible_birth_date']) ?>"<?= $disabled ?>></label>
    <label>Documento complementar<input name="responsible_document" value="<?= e($precheckin['responsible_document']) ?>"<?= $disabled ?>></label>
    <label>Horário estimado<input type="time" name="estimated_arrival_time" value="<?= e($precheckin['estimated_arrival_time']) ?>"<?= $disabled ?>></label>
    <h2>Hóspedes</h2>
    <?php foreach ($guests as $i => $guest): ?><fieldset><legend>Hóspede <?= $i + 1 ?></legend>
        <label>Nome completo<input name="guests[<?= $i ?>][full_name]" value="<?= e($guest['full_name'] ?? '') ?>" <?= $i === 0 ? 'required' : '' ?><?= $disabled ?>></label>
        <label>CPF<input name="guests[<?= $i ?>][cpf]" value="<?= e($guest['cpf'] ?? '') ?>"<?= $disabled ?>></label>
        <label>Documento<input name="guests[<?= $i ?>][document_number]" value="<?= e($guest['document_number'] ?? '') ?>"<?= $disabled ?>></label>
        <label>Nascimento<input type="date" name="guests[<?= $i ?>][birth_date]" value="<?= e($guest['birth_date'] ?? '') ?>"<?= $disabled ?>></label>
        <label><input type="checkbox" name="guests[<?= $i ?>][is_responsible]" value="1" <?= !empty($guest['is_responsible']) ? 'checked' : '' ?><?= $disabled ?>> Responsável</label>
    </fieldset><?php endforeach; ?>
    <h2>Veículos</h2>
    <?php for ($i = 0; $i < 3; $i++): $vehicle = $precheckin['vehicles'][$i] ?? []; ?><fieldset>
        <label>Placa<input name="vehicles[<?= $i ?>][plate]" value="<?= e($vehicle['plate'] ?? '') ?>"<?= $disabled ?>></label>
        <label>Marca/modelo<input name="vehicles[<?= $i ?>][make_model]" value="<?= e($vehicle['make_model'] ?? '') ?>"<?= $disabled ?>></label>
        <label>Cor<input name="vehicles[<?= $i ?>][color]" value="<?= e($vehicle['color'] ?? '') ?>"<?= $disabled ?>></label>
        <label>Condutor<input name="vehicles[<?= $i ?>][driver_name]" value="<?= e($vehicle['driver_name'] ?? '') ?>"<?= $disabled ?>></label>
    </fieldset><?php endfor; ?>
    <h2>Pets</h2>
    <?php if (!$petsAllowed || $maxPets === 0): ?><p>Esta reserva não permite o cadastro de pets.</p><?php else: ?>
        <p>Cadastre até <?= (int) $maxPets ?> pet(s).</p>
        <?php foreach ($pets as $i => $pet): ?><fieldset><legend>Pet <?= $i + 1 ?></legend>
            <label>Nome<input name="pets[<?= $i ?>][name]" value="<?= e($pet['name'] ?? '') ?>"<?= $disabled ?>></label>
            <label>Espécie<input name="pets[<?= $i ?>][species]" value="<?= e($pet['species'] ?? '') ?>"<?= $disabled ?>></label>
            <label>Raça<input name="pets[<?= $i ?>][breed]" value="<?= e($pet['breed'] ?? '') ?>"<?= $disabled ?>></label>
            <label>Porte<select name="pets[<?= $i ?>][size]"<?= $disabled ?>><option value="">Selecione</option><?php foreach (['SMALL' => 'Pequeno', 'MEDIUM' => 'Médio', 'LARGE' => 'Grande'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($pet['size'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Observações<input name="pets[<?= $i ?>][notes]" value="<?= e($pet['notes'] ?? '') ?>"<?= $disabled ?>></label>
        </fieldset><?php endforeach; ?>
    <?php endif; ?>
    <h2>Aceite das regras da casa</h2>
    <?php if ($houseRuleVersion): ?><p><small>Versão <?= (int) $houseRuleVersion['version_no'] ?> · <?= e($houseRuleVersion['title']) ?></small></p><?php endif; ?>
    <?php foreach ($houseRules as $rule => $text): ?><label class="check-row"><input type="checkbox" name="rules[<?= e($rule) ?>]" value="1" <?= isset($acceptedRules[$rule]) ? 'checked' : '' ?><?= $disabled ?>><span><?= e($text) ?></span></label><?php endforeach; ?>
    <label>Observações<textarea name="notes"<?= $disabled ?>><?= e($precheckin['notes']) ?></textarea></label>
    <?php if ($editable): ?><div class="form-actions"><button class="secondary-button" name="intent" value="save">Salvar rascunho</button><button class="primary-button" name="intent" value="submit" <?= !$houseRuleVersion ? 'disabled' : '' ?>>Enviar para análise</button></div><?php endif; ?>
</form>
<?php require BASE_PATH . '/app/Views/public/_bottom.php'; ?>
