<?php
declare(strict_types=1);

namespace Refugio\Services;

use RuntimeException;

final class GoogleAdsScriptTemplate
{
    public static function render(string $appUrl, array $campaignNames): string
    {
        $template = file_get_contents(BASE_PATH . '/resources/google-ads-scripts/refugio-campaign-sync.js');
        if ($template === false) {
            throw new RuntimeException('Template do Google Ads Script nao encontrado.');
        }
        $endpoint = rtrim($appUrl, '/') . '/api/marketing/google-ads-script';
        $names = json_encode(array_values($campaignNames), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return str_replace(['__ENDPOINT_URL__', '__CAMPAIGN_NAMES__'], [$endpoint, $names], $template);
    }
}
