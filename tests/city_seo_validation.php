<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Refugio\Seo\CityContentValidator;
use Refugio\Seo\ContentRepository;

$repository = new ContentRepository();
$cities = $repository->cities();
$errors = [];
$titles = [];
$descriptions = [];
$contentFingerprints = [];
$sitemapPaths = array_column($repository->sitemapEntries(), 'path');

if (count($cities) !== 9) {
    $errors[] = 'Devem existir exatamente nove cidades iniciais ativas.';
}

foreach ($cities as $city) {
    $slug = $city['slug'];
    if ($city['validation_errors'] !== []) {
        $errors[] = $slug . ': ' . implode('; ', $city['validation_errors']);
    }
    if (!$city['indexable']) {
        $errors[] = "{$slug}: conteúdo completo deveria estar indexável.";
    }
    if (isset($titles[$city['title']])) {
        $errors[] = "Title duplicado: {$city['title']}";
    }
    if (isset($descriptions[$city['description']])) {
        $errors[] = "Description duplicada: {$city['description']}";
    }
    $titles[$city['title']] = true;
    $descriptions[$city['description']] = true;
    $fingerprint = hash('sha256', implode(' ', $city['why_visit']) . implode(' ', $city['weekend_plan']) . $city['trip_profile']);
    if (isset($contentFingerprints[$fingerprint])) {
        $errors[] = "Conteúdo específico duplicado: {$slug}";
    }
    $contentFingerprints[$fingerprint] = true;
    foreach ($city['nearby_cities'] as $relatedSlug) {
        if ($repository->city($relatedSlug) === null) {
            $errors[] = "{$slug}: cidade relacionada inexistente {$relatedSlug}";
        }
    }
    $path = '/chacara-perto-de/' . $slug . '/';
    if (!in_array($path, $sitemapPaths, true)) {
        $errors[] = "Cidade indexável ausente do sitemap: {$slug}";
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/render_seo_page.php') . ' ' . escapeshellarg('chacara-perto-de/' . $slug);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $html = implode("\n", $output);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    if ($dom->getElementsByTagName('h1')->length !== 1) {
        $errors[] = "{$slug}: deve conter um H1.";
    }
    if ($xpath->query('//meta[@name="robots" and contains(@content,"index,follow")]')->length !== 1) {
        $errors[] = "{$slug}: robots index,follow ausente.";
    }
    if ($xpath->query('//body[@data-seo-landing-type="city" and @data-seo-origin-city="' . $slug . '"]')->length !== 1) {
        $errors[] = "{$slug}: atributos para GTM ausentes.";
    }
    if ($xpath->query('//nav[@aria-label="Breadcrumb"]')->length !== 1) {
        $errors[] = "{$slug}: breadcrumb ausente.";
    }
    foreach (['/alugar-chacara/analandia/', '/alugar-chacara/interior-de-sp/', '/analandia/', '/blog/o-que-fazer-em-analandia/', '/blog/onde-ficar-em-analandia/', '/reserva/solicitar'] as $requiredLink) {
        if ($xpath->query('//a[@href="' . $requiredLink . '"]')->length < 1) {
            $errors[] = "{$slug}: link obrigatório ausente {$requiredLink}";
        }
    }
}

$incomplete = $cities[0];
$incomplete['distance_km'] = null;
if (CityContentValidator::isIndexable($incomplete)) {
    $errors[] = 'Validador permitiu indexação sem distância.';
}
if (!in_array('/chacara-perto-de/', $sitemapPaths, true)) {
    $errors[] = 'Hub de cidades ausente do sitemap.';
}
if (in_array('/alugar-chacara/perto-de-sao-paulo/', $sitemapPaths, true)) {
    $errors[] = 'URL antiga de São Paulo não deve permanecer no sitemap.';
}
$legacyPage = $repository->findPage('alugar-chacara/perto-de-sao-paulo');
if (($legacyPage['redirect_to'] ?? null) !== '/chacara-perto-de/sao-paulo/') {
    $errors[] = 'Redirecionamento consolidado de São Paulo não está configurado.';
}

$statusCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/render_seo_status.php') . ' ' . escapeshellarg('chacara-perto-de/cidade-inexistente');
$statusOutput = [];
$statusCode = 0;
exec($statusCommand, $statusOutput, $statusCode);
if (trim(implode('', $statusOutput)) !== '404') {
    $errors[] = 'Cidade inexistente não retornou HTTP 404.';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'SEO por cidade validado: 9 páginas indexáveis, conteúdo único, sitemap, links, GTM e 404.' . PHP_EOL);
