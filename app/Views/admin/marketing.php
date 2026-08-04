<?php
declare(strict_types=1);

$title = 'Marketing';
require BASE_PATH . '/app/Views/admin/_top.php';
$m = $dashboard['metrics'];
$a = $dashboard['attribution'];
$fmt = static fn(mixed $value, string $suffix = ''): string => $value === null ? '—' : number_format((float) $value, 2, ',', '.') . $suffix;
$analysis = is_array($selectedAnalysis['analysis'] ?? null) ? $selectedAnalysis['analysis'] : [];
$providerStates = is_array($providerStates ?? null) ? $providerStates : [];
$analysisDataReady = (bool) ($analysisDataReady ?? (count($campaigns) > 0 || count($dashboard['daily'] ?? []) > 0));
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">Aquisição e atribuição indicativa</p>
        <h1>Marketing</h1>
        <p>Dados sincronizados de Meta, Google e TikTok. O dashboard não consulta APIs externas ao abrir.</p>
    </div>
</section>

<?php if ($providerStates): ?>
<section class="admin-panel marketing-source-panel">
    <div class="panel-heading">
        <div>
            <h2>Fontes da análise</h2>
            <p>As credenciais do aplicativo habilitam o OAuth. A conta de anúncios precisa ser autorizada uma vez e sincronizada para alimentar o painel.</p>
        </div>
    </div>
    <div class="marketing-source-grid">
        <?php foreach ($providerStates as $state): ?>
            <?php
            $ready = $state['connected'] && $state['campaign_count'] > 0;
            $statusLabel = !$state['configured']
                ? 'Configuração pendente'
                : (!$state['connected']
                    ? ($state['pending_integration_id'] ? 'Selecionar conta' : 'Aplicativo configurado')
                    : ($state['campaign_count'] > 0 ? 'Pronto para análise' : 'Sincronização pendente'));
            ?>
            <article class="marketing-source-card <?= $ready ? 'is-ready' : '' ?>">
                <header>
                    <strong><?= e($state['label']) ?></strong>
                    <span class="admin-status <?= $ready ? 'status-conectada' : 'status-pendente' ?>"><?= e($statusLabel) ?></span>
                </header>
                <ol class="marketing-source-steps">
                    <li class="<?= $state['configured'] ? 'done' : '' ?>">Aplicativo configurado no servidor</li>
                    <li class="<?= $state['connected'] ? 'done' : '' ?>">Conta de anúncios autorizada</li>
                    <li class="<?= $state['campaign_count'] > 0 ? 'done' : '' ?>"><?= (int) $state['campaign_count'] ?> campanha(s) sincronizada(s)</li>
                </ol>
                <?php if ($state['last_sync']): ?>
                    <small>Última sincronização: <?= e(date('d/m/Y H:i', strtotime($state['last_sync']))) ?></small>
                <?php endif; ?>
                <?php if (can('marketing.connect') && $state['configured'] && !$state['connected'] && !$state['pending_integration_id']): ?>
                    <form action="<?= e(base_url('admin/marketing/conectar/' . $state['slug'])) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="admin-secondary">Autorizar <?= e($state['label']) ?></button>
                    </form>
                <?php elseif (can('marketing.connect') && !$state['connected'] && $state['pending_integration_id']): ?>
                    <a class="admin-secondary" href="<?= e(base_url('admin/marketing/contas') . '?integracao=' . (int) $state['pending_integration_id']) ?>">Selecionar conta de anúncios</a>
                <?php elseif ($state['connected']): ?>
                    <a class="admin-secondary" href="#marketing-integrations">Gerenciar e sincronizar</a>
                <?php else: ?>
                    <small>Configure as variáveis deste provedor no <code>.env</code>.</small>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<form class="marketing-filters" method="get" action="<?= e(base_url('admin/marketing')) ?>">
    <select name="periodo" aria-label="Período">
        <?php foreach (['hoje' => 'Hoje', 'ontem' => 'Ontem', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', 'mes' => 'Mês atual', 'mes-anterior' => 'Mês anterior'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= ($_GET['periodo'] ?? '30d') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="inicio" value="<?= e($start) ?>" aria-label="Data inicial">
    <input type="date" name="fim" value="<?= e($end) ?>" aria-label="Data final">
    <select name="provider" aria-label="Canal">
        <option value="">Todos os canais</option>
        <?php foreach (['META', 'GOOGLE', 'TIKTOK'] as $provider): ?>
            <option value="<?= e($provider) ?>" <?= ($filters['provider'] ?? '') === $provider ? 'selected' : '' ?>><?= e($provider) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="integracao_id" aria-label="Conta">
        <option value="">Todas as contas</option>
        <?php foreach ($integrations as $integration): ?>
            <option value="<?= (int) $integration['id'] ?>" <?= (string) ($filters['integracao_id'] ?? '') === (string) $integration['id'] ? 'selected' : '' ?>>
                <?= e($integration['provider'] . ' · ' . $integration['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="campanha_id" aria-label="Campanha">
        <option value="">Todas as campanhas</option>
        <?php foreach ($campaigns as $campaign): ?>
            <option value="<?= (int) $campaign['id'] ?>" <?= (string) ($filters['campanha_id'] ?? '') === (string) $campaign['id'] ? 'selected' : '' ?>><?= e($campaign['nome']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="modelo" aria-label="Modelo de atribuição">
        <option value="ultimo">Último contato</option>
        <option value="primeiro" <?= ($filters['modelo'] ?? '') === 'primeiro' ? 'selected' : '' ?>>Primeiro contato</option>
    </select>
    <button class="admin-primary">Filtrar</button>
</form>

<div class="metric-grid marketing-metrics">
    <div><strong><?= money($m['gasto']) ?></strong><span>Investimento</span></div>
    <div><strong><?= e(number_format((float) ($m['impressoes'] ?? 0), 0, ',', '.')) ?></strong><span>Impressões</span></div>
    <div><strong><?= ($m['alcance'] ?? null) === null ? '—' : e(number_format((float) $m['alcance'], 0, ',', '.')) ?></strong><span>Alcance</span></div>
    <div><strong><?= e(number_format((float) ($m['cliques'] ?? 0), 0, ',', '.')) ?></strong><span>Cliques</span></div>
    <div><strong><?= ($m['ctr'] ?? null) === null ? '—' : e($fmt($m['ctr'], '%')) ?></strong><span>CTR</span></div>
    <div><strong><?= ($m['cpc'] ?? null) === null ? '—' : money($m['cpc']) ?></strong><span>CPC</span></div>
    <div><strong><?= ($m['cpm'] ?? null) === null ? '—' : money($m['cpm']) ?></strong><span>CPM</span></div>
    <div><strong><?= e(number_format((float) (($a['leads_internos'] ?? 0) ?: ($m['leads'] ?? 0)), 0, ',', '.')) ?></strong><span>Leads atribuídos</span></div>
    <div><strong><?= ($m['cpl'] ?? null) === null ? '—' : money($m['cpl']) ?></strong><span>Custo por lead</span></div>
    <div><strong><?= e((string) ($a['solicitacoes'] ?? 0)) ?></strong><span>Solicitações</span></div>
    <div><strong><?= e((string) ($a['reservas_confirmadas'] ?? 0)) ?></strong><span>Reservas confirmadas</span></div>
    <div><strong><?= money($a['receita'] ?? 0) ?></strong><span>Receita atribuída</span></div>
    <div><strong><?= ($m['custo_reserva'] ?? null) === null ? '—' : money($m['custo_reserva']) ?></strong><span>Custo por reserva</span></div>
    <div><strong><?= ($m['roas'] ?? null) === null ? '—' : e($fmt($m['roas'], '×')) ?></strong><span>ROAS</span></div>
    <div><strong><?= ($m['taxa_conversao'] ?? null) === null ? '—' : e($fmt($m['taxa_conversao'], '%')) ?></strong><span>Lead → reserva</span></div>
</div>

<section class="admin-panel marketing-ai-panel">
    <div class="panel-heading marketing-ai-heading">
        <div>
            <p class="eyebrow">Diagnóstico assistido</p>
            <h2>Análise de campanhas com OpenAI</h2>
            <p>A IA usa o período e os filtros acima, sugere melhorias e criativos, mas nunca modifica campanhas automaticamente.</p>
        </div>
        <span class="ai-model-badge"><?= e($openAiModel) ?></span>
    </div>

    <?php if (can('marketing.analyze')): ?>
        <form class="marketing-ai-action" action="<?= e(base_url('admin/marketing/analisar')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="inicio" value="<?= e($start) ?>">
            <input type="hidden" name="fim" value="<?= e($end) ?>">
            <input type="hidden" name="provider" value="<?= e($filters['provider'] ?? '') ?>">
            <input type="hidden" name="integracao_id" value="<?= e((string) ($filters['integracao_id'] ?? '')) ?>">
            <input type="hidden" name="campanha_id" value="<?= e((string) ($filters['campanha_id'] ?? '')) ?>">
            <input type="hidden" name="modelo" value="<?= e($filters['modelo'] ?? 'ultimo') ?>">
            <div>
                <strong><?= e(date('d/m/Y', strtotime($start))) ?> a <?= e(date('d/m/Y', strtotime($end))) ?></strong>
                <small>Serão enviados somente métricas, campanhas e metadados de criativos — sem hóspedes ou credenciais.</small>
            </div>
            <button class="admin-primary" <?= (!$aiConfigured || !$analysisDataReady) ? 'disabled' : '' ?>>Analisar com IA</button>
        </form>
        <?php if (!$aiConfigured): ?>
            <p class="ai-config-warning">Configure <code>OPENAI_API_KEY</code> no servidor e execute a migration 010 para habilitar a análise.</p>
        <?php elseif (!$analysisDataReady): ?>
            <p class="ai-config-warning">A OpenAI está configurada. Autorize e sincronize ao menos uma conta de anúncios para habilitar a análise deste período.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($analyses): ?>
        <nav class="ai-history" aria-label="Histórico de análises">
            <span>Histórico:</span>
            <?php foreach ($analyses as $historyItem): ?>
                <a class="<?= (int) ($selectedAnalysis['id'] ?? 0) === (int) $historyItem['id'] ? 'active' : '' ?>" href="<?= e(base_url('admin/marketing') . '?analise=' . (int) $historyItem['id']) ?>">
                    #<?= (int) $historyItem['id'] ?> · <?= e(date('d/m H:i', strtotime($historyItem['created_at']))) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($selectedAnalysis && $analysis): ?>
        <article class="ai-analysis">
            <header class="ai-analysis-header">
                <div>
                    <span class="admin-status ai-confidence-<?= e((string) ($analysis['nivel_confianca'] ?? 'baixo')) ?>">Confiança <?= e((string) ($analysis['nivel_confianca'] ?? 'baixo')) ?></span>
                    <h3><?= e((string) ($analysis['resumo_executivo'] ?? '')) ?></h3>
                </div>
                <small>
                    Período <?= e(date('d/m/Y', strtotime($selectedAnalysis['data_inicio']))) ?>–<?= e(date('d/m/Y', strtotime($selectedAnalysis['data_fim']))) ?><br>
                    <?= e($selectedAnalysis['modelo']) ?> · <?= e(date('d/m/Y H:i', strtotime($selectedAnalysis['created_at']))) ?>
                    <?php if ($selectedAnalysis['input_tokens'] !== null || $selectedAnalysis['output_tokens'] !== null): ?><br><?= e((string) ($selectedAnalysis['input_tokens'] ?? 0)) ?> entrada · <?= e((string) ($selectedAnalysis['output_tokens'] ?? 0)) ?> saída<?php endif; ?>
                </small>
            </header>

            <?php $quality = is_array($analysis['qualidade_dados'] ?? null) ? $analysis['qualidade_dados'] : []; ?>
            <div class="ai-data-quality">
                <strong>Qualidade dos dados</strong>
                <p><?= e((string) ($quality['diagnostico'] ?? 'Não informada.')) ?></p>
                <?php if (!empty($quality['lacunas'])): ?>
                    <ul><?php foreach ($quality['lacunas'] as $gap): ?><li><?= e((string) $gap) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>

            <div class="ai-evidence-grid">
                <section>
                    <h4>Destaques</h4>
                    <?php foreach ($analysis['destaques'] ?? [] as $item): ?>
                        <article><strong><?= e((string) ($item['titulo'] ?? '')) ?></strong><p><?= e((string) ($item['evidencia'] ?? '')) ?></p><small><?= e((string) ($item['impacto'] ?? '')) ?></small></article>
                    <?php endforeach; ?>
                </section>
                <section>
                    <h4>Alertas</h4>
                    <?php foreach ($analysis['alertas'] ?? [] as $item): ?>
                        <article><strong><?= e((string) ($item['titulo'] ?? '')) ?></strong><p><?= e((string) ($item['evidencia'] ?? '')) ?></p><small><?= e((string) ($item['acao'] ?? '')) ?></small></article>
                    <?php endforeach; ?>
                </section>
            </div>

            <section class="ai-section">
                <h4>Recomendações priorizadas</h4>
                <div class="ai-recommendations">
                    <?php foreach ($analysis['recomendacoes'] ?? [] as $recommendation): ?>
                        <article class="priority-<?= e((string) ($recommendation['prioridade'] ?? 'baixa')) ?>">
                            <div><span><?= e((string) ($recommendation['canal'] ?? 'GERAL')) ?></span><b><?= e((string) ($recommendation['prioridade'] ?? '')) ?></b></div>
                            <h5><?= e((string) ($recommendation['titulo'] ?? '')) ?></h5>
                            <p><?= e((string) ($recommendation['justificativa'] ?? '')) ?></p>
                            <strong>Ação</strong><p><?= e((string) ($recommendation['acao'] ?? '')) ?></p>
                            <small>Métrica de sucesso: <?= e((string) ($recommendation['metrica_sucesso'] ?? '')) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ai-section">
                <h4>Sugestões de criativos</h4>
                <div class="ai-creatives">
                    <?php foreach ($analysis['criativos'] ?? [] as $creative): ?>
                        <details>
                            <summary><span><?= e((string) ($creative['canal'] ?? '')) ?></span> <?= e((string) ($creative['conceito'] ?? '')) ?></summary>
                            <dl>
                                <div><dt>Formato</dt><dd><?= e((string) ($creative['formato'] ?? '')) ?></dd></div>
                                <div><dt>Público</dt><dd><?= e((string) ($creative['publico'] ?? '')) ?></dd></div>
                                <div><dt>Gancho</dt><dd><?= e((string) ($creative['gancho'] ?? '')) ?></dd></div>
                                <div><dt>Roteiro</dt><dd><?= e((string) ($creative['roteiro'] ?? '')) ?></dd></div>
                                <div><dt>Texto principal</dt><dd><?= e((string) ($creative['texto_principal'] ?? '')) ?></dd></div>
                                <div><dt>Título</dt><dd><?= e((string) ($creative['titulo'] ?? '')) ?></dd></div>
                                <div><dt>Chamada</dt><dd><?= e((string) ($creative['chamada_acao'] ?? '')) ?></dd></div>
                                <div><dt>Restrições</dt><dd><?= e((string) ($creative['restricoes'] ?? '')) ?></dd></div>
                            </dl>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ai-section">
                <h4>Plano de testes</h4>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Hipótese</th><th>Teste</th><th>Duração</th><th>Métrica e decisão</th></tr></thead>
                        <tbody>
                        <?php foreach ($analysis['plano_testes'] ?? [] as $test): ?>
                            <tr>
                                <td><?= e((string) ($test['hipotese'] ?? '')) ?></td>
                                <td><strong><?= e((string) ($test['variavel'] ?? '')) ?></strong><small>Controle: <?= e((string) ($test['controle'] ?? '')) ?><br>Variante: <?= e((string) ($test['variante'] ?? '')) ?></small></td>
                                <td><?= e((string) ($test['duracao_dias'] ?? '')) ?> dias</td>
                                <td><?= e((string) ($test['metrica_primaria'] ?? '')) ?><small><?= e((string) ($test['criterio_decisao'] ?? '')) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </article>
    <?php else: ?>
        <div class="ai-empty"><strong>Nenhuma análise gerada ainda.</strong><span>Sincronize as contas, selecione um período e solicite o primeiro diagnóstico.</span></div>
    <?php endif; ?>
</section>

<div class="detail-grid">
    <section class="admin-panel">
        <h2>Investimento e leads por dia</h2>
        <div class="marketing-timeline">
            <?php $maxSpend = max(array_map(static fn(array $row): float => (float) $row['gasto'], $dashboard['daily']) ?: [1]); ?>
            <?php foreach ($dashboard['daily'] as $row): ?>
                <article>
                    <time><?= e(date('d/m', strtotime($row['data']))) ?></time>
                    <strong><?= e($row['provider']) ?></strong>
                    <div><span style="--value:<?= e((string) ($maxSpend > 0 ? min(100, (float) $row['gasto'] / $maxSpend * 100) : 0)) ?>%"></span></div>
                    <small><?= money($row['gasto']) ?> · <?= e((string) ($row['leads'] ?? '—')) ?> lead(s)</small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="admin-panel">
        <h2>Comparação entre canais</h2>
        <div class="table-wrap"><table><thead><tr><th>Canal</th><th>Gasto</th><th>Cliques</th><th>Leads</th><th>CPL</th></tr></thead><tbody>
        <?php foreach ($dashboard['channels'] as $row): ?>
            <tr><td><?= e($row['provider']) ?></td><td><?= money($row['gasto']) ?></td><td><?= e((string) $row['cliques']) ?></td><td><?= e((string) ($row['leads'] ?? '—')) ?></td><td><?= empty($row['leads']) ? '—' : money((float) $row['gasto'] / (float) $row['leads']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>

<section class="admin-panel">
    <h2>Reservas e receita atribuída por dia</h2>
    <div class="table-wrap"><table><thead><tr><th>Data</th><th>Reservas confirmadas</th><th>Receita confirmada</th></tr></thead><tbody>
    <?php foreach ($dashboard['attribution_daily'] as $row): ?>
        <tr><td><?= e(date('d/m/Y', strtotime($row['data']))) ?></td><td><?= e((string) $row['reservas']) ?></td><td><?= money($row['receita']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="admin-panel" id="marketing-integrations">
    <div class="panel-heading"><h2>Integrações</h2><span>Credenciais nunca são exibidas.</span></div>
    <?php if (!$integrations): ?><div class="empty-state"><p>Nenhuma conta conectada.</p></div><?php endif; ?>
    <?php foreach ($integrations as $integration): ?>
        <article class="integration-card">
            <div>
                <strong><?= e($integration['provider'] . ' · ' . $integration['nome']) ?></strong>
                <span class="admin-status status-<?= e(strtolower($integration['status'])) ?>"><?= e($integration['status']) ?></span>
                <small>Última sincronização: <?= e($integration['ultima_sincronizacao_em'] ? date('d/m/Y H:i', strtotime($integration['ultima_sincronizacao_em'])) : 'Nunca') ?></small>
                <?php if ($integration['erro_ultima_sincronizacao']): ?><p class="error-text"><?= e($integration['erro_ultima_sincronizacao']) ?></p><?php endif; ?>
            </div>
            <div class="action-row">
                <?php if (can('marketing.connect') && empty($integration['conta_externa_id']) && $integration['status'] !== 'DESCONECTADA'): ?>
                    <a class="admin-secondary" href="<?= e(base_url('admin/marketing/contas') . '?integracao=' . (int) $integration['id']) ?>">Selecionar conta</a>
                <?php endif; ?>
                <?php if (can('marketing.sync') && !empty($integration['conta_externa_id']) && $integration['status'] !== 'DESCONECTADA'): ?>
                    <form action="<?= e(base_url('admin/marketing/integracoes/' . $integration['id'] . '/sincronizar')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="inicio" value="<?= e($start) ?>"><input type="hidden" name="fim" value="<?= e($end) ?>"><button class="admin-primary">Sincronizar agora</button></form>
                    <form action="<?= e(base_url('admin/marketing/integracoes/' . $integration['id'] . '/testar')) ?>" method="post"><?= csrf_field() ?><button class="admin-secondary">Testar conexão</button></form>
                <?php endif; ?>
                <?php if (can('marketing.connect') && $integration['status'] !== 'DESCONECTADA'): ?>
                    <form action="<?= e(base_url('admin/marketing/integracoes/' . $integration['id'] . '/desconectar')) ?>" method="post" data-confirm="Desconectar e apagar as credenciais desta conta?"><?= csrf_field() ?><button class="admin-danger">Desconectar</button></form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-panel">
    <h2>Campanhas sincronizadas</h2>
    <div class="table-wrap"><table><thead><tr><th>Canal</th><th>Campanha</th><th>Objetivo</th><th>Status</th><th>Orçamento diário</th><th>Sincronizada</th></tr></thead><tbody>
    <?php foreach ($campaigns as $campaign): ?>
        <tr><td><?= e($campaign['provider']) ?></td><td><?= e($campaign['nome']) ?></td><td><?= e($campaign['objetivo'] ?: '—') ?></td><td><?= e($campaign['status'] ?: '—') ?></td><td><?= $campaign['orcamento_diario'] === null ? '—' : money($campaign['orcamento_diario']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($campaign['last_synced_at']))) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$campaigns): ?><tr><td colspan="6">Nenhuma campanha sincronizada para os filtros selecionados.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<p class="privacy-note">A atribuição é indicativa (primeiro ou último contato) e não afirma causalidade quando não há identificador confiável. Recomendações da IA exigem revisão humana antes de qualquer alteração.</p>

<?php require BASE_PATH . '/app/Views/admin/_bottom.php'; ?>
