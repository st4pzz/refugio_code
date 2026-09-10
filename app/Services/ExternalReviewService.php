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

    public function __construct(PDO $db)
    {
        $this->repository = new ReviewRepository($db);
        $this->http = new MarketingHttpClient();
    }

    public function googleStatus(): array
    {
        return [
            'configured' => Env::get('GOOGLE_PLACES_API_KEY') !== '' && Env::get('GOOGLE_PLACES_PLACE_ID') !== '',
        ];
    }

    public function fetchGooglePlacesReviews(): array
    {
        $this->assertGoogleConfigured();
        $payload = $this->http->request(
            'GET',
            'https://places.googleapis.com/v1/places/' . rawurlencode(Env::get('GOOGLE_PLACES_PLACE_ID')),
            [
                'X-Goog-Api-Key: ' . Env::get('GOOGLE_PLACES_API_KEY'),
                'X-Goog-FieldMask: id,displayName,reviews,rating,userRatingCount,googleMapsUri',
            ],
            ['languageCode' => 'pt-BR']
        );

        $items = [];
        foreach (array_slice($payload['reviews'] ?? [], 0, 5) as $review) {
            if (!is_array($review) || empty($review['rating'])) continue;
            $comment = ReviewValidator::cleanText((string) ($review['originalText']['text'] ?? $review['text']['text'] ?? ''), 5000);
            if ($comment === '') continue;
            $items[] = [
                'nome_exibicao' => ReviewValidator::cleanText((string) ($review['authorAttribution']['displayName'] ?? 'Cliente Google'), 160),
                'nota_geral' => max(1, min(5, (int) round((float) $review['rating']))),
                'comentario' => $comment,
                'resposta_administrador' => null,
                'checkout' => null,
                'origem' => 'GOOGLE',
                'external_url' => (string) ($review['googleMapsUri'] ?? $payload['googleMapsUri'] ?? ''),
                'avaliacao_em' => self::googleDate((string) ($review['publishTime'] ?? '')),
            ];
        }

        return [
            'items' => $items,
            'place_name' => ReviewValidator::cleanText((string) ($payload['displayName']['text'] ?? 'Refúgio do Cuscuzeiro'), 160),
            'rating' => isset($payload['rating']) ? round((float) $payload['rating'], 1) : null,
            'total' => (int) ($payload['userRatingCount'] ?? count($items)),
            'google_maps_url' => (string) ($payload['googleMapsUri'] ?? ''),
        ];
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

    private function assertGoogleConfigured(): void
    {
        foreach (['GOOGLE_PLACES_API_KEY','GOOGLE_PLACES_PLACE_ID'] as $key) {
            if (Env::get($key) === '') throw new RuntimeException($key . ' não configurado.');
        }
    }

    private static function googleDate(string $value): ?string
    {
        if ($value === '') return null;
        try { return (new DateTimeImmutable($value))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
