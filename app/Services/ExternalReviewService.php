<?php
declare(strict_types=1);

namespace Refugio\Services;

use DateTimeImmutable;
use PDO;
use Refugio\Marketing\MarketingHttpClient;
use Refugio\Repositories\ReviewRepository;
use Refugio\Support\Env;
use Refugio\Support\ReviewValidator;
use RuntimeException;

final class ExternalReviewService
{
    private ReviewRepository $repository;
    private MarketingHttpClient $http;

    public function __construct(private PDO $db, private array $config)
    {
        $this->repository = new ReviewRepository($db);
        $this->http = new MarketingHttpClient();
    }

    public function googleStatus(): array
    {
        $configured = true;
        foreach (['GOOGLE_BUSINESS_CLIENT_ID','GOOGLE_BUSINESS_CLIENT_SECRET','GOOGLE_BUSINESS_ACCOUNT_ID','GOOGLE_BUSINESS_LOCATION_ID'] as $key) {
            if (Env::get($key) === '') $configured = false;
        }
        return ['configured' => $configured, 'integration' => $this->repository->googleIntegration(false)];
    }

    public function googleAuthorizationUrl(): string
    {
        $this->assertGoogleConfigured();
        $state = bin2hex(random_bytes(32));
        $_SESSION['_review_google_oauth'] = ['state' => $state, 'expires' => time() + 600];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => Env::get('GOOGLE_BUSINESS_CLIENT_ID'),
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function completeGoogleOAuth(string $code, string $state, int $userId): void
    {
        $this->assertGoogleConfigured();
        $stored = $_SESSION['_review_google_oauth'] ?? null;
        unset($_SESSION['_review_google_oauth']);
        if (!$stored || !hash_equals((string) ($stored['state'] ?? ''), $state) || (int) ($stored['expires'] ?? 0) < time()) {
            throw new RuntimeException('Estado OAuth inválido ou expirado.');
        }
        if ($code === '') throw new RuntimeException('O Google não retornou o código de autorização.');
        $tokens = $this->http->form('https://oauth2.googleapis.com/token', [
            'client_id' => Env::get('GOOGLE_BUSINESS_CLIENT_ID'),
            'client_secret' => Env::get('GOOGLE_BUSINESS_CLIENT_SECRET'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callbackUrl(),
        ]);
        if (empty($tokens['access_token'])) throw new RuntimeException('O Google não retornou um token de acesso.');
        $this->repository->saveGoogleIntegration([
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) ($tokens['refresh_token'] ?? ''),
            'expires_at' => date('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 3600)),
        ], $userId);
    }

    public function syncGoogle(int $userId): array
    {
        $this->assertGoogleConfigured();
        $integration = $this->repository->googleIntegration(true);
        if (!$integration || ($integration['status'] ?? '') === 'DESCONECTADA') throw new RuntimeException('Conecte o Google antes de sincronizar.');

        try {
            $token = $this->googleAccessToken($integration, $userId);
            $account = preg_replace('#^accounts/#', '', Env::get('GOOGLE_BUSINESS_ACCOUNT_ID'));
            $location = preg_replace('#^locations/#', '', Env::get('GOOGLE_BUSINESS_LOCATION_ID'));
            $url = 'https://mybusiness.googleapis.com/v4/accounts/' . rawurlencode((string) $account) . '/locations/' . rawurlencode((string) $location) . '/reviews';
            $payload = $this->http->request('GET', $url, ['Authorization: Bearer ' . $token], [
                'pageSize' => 5,
                'orderBy' => 'updateTime desc',
            ]);
            $imported = 0;
            foreach (array_slice($payload['reviews'] ?? [], 0, 5) as $review) {
                if (!is_array($review) || empty($review['reviewId'])) continue;
                $rating = self::googleRating((string) ($review['starRating'] ?? ''));
                if ($rating === 0) continue;
                $this->repository->upsertExternalReview([
                    'provider' => 'GOOGLE',
                    'external_id' => (string) $review['reviewId'],
                    'external_url' => Env::get('GOOGLE_BUSINESS_REVIEWS_URL') ?: null,
                    'name' => ReviewValidator::cleanText((string) ($review['reviewer']['displayName'] ?? 'Cliente Google'), 160),
                    'rating' => $rating,
                    'comment' => ReviewValidator::cleanText((string) ($review['comment'] ?? ''), 5000),
                    'reviewed_at' => self::googleDate((string) ($review['createTime'] ?? '')),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+29 days')),
                ], $userId);
                $imported++;
            }
            $this->repository->finishGoogleSync(null);
            $this->repository->purgeExpiredGoogleReviews();
            return ['imported' => $imported, 'total' => (int) ($payload['totalReviewCount'] ?? $imported)];
        } catch (\Throwable $error) {
            $this->repository->finishGoogleSync($error->getMessage());
            throw $error;
        }
    }

    public function createManual(array $input, int $userId): int
    {
        $provider = strtoupper(trim((string) ($input['provider'] ?? '')));
        if (!in_array($provider, ['BOOKING','AIRBNB'], true)) throw new RuntimeException('Escolha Booking ou Airbnb.');
        if (($input['publication_authorized'] ?? '') !== '1') throw new RuntimeException('Confirme que há autorização para republicar o conteúdo.');
        $name = ReviewValidator::cleanText((string) ($input['name'] ?? ''), 160);
        $comment = ReviewValidator::cleanText((string) ($input['comment'] ?? ''), 5000);
        $rating = (int) ($input['rating'] ?? 0);
        if ($name === '' || mb_strlen($name) < 2) throw new RuntimeException('Informe o nome de exibição do hóspede.');
        if ($comment === '' || mb_strlen($comment) < 10) throw new RuntimeException('O comentário deve ter pelo menos 10 caracteres.');
        if ($rating < 1 || $rating > 5) throw new RuntimeException('A nota deve estar entre 1 e 5.');
        $reviewedAt = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['reviewed_at'] ?? ''));
        if ($reviewedAt === false || $reviewedAt > new DateTimeImmutable('today')) throw new RuntimeException('Informe uma data válida para a avaliação.');
        $sourceUrl = trim((string) ($input['external_url'] ?? ''));
        if (!filter_var($sourceUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http','https'], true)) {
            throw new RuntimeException('Informe a URL pública da avaliação original.');
        }
        $host = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $allowedDomain = $provider === 'BOOKING'
            ? ($host === 'booking.com' || str_ends_with($host, '.booking.com'))
            : ($host === 'airbnb.com' || str_ends_with($host, '.airbnb.com') || $host === 'airbnb.com.br' || str_ends_with($host, '.airbnb.com.br'));
        if (!$allowedDomain) throw new RuntimeException('A URL deve pertencer à plataforma selecionada.');
        $externalId = hash('sha256', implode('|', [$provider, $sourceUrl, $name, $reviewedAt->format('Y-m-d')]));
        return $this->repository->upsertExternalReview([
            'provider' => $provider,
            'external_id' => $externalId,
            'external_url' => $sourceUrl,
            'name' => $name,
            'rating' => $rating,
            'comment' => $comment,
            'reviewed_at' => $reviewedAt->format('Y-m-d 12:00:00'),
            'expires_at' => null,
        ], $userId);
    }

    public function disconnectGoogle(): void
    {
        $this->repository->disconnectGoogleIntegration();
    }

    private function googleAccessToken(array $integration, int $userId): string
    {
        $encryption = new EncryptionService();
        $accessToken = $encryption->decrypt($integration['access_token_encrypted'] ?? null);
        $expires = strtotime((string) ($integration['token_expires_at'] ?? '')) ?: 0;
        if ($accessToken !== '' && $expires > time() + 120) return $accessToken;
        $refreshToken = $encryption->decrypt($integration['refresh_token_encrypted'] ?? null);
        if ($refreshToken === '') throw new RuntimeException('A autorização do Google expirou. Conecte novamente.');
        $tokens = $this->http->form('https://oauth2.googleapis.com/token', [
            'client_id' => Env::get('GOOGLE_BUSINESS_CLIENT_ID'),
            'client_secret' => Env::get('GOOGLE_BUSINESS_CLIENT_SECRET'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        if (empty($tokens['access_token'])) throw new RuntimeException('Não foi possível renovar a autorização do Google.');
        $this->repository->saveGoogleIntegration([
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => date('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 3600)),
        ], $userId);
        return (string) $tokens['access_token'];
    }

    private function assertGoogleConfigured(): void
    {
        foreach (['GOOGLE_BUSINESS_CLIENT_ID','GOOGLE_BUSINESS_CLIENT_SECRET','GOOGLE_BUSINESS_ACCOUNT_ID','GOOGLE_BUSINESS_LOCATION_ID'] as $key) {
            if (Env::get($key) === '') throw new RuntimeException($key . ' não configurado.');
        }
    }

    private function callbackUrl(): string
    {
        return Env::get('GOOGLE_BUSINESS_REDIRECT_URI') ?: rtrim((string) $this->config['url'], '/') . '/admin/avaliacoes/integracoes/google/callback';
    }

    private static function googleRating(string $rating): int
    {
        return ['ONE'=>1,'TWO'=>2,'THREE'=>3,'FOUR'=>4,'FIVE'=>5][$rating] ?? 0;
    }

    private static function googleDate(string $value): string
    {
        try { return (new DateTimeImmutable($value))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return date('Y-m-d H:i:s'); }
    }
}
