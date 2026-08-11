<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Seo\ContentRepository;
use Refugio\Seo\SeoSchema;

$repository = new ContentRepository();
$route = trim((string) ($_GET['route'] ?? ''), '/');
$page = $repository->findPage($route);

if ($page === null) {
    http_response_code(404);
    $page = $repository->notFound();
}

if (!empty($page['redirect_to'])) {
    header('Location: ' . $page['redirect_to'], true, 301);
    exit;
}

$site = $repository->site();
$articles = $repository->articles();
$schema = new SeoSchema($site);
$jsonLd = $schema->forPage($page, $repository);

$view = match ($page['type'] ?? '') {
    'blog-index' => 'blog-index.php',
    'city-hub' => 'city-hub.php',
    'city-landing' => 'city-page.php',
    default => 'page.php',
};
require BASE_PATH . '/app/Views/seo/' . $view;
