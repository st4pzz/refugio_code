<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use DateTimeImmutable;
use PDO;
use Refugio\Config\Database;
use Refugio\Marketing\MarketingProviderFactory;
use Refugio\Repositories\MarketingAiRepository;
use Refugio\Repositories\MarketingRepository;
use Refugio\Services\AuditService;
use Refugio\Services\AuthorizationService;
use Refugio\Services\JobQueueService;
use Refugio\Services\MarketingOAuthService;
use Refugio\Services\MarketingSyncService;
use Refugio\Services\OpenAiMarketingAnalysisService;
use Refugio\Support\Csrf;
use Refugio\Support\Env;
use RuntimeException;
use Throwable;

final class MarketingController
{
    private PDO $db;
    private MarketingRepository $repository;

    public function __construct(private array $config)
    {
    }

    public function index(): void
    {
        AuthorizationService::requirePermission('marketing.view');
        $this->boot();
        [$start, $end] = $this->period($_GET);
        $filters = $this->filters($_GET);
        $dashboard = $this->repository->dashboard($start, $end, $filters);
        $integrations = $this->repository->integrations();
        $campaigns = $this->repository->campaigns($filters);
        $allCampaigns = $filters ? $this->repository->campaigns() : $campaigns;
        $providerStates = $this->providerStates($integrations, $allCampaigns);
        $analysisDataReady = count($campaigns) > 0 || count($dashboard['daily'] ?? []) > 0;
        $aiConfigured = Env::bool('OPENAI_MARKETING_ENABLED', true) && trim(Env::get('OPENAI_API_KEY')) !== '';
        $openAiModel = Env::get('OPENAI_MARKETING_MODEL', 'gpt-5.6-sol');
        $analyses = [];
        $selectedAnalysis = null;
        try {
            $aiRepository = new MarketingAiRepository($this->db);
            $analyses = array_map([MarketingAiRepository::class, 'decode'], $aiRepository->latest());
            $wantedId = max(0, (int) ($_GET['analise'] ?? 0));
            if ($wantedId > 0) {
                $row = $aiRepository->find($wantedId);
                $selectedAnalysis = $row ? MarketingAiRepository::decode($row) : null;
            } elseif ($analyses) {
                $selectedAnalysis = $analyses[0];
            }
        } catch (Throwable $error) {
            // O dashboard continua disponivel durante a janela entre deploy e migration.
            error_log('[marketing-ai] Historico indisponivel: ' . $error->getMessage());
        }
        require BASE_PATH . '/app/Views/admin/marketing.php';
    }

    public function analyze(): never
    {
        AuthorizationService::requirePermission('marketing.analyze');
        $this->boot();
        $redirectParams = [];
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                throw new RuntimeException('Use o formulario protegido para solicitar a analise.');
            }
            Csrf::verify($_POST['_csrf'] ?? null);
            [$start, $end] = $this->period($_POST);
            $filters = $this->filters($_POST);
            $redirectParams = array_filter([
                'inicio' => $start,
                'fim' => $end,
                'provider' => $filters['provider'] ?? null,
                'integracao_id' => $filters['integracao_id'] ?? null,
                'campanha_id' => $filters['campanha_id'] ?? null,
                'modelo' => $filters['modelo'] ?? 'ultimo',
            ], static fn(mixed $value): bool => $value !== null && $value !== '');

