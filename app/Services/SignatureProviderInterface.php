<?php
declare(strict_types=1);

namespace Refugio\Services;

interface SignatureProviderInterface
{
    public function issueChallenge(int $contractId, string $role): array;
    public function sign(int $contractId, string $role, string $code, array $acceptance): array;
}
