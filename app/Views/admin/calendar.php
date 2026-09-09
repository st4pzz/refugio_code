<?php
$title='Calendário';
require __DIR__.'/_top.php';
$exportUrl=flash('calendar_export_url');
$prev=$start->modify('-1 month')->format('Y-m');
$next=$start->modify('+1 month')->format('Y-m');
?>
<div class="page-heading">
    <div><p class="eyebrow">Ocupação e tarifas</p><h1>Calendário</h1></div>
    <div class="month-nav"><a href="?mes=<?= e($prev) ?>" aria-label="Mês anterior">←</a><strong><?= e($start->format('m/Y')) ?></strong><a href="?mes=<?= e($next) ?>" aria-label="Próximo mês">→</a></div>
</div>

<section class="admin-panel calendar-panel">
    <div class="calendar-weekdays"><?php foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $dayName):?><span><?= e($dayName) ?></span><?php endforeach;?></div>
    <div class="month-grid">
        <?php $cursor=$start->modify('-'.$start->format('w').' days'); ?>
        <?php for($cell=0;$cell<42;$cell++,$cursor=$cursor->modify('+1 day')):$day=$cursor->format('Y-m-d');$daySpecialPrice=null;foreach($specialPrices as $candidate){if($candidate['starts_on']<=$day&&$candidate['ends_on']>=$day){$daySpecialPrice=$candidate;break;}} ?>
            <article class="calendar-day <?= $cursor->format('m')!==$start->format('m')?'outside':'' ?><?= $daySpecialPrice?' has-special-price':'' ?>">
                <div class="calendar-day-top"><strong><?= $cursor->format('d') ?></strong><?php if($daySpecialPrice):?><span class="calendar-special-price" title="<?= e($daySpecialPrice['nome']) ?>"><?= e(money($daySpecialPrice['daily_rate'])) ?></span><?php endif;?></div>
                <?php foreach($unifiedEvents as $event):$eventStart=substr((string)$event['starts_at'],0,10);$eventEnd=substr((string)$event['ends_at'],0,10);if($eventStart<=$day&&$eventEnd>$day):?>
                    <?php $external=$event['type']==='external';$label=$external?(string)$event['source']:'block';$classes='calendar-event '.$event['type'].($external?' provider-'.strtolower((string)$event['source']):''); ?><?php if(!empty($event['reservation_id'])):?><a class="<?= e($classes) ?>" href="<?= e(base_url('admin/reservas/'.$event['reservation_id'])) ?>" title="<?= e($event['title']) ?>"><?= e($label) ?><small><?= e($event['title']) ?></small></a><?php else:?><span class="<?= e($classes) ?>" title="<?= e($event['title']) ?>"><?= e($label) ?><small><?= e($event['title']) ?></small></span><?php endif;?>
                <?php endif;endforeach;?>
            </article>
        <?php endfor;?>
    </div>
</section>

