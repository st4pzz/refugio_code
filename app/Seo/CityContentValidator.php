<?php
declare(strict_types=1);

namespace Refugio\Seo;

final class CityContentValidator
{
    private const REQUIRED_TEXT_FIELDS = [
        'name', 'slug', 'state', 'target_keyword', 'title', 'description', 'h1',
        'intro', 'trip_profile', 'travel_context', 'cta_title', 'image', 'alt',
        'travel_time', 'route_description', 'route_source_url', 'route_verified_at',
    ];

    public static function errors(array $city): array
    {
        $errors = [];
        foreach (self::REQUIRED_TEXT_FIELDS as $field) {
            if (trim((string) ($city[$field] ?? '')) === '') {
                $errors[] = "Campo obrigatório ausente: {$field}";
            }
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) ($city['slug'] ?? '')) !== 1) {
            $errors[] = 'Slug deve usar lowercase, caracteres ASCII e hífens.';
        }
        if ((int) ($city['distance_km'] ?? 0) <= 0) {
            $errors[] = 'Distância editorial validada é obrigatória.';
        }
        if (count($city['main_roads'] ?? []) < 1) {
            $errors[] = 'Ao menos um acesso principal é obrigatório.';
        }
        if (count($city['why_visit'] ?? []) < 2) {
            $errors[] = 'Conteúdo específico de viagem insuficiente.';
        }
        if (count($city['weekend_plan'] ?? []) !== 3) {
            $errors[] = 'Roteiro deve conter sexta, sábado e domingo.';
        }
        if (count($city['faq'] ?? []) < 4) {
            $errors[] = 'Ao menos quatro FAQs específicas são obrigatórias.';
        }
        if (count($city['nearby_cities'] ?? []) < 3 || count($city['nearby_cities'] ?? []) > 5) {
            $errors[] = 'Defina de três a cinco cidades relacionadas.';
        }
        if (count($city['secondary_keywords'] ?? []) < 3) {
            $errors[] = 'Cluster semântico insuficiente.';
        }
        if (filter_var($city['route_source_url'] ?? '', FILTER_VALIDATE_URL) === false) {
            $errors[] = 'URL da fonte de rota é inválida.';
        }
        $verifiedAt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) ($city['route_verified_at'] ?? ''));
        if ($verifiedAt === false) {
            $errors[] = 'Data de verificação da rota é inválida.';
        }
        return $errors;
    }

    public static function isIndexable(array $city): bool
    {
        return ($city['active'] ?? false) === true
            && ($city['requested_indexable'] ?? false) === true
            && self::errors($city) === [];
    }
}

