<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Seo\ContentRepository;

$site = (new ContentRepository())->site();
header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /minha-reserva/\n";
echo "Disallow: /reserva/\n";
echo "Disallow: /avaliar/\n";
echo "Sitemap: {$site['url']}/sitemap.xml\n";