<section class="admin-panel special-pricing-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">Datas especiais</p><h2>Diárias por período</h2><p>O valor especial substitui a diária base em todas as noites do período. A data final também recebe o valor informado.</p></div>
        <a class="admin-secondary" href="<?= e(base_url('admin/precos')) ?>">Configuração geral</a>
    </div>
    <?php if(can('pricing.manage')):?>
        <form class="admin-form form-grid special-price-form" method="post" action="<?= e(base_url('admin/operacoes/calendar-special-price-save')) ?>">
            <?= csrf_field() ?><input type="hidden" name="return_month" value="<?= e($start->format('Y-m')) ?>">
            <label class="full">Nome do período<input name="name" maxlength="120" placeholder="Ex.: Réveillon 2026" required></label>
            <label>Data inicial<input type="date" name="starts_on" required></label>
            <label>Data final (inclusive)<input type="date" name="ends_on" required></label>
            <label>Diária especial (R$)<input type="number" name="daily_rate" min="0.01" step="0.01" inputmode="decimal" placeholder="1500,00" required></label>
            <div class="special-price-submit"><button class="admin-primary" type="submit">Adicionar preço especial</button></div>
        </form>
    <?php endif;?>

    <div class="special-price-list">
        <?php if(!$specialPricePeriods):?><p class="empty-state">Nenhum preço especial cadastrado.</p><?php endif;?>
        <?php foreach($specialPricePeriods as $period):?>
            <article class="special-price-row<?= $period['ativo']?'':' is-inactive' ?>">
                <header>
                    <div><strong><?= e($period['nome']) ?></strong><small><?= e(date('d/m/Y',strtotime($period['starts_on']))) ?> a <?= e(date('d/m/Y',strtotime($period['ends_on']))) ?> · <?= e(money($period['daily_rate'])) ?> por noite</small></div>
                    <span class="admin-status"><?= $period['ativo']?'Ativo':'Inativo' ?></span>
                </header>
                <?php if(can('pricing.manage')):?>
                    <div class="special-price-actions">
                        <details>
                            <summary>Editar</summary>
                            <form class="admin-form form-grid special-price-edit" method="post" action="<?= e(base_url('admin/operacoes/calendar-special-price-save')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="special_price_id" value="<?= (int)$period['id'] ?>"><input type="hidden" name="return_month" value="<?= e($start->format('Y-m')) ?>">
                                <label class="full">Nome<input name="name" maxlength="120" value="<?= e($period['nome']) ?>" required></label>
                                <label>Data inicial<input type="date" name="starts_on" value="<?= e($period['starts_on']) ?>" required></label>
                                <label>Data final (inclusive)<input type="date" name="ends_on" value="<?= e($period['ends_on']) ?>" required></label>
                                <label>Diária especial (R$)<input type="number" name="daily_rate" min="0.01" step="0.01" inputmode="decimal" value="<?= e($period['daily_rate']) ?>" required></label>
                                <div class="special-price-submit"><button class="admin-primary" type="submit">Salvar alterações</button></div>
                            </form>
                        </details>
                        <form method="post" action="<?= e(base_url('admin/operacoes/calendar-special-price-toggle')) ?>" data-confirm="<?= $period['ativo']?'Desativar este preço especial? A diária base voltará a valer no período.':'Ativar este preço especial?' ?>">
                            <?= csrf_field() ?><input type="hidden" name="special_price_id" value="<?= (int)$period['id'] ?>"><input type="hidden" name="active" value="<?= $period['ativo']?'0':'1' ?>"><input type="hidden" name="return_month" value="<?= e($start->format('Y-m')) ?>">
                            <button class="<?= $period['ativo']?'admin-danger':'admin-secondary' ?>" type="submit"><?= $period['ativo']?'Desativar':'Ativar' ?></button>
                        </form>
                    </div>
                <?php endif;?>
            </article>
        <?php endforeach;?>
    </div>
</section>

<div class="detail-grid">
    <section class="admin-panel">
        <h2>Novo bloqueio</h2>
        <form class="admin-form" action="<?= e(base_url('admin/bloqueios')) ?>" method="post"><?= csrf_field() ?><label>Início<input type="date" name="data_inicio" required></label><label>Fim (data liberada)<input type="date" name="data_fim" required></label><label>Tipo<select name="origem"><option value="USO_PROPRIO">Uso próprio</option><option value="MANUTENCAO">Manutenção</option><option value="RESERVA_EXTERNA">Reserva externa</option><option value="INDISPONIBILIDADE">Indisponibilidade</option><option value="OUTRO">Outro</option></select></label><label>Motivo<input name="motivo" maxlength="255" required></label><button class="admin-primary">Bloquear datas</button></form>
        <?php foreach($blocks as $block):?><div class="block-row"><div><strong><?= e($block['motivo']) ?></strong><small><?= e($block['data_inicio']) ?>–<?= e($block['data_fim']) ?> · <?= e($block['origem']) ?></small></div><?php if(!$block['reserva_id']):?><form action="<?= e(base_url('admin/bloqueios/'.$block['id'].'/excluir')) ?>" method="post" data-confirm="Excluir este bloqueio?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$block['id'] ?>"><button class="admin-danger">Excluir</button></form><?php endif;?></div><?php endforeach;?>
    </section>
    <section class="admin-panel">
        <h2>Fontes iCal</h2>
        <form class="admin-form" action="<?= e(base_url('admin/operacoes/calendar-source-create')) ?>" method="post"><?= csrf_field() ?><label>Nome<input name="name" required></label><label>Provedor<select name="provider"><option>AIRBNB</option><option>BOOKING</option><option>GOOGLE</option><option value="OTHER">Outro</option></select></label><label>URL do feed<input type="url" name="feed_url" required><small>Use o link de exportação iCal fornecido pela plataforma, normalmente terminado em .ics. Não reutilize o link gerado pelo próprio Refúgio.</small></label><label>Timezone<input name="timezone" value="America/Sao_Paulo" required></label><label>Intervalo (minutos)<input type="number" name="interval" min="5" max="1440" value="30"></label><button class="admin-primary">Adicionar e importar</button></form>
        <?php foreach($calendarSources as $source):?><div class="block-row"><div><strong><?= e($source['nome']) ?> · <?= e($source['provider']) ?></strong><small><?= e($source['ultimo_status']??'Ainda não sincronizado') ?> · <?= (int)$source['active_event_count'] ?> bloqueio(s) ativo(s) · última <?= e($source['ultimo_sync_em']??'nunca') ?> · próxima <?= e($source['proximo_sync_em']??'agora') ?></small><?php if($source['first_event_at']):?><small>Período importado: <?= e(substr((string)$source['first_event_at'],0,10)) ?> até <?= e(substr((string)$source['last_event_at'],0,10)) ?></small><?php endif;?><?php if($source['ultimo_erro']):?><small class="message-error"><?= e($source['ultimo_erro']) ?></small><?php endif;?></div><form method="post" action="<?= e(base_url('admin/operacoes/calendar-source-sync')) ?>"><?= csrf_field() ?><input type="hidden" name="source_id" value="<?= (int)$source['id'] ?>"><button class="admin-secondary">Sincronizar agora</button></form></div><?php endforeach;?>
    </section>
