<?php
declare(strict_types=1);

namespace Refugio\Marketing;

use RuntimeException;

final class MarketingProviderFactory
{
    public static function make(array $integration):MarketingProviderInterface
    {
        return match(strtoupper((string)($integration['provider']??''))){'META'=>new MetaAdsProvider($integration),'GOOGLE'=>new GoogleAdsProvider($integration),'TIKTOK'=>new TikTokAdsProvider($integration),default=>throw new RuntimeException('Provedor de marketing desconhecido.')};
    }
}
