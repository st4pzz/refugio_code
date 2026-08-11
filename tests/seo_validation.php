<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Seo\ContentRepository;
use Refugio\Seo\SeoSchema;

$repository = new ContentRepository();
$site = $repository->site();
$schema = new SeoSchema($site);
$routes = [
    'alugar-chacara',
    'alugar-chacara/interior-de-sp',
    'alugar-chacara/analandia',
    'alugar-chacara/final-de-semana',
    'alugar-chacara/para-familia',
    'analandia',
    'blog',
    'chacara-perto-de',
];
foreach ($repository->articles() as $article) {
    $routes[] = trim($article['path'], '/');
}
foreach ($repository->cities() as $city) {
    $routes[] = 'chacara-perto-de/' . $city['slug'];
}
$knownInternalPaths = ['', 'reserva/solicitar', 'contato/whatsapp'];
foreach ($repository->sitemapEntries() as $entry) {
    $knownInternalPaths[] = trim($entry['path'], '/');
}
$knownInternalPaths = array_fill_keys(array_unique($knownInternalPaths), true);

$errors = [];
$titles = [];
$descriptions = [];
foreach ($routes as $route) {
    $page = $repository->findPage($route);
    if ($page === null) {
        $errors[] = "Rota sem dados: {$route}";
        continue;
    }
    if (isset($titles[$page['title']])) {
        $errors[] = "Title duplicado: {$page['title']}";
    }
    if (isset($descriptions[$page['description']])) {
        $errors[] = "Description duplicada: {$page['description']}";
    }
    $titles[$page['title']] = true;
    $descriptions[$page['description']] = true;
    if (!is_file(BASE_PATH . $page['image'])) {
        $errors[] = "Imagem ausente em {$route}: {$page['image']}";
    }
    try {
        json_encode($schema->forPage($page, $repository), JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $errors[] = "JSON-LD inválido em {$route}: {$error->getMessage()}";
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/render_seo_page.php') . ' ' . escapeshellarg($route);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $html = implode("\n", $output);
    if ($exitCode !== 0 || $html === '') {
        $errors[] = "Falha ao renderizar {$route}";
        continue;
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $checks = [
        'title' => $dom->getElementsByTagName('title')->length === 1,
        'description' => $xpath->query('//meta[@name="description"]')->length === 1,
        'canonical' => $xpath->query('//link[@rel="canonical"]')->length === 1,
        'h1' => $dom->getElementsByTagName('h1')->length === 1,
        'json-ld' => $xpath->query('//script[@type="application/ld+json"]')->length === 1,
        'og-title' => $xpath->query('//meta[@property="og:title"]')->length === 1,
        'twitter-card' => $xpath->query('//meta[@name="twitter:card"]')->length === 1,
    ];
    foreach ($checks as $name => $passed) {
        if (!$passed) {
            $errors[] = "{$route}: verificação {$name} falhou";
        }
    }
    foreach ($xpath->query('//a[@href]') as $anchor) {
        $href = $anchor->getAttribute('href');
        if ($href === '' || $href[0] === '#' || !str_starts_with($href, '/')) {
            continue;
        }
        $linkedPath = trim((string) parse_url($href, PHP_URL_PATH), '/');
        if (!isset($knownInternalPaths[$linkedPath])) {
            $errors[] = "Link interno sem rota conhecida em {$route}: {$href}";
        }
    }
    $canonical = $xpath->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href');
    if ($canonical !== $site['url'] . $page['path']) {
        $errors[] = "Canonical incorreto em {$route}: {$canonical}";
    }
}

if (count($repository->articles()) !== 10) {
    $errors[] = 'O blog deve conter exatamente os 10 artigos iniciais.';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("SEO validado: %d URLs, 10 artigos, metadados únicos, imagens e JSON-LD.\n", count($routes)));
