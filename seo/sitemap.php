<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Seo\ContentRepository;

$repository = new ContentRepository();
$site = $repository->site();

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($repository->sitemapEntries() as $entry): ?>
  <url>
    <loc><?= e($site['url'] . $entry['path']) ?></loc>
<?php if (!empty($entry['lastmod'])): ?>
    <lastmod><?= e($entry['lastmod']) ?></lastmod>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>

