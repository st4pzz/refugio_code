<?php
declare(strict_types=1);

namespace Refugio\Marketing;

interface MarketingProviderInterface
{
    public function connect(array $credentials): array;
    public function disconnect(): void;
    public function refreshCredentials(): array;
    public function listAccounts(?string $cursor = null): array;
    public function listCampaigns(?string $cursor = null): array;
    public function listAdGroups(?string $cursor = null): array;
    public function listAds(?string $cursor = null): array;
    public function getInsights(string $start, string $end, ?string $cursor = null): array;
    public function syncInsights(string $start, string $end, ?string $cursor = null): array;
    public function testConnection(): bool;
}