            $userId = (int) $_SESSION['admin_id'];
            $analysisId = (new OpenAiMarketingAnalysisService($this->db))->analyze($start, $end, $filters, $userId);
            $row = (new MarketingAiRepository($this->db))->find($analysisId);
            (new AuditService($this->db))->record(
                'MARKETING',
                'GERAR_ANALISE_IA',
                'marketing_analises_ia',
                $analysisId,
                null,
                [
                    'periodo' => [$start, $end],
                    'filtros' => $filters,
                    'modelo' => $row['modelo'] ?? Env::get('OPENAI_MARKETING_MODEL', 'gpt-5.6-sol'),
                    'input_tokens' => $row['input_tokens'] ?? null,
                    'output_tokens' => $row['output_tokens'] ?? null,
                ]
            );
            $redirectParams['analise'] = $analysisId;
            flash('success', 'Analise de campanhas concluida pela IA. Revise as recomendacoes antes de alterar qualquer campanha.');
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
        }
        $query = $redirectParams ? '?' . http_build_query($redirectParams, '', '&', PHP_QUERY_RFC3986) : '';
        redirect(base_url('admin/marketing') . $query);
    }

    public function connect(string $provider): never
    {
        AuthorizationService::requirePermission('marketing.connect');
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                throw new RuntimeException('Use o formulario protegido para iniciar a conexao.');
            }
            Csrf::verify($_POST['_csrf'] ?? null);
            $url = (new MarketingOAuthService($this->config))->authorizationUrl(strtoupper($provider));
            redirect($url);
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
            redirect(base_url('admin/marketing'));
        }
    }

    public function callback(string $provider): never
    {
        AuthorizationService::requirePermission('marketing.connect');
        $this->boot();
        try {
            if (!empty($_GET['error'])) {
                throw new RuntimeException('Autorizacao recusada pelo provedor.');
            }
            $tokens = (new MarketingOAuthService($this->config))->exchange(
                strtoupper($provider),
                (string) ($_GET['code'] ?? $_GET['auth_code'] ?? ''),
                (string) ($_GET['state'] ?? '')
            );
            $id = $this->repository->createIntegration(strtoupper($provider), $tokens, (int) $_SESSION['admin_id']);
            (new AuditService($this->db))->record('MARKETING', 'CONECTAR_OAUTH', 'marketing_integracoes', $id, null, ['provider' => strtoupper($provider)]);
            redirect(base_url('admin/marketing/contas?integracao=' . $id));
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
            redirect(base_url('admin/marketing'));
        }
    }

    public function accounts(int $id): void
    {
        AuthorizationService::requirePermission('marketing.connect');
        $this->boot();
        $integration = $this->repository->findIntegration($id) ?? throw new RuntimeException('Integracao nao encontrada.');
        $provider = MarketingProviderFactory::make($integration);
        $accounts = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $provider->listAccounts($cursor);
            $accounts = array_merge($accounts, $page['items'] ?? []);
            $cursor = $page['next_cursor'] ?? null;
            $pages++;
        } while ($cursor && $pages < 20);
        $assets = ['pages' => [], 'pixels' => []];
        if (count($accounts) === 1 && method_exists($provider, 'listAssets')) {
            $assets = $provider->listAssets((string) $accounts[0]['external_id']);
        }
        require BASE_PATH . '/app/Views/admin/marketing-accounts.php';
    }

    public function selectAccount(int $id): never
    {
        AuthorizationService::requirePermission('marketing.connect');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $integration = $this->repository->findIntegration($id) ?? throw new RuntimeException('Integracao nao encontrada.');
            $wanted = (string) ($_POST['conta_externa_id'] ?? '');
            $provider = MarketingProviderFactory::make($integration);
            $account = null;
            $cursor = null;
            $pages = 0;
            do {
                $page = $provider->listAccounts($cursor);
                foreach ($page['items'] ?? [] as $item) {
                    if (hash_equals((string) $item['external_id'], $wanted)) {
                        $account = $item;
                        break 2;
                    }
                }
                $cursor = $page['next_cursor'] ?? null;
                $pages++;
            } while ($cursor && $pages < 20);
            if (!$account) {
                throw new RuntimeException('Conta de anuncios nao autorizada.');
            }
            $selectedConfig = [];
            if (method_exists($provider, 'listAssets')) {
                $assets = $provider->listAssets($wanted);
                foreach (['page_external_id' => 'pages', 'pixel_external_id' => 'pixels'] as $field => $group) {
                    $selected = (string) ($_POST[$field] ?? '');
                    if ($selected === '') {
                        continue;
                    }
                    $allowed = false;
                    foreach ($assets[$group] ?? [] as $asset) {
                        $assetId = (string) ($asset['id'] ?? $asset['pixel_id'] ?? '');
                        if (hash_equals($assetId, $selected)) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) {
                        throw new RuntimeException('Ativo adicional nao autorizado para esta conta.');
                    }
                    $selectedConfig[$field] = $selected;
                }
            }
            $this->repository->selectAccount($id, $account, $selectedConfig);
            (new AuditService($this->db))->record('MARKETING', 'SELECIONAR_CONTA', 'marketing_integracoes', $id, null, [
                'conta' => $account['external_id'],
                'provider' => $integration['provider'],
                'ativos' => $selectedConfig,
            ]);
            flash('success', 'Conta conectada. A primeira sincronizacao pode ser iniciada.');
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
        }
        redirect(base_url('admin/marketing'));
    }

    public function action(int $id, string $action): never
    {
        AuthorizationService::requirePermission($action === 'desconectar' ? 'marketing.connect' : 'marketing.sync');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            if ($action === 'sincronizar') {
                $days = max(1, min(366, Env::int('MARKETING_SYNC_DAYS_DEFAULT', 30)));
                $start = (string) ($_POST['inicio'] ?? date('Y-m-d', strtotime('-' . ($days - 1) . ' days')));
                $end = (string) ($_POST['fim'] ?? date('Y-m-d'));
                $key = 'marketing-sync-' . $id . '-' . $start . '-' . $end . '-' . date('YmdHi');
                (new JobQueueService($this->db))->enqueue('MARKETING_SYNC', [
                    'integration_id' => $id,
                    'start' => $start,
                    'end' => $end,
                    'user_id' => (int) $_SESSION['admin_id'],
                ], $key, 40, 5);
                flash('success', 'Sincronizacao adicionada a fila.');
            } elseif ($action === 'testar') {
                (new MarketingSyncService($this->db))->test($id, (int) $_SESSION['admin_id']);
                flash('success', 'Conexao validada com sucesso.');
            } elseif ($action === 'desconectar') {
                $this->repository->disconnect($id);
                (new AuditService($this->db))->record('MARKETING', 'DESCONECTAR', 'marketing_integracoes', $id);
                flash('success', 'Integracao desconectada e credenciais removidas.');
            } else {
                throw new RuntimeException('Acao de marketing invalida.');
            }
        } catch (Throwable $error) {
            flash('error', $error->getMessage());
        }
        redirect(base_url('admin/marketing'));
    }

    private function period(array $source): array
    {
        $preset = (string) ($source['periodo'] ?? '30d');
        $today = new DateTimeImmutable('today');
        [$start, $end] = match ($preset) {
            'hoje' => [$today, $today],
            'ontem' => [$today->modify('-1 day'), $today->modify('-1 day')],
            '7d' => [$today->modify('-6 days'), $today],
            'mes' => [$today->modify('first day of this month'), $today],
            'mes-anterior' => [$today->modify('first day of last month'), $today->modify('last day of last month')],
            default => [$today->modify('-29 days'), $today],
        };
        if (!empty($source['inicio']) && !empty($source['fim'])) {
            try {
                $candidateStart = new DateTimeImmutable((string) $source['inicio']);
                $candidateEnd = new DateTimeImmutable((string) $source['fim']);
                if ($candidateEnd >= $candidateStart && $candidateEnd <= $candidateStart->modify('+366 days')) {
                    $start = $candidateStart;
                    $end = $candidateEnd;
                }
            } catch (Throwable) {
            }
        }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function filters(array $source): array
    {
        $filters = [];
        $provider = strtoupper(trim((string) ($source['provider'] ?? '')));
        if (in_array($provider, ['META', 'GOOGLE', 'TIKTOK'], true)) {
            $filters['provider'] = $provider;
        }
        foreach (['integracao_id', 'campanha_id'] as $field) {
            $id = max(0, (int) ($source[$field] ?? 0));
            if ($id > 0) {
                $filters[$field] = $id;
            }
        }
        $filters['modelo'] = ($source['modelo'] ?? 'ultimo') === 'primeiro' ? 'primeiro' : 'ultimo';
        return $filters;
    }

    private function providerStates(array $integrations, array $campaigns): array
    {
        $encryptionKey = base64_decode(trim(Env::get('MARKETING_ENCRYPTION_KEY')), true);
        $encryptionReady = is_string($encryptionKey) && strlen($encryptionKey) === 32;
        $requirements = [
            'META' => ['label' => 'Meta Ads', 'keys' => ['META_APP_ID', 'META_APP_SECRET']],
            'GOOGLE' => ['label' => 'Google Ads', 'keys' => ['GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_DEVELOPER_TOKEN']],
            'TIKTOK' => ['label' => 'TikTok Ads', 'keys' => ['TIKTOK_ADS_APP_ID', 'TIKTOK_ADS_APP_SECRET']],
        ];
        $states = [];
        foreach ($requirements as $provider => $definition) {
            $providerIntegrations = array_values(array_filter(
                $integrations,
                static fn(array $integration): bool => strtoupper((string) ($integration['provider'] ?? '')) === $provider
                    && ($integration['status'] ?? '') !== 'DESCONECTADA'
            ));
            $connected = array_values(array_filter(
                $providerIntegrations,
                static fn(array $integration): bool => ($integration['status'] ?? '') === 'CONECTADA'
                    && trim((string) ($integration['conta_externa_id'] ?? '')) !== ''
            ));
            $configured = $encryptionReady;
            foreach ($definition['keys'] as $key) {
                if (trim(Env::get($key)) === '') {
                    $configured = false;
                    break;
                }
            }
            $campaignCount = count(array_filter(
                $campaigns,
                static fn(array $campaign): bool => strtoupper((string) ($campaign['provider'] ?? '')) === $provider
            ));
            $lastSync = null;
            foreach ($providerIntegrations as $integration) {
                $candidate = $integration['ultima_sincronizacao_em'] ?? null;
                if ($candidate && ($lastSync === null || strtotime((string) $candidate) > strtotime((string) $lastSync))) {
                    $lastSync = (string) $candidate;
                }
            }
            $pendingIntegration = null;
            foreach ($providerIntegrations as $integration) {
                if (($integration['status'] ?? '') === 'PENDENTE') {
                    $pendingIntegration = $integration;
                    break;
                }
            }
            $states[$provider] = [
                'provider' => $provider,
                'slug' => strtolower($provider),
                'label' => $definition['label'],
                'configured' => $configured,
                'connected' => count($connected) > 0,
                'integration_count' => count($providerIntegrations),
                'connected_count' => count($connected),
                'pending_integration_id' => $pendingIntegration ? (int) $pendingIntegration['id'] : null,
                'campaign_count' => $campaignCount,
                'last_sync' => $lastSync,
            ];
        }
        return $states;
    }

    private function boot(): void
    {
        if (isset($this->db)) {
            return;
        }
        $this->db = Database::connection();
        $this->repository = new MarketingRepository($this->db);
    }
}