</div>

<div class="detail-grid">
    <section class="admin-panel">
        <h2>Exportação iCal privada</h2><p>O feed expõe apenas ocupação, sem dados do hóspede ou valores.</p>
        <form class="admin-form" method="post" action="<?= e(base_url('admin/operacoes/calendar-export-create')) ?>"><?= csrf_field() ?><label>Nome<input name="name" value="Feed principal"></label><button class="admin-primary">Gerar link revogável</button></form>
        <?php if($exportUrl):?><div class="export-link-card" role="status"><strong>Link criado. Copie agora.</strong><p>Por segurança, este endereço completo não será exibido novamente.</p><div class="copy-link-row"><input id="calendar-export-url" type="text" readonly value="<?= e($exportUrl) ?>" aria-label="Link privado do calendário"><button class="admin-secondary" type="button" data-copy-target="calendar-export-url" data-copy-feedback="calendar-export-copy-feedback">Copiar link</button></div><small id="calendar-export-copy-feedback" class="copy-feedback" aria-live="polite"></small></div><?php endif;?>
        <?php if(!$exportTokens):?><p class="empty-state">Nenhum link de exportação criado.</p><?php endif;?><?php foreach($exportTokens as $export):?><div class="block-row"><div><strong><?= e($export['nome']) ?></strong><small>Criado em <?= e($export['created_at']) ?><?= $export['last_used_at']?' · último acesso '.e($export['last_used_at']):' · ainda não acessado' ?><?= $export['revoked_at']?' · revogado em '.e($export['revoked_at']):'' ?></small></div><?php if((int)$export['ativo']===1&&!$export['revoked_at']):?><form method="post" action="<?= e(base_url('admin/operacoes/calendar-export-revoke')) ?>" data-confirm="Revogar este link iCal? A sincronização que o utiliza deixará de funcionar."><?= csrf_field() ?><input type="hidden" name="export_id" value="<?= (int)$export['id'] ?>"><button class="admin-danger">Revogar</button></form><?php else:?><span class="admin-status">Revogado</span><?php endif;?></div><?php endforeach;?>
    </section>
    <section class="admin-panel">
        <h2>Sincronizações</h2><?php if(!$syncLogs):?><p class="empty-state">Nenhuma sincronização.</p><?php endif;?><?php foreach($syncLogs as $log):?><div class="block-row"><strong><?= e($log['source_name']) ?> · <?= e($log['status']) ?></strong><small>lidos <?= (int)$log['events_seen'] ?> · novos <?= (int)$log['events_created'] ?> · atualizados <?= (int)$log['events_updated'] ?> · <?= e($log['created_at']) ?><?= $log['error_message']?' · '.e($log['error_message']):'' ?></small></div><?php endforeach;?>
    </section>
</div>
<?php require __DIR__.'/_bottom.php';?>
