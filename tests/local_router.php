<?php
declare(strict_types=1);

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicFile = dirname(__DIR__) . $path;
if ($path !== '/' && is_file($publicFile)) {
    return false;
}
if ($path === '/sitemap.xml') {
    require dirname(__DIR__) . '/seo/sitemap.php';
    return true;
}
if ($path === '/robots.txt') {
    require dirname(__DIR__) . '/seo/robots.php';
    return true;
}
$route = trim($path, '/');
if ($route === '' || in_array($route, ['a-chacara', 'avaliacoes-dos-hospedes', 'comodidades', 'galeria-de-fotos', 'videos-do-refugio', 'conheca-analandia', 'reserva-direta', 'localizacao'], true)) {
    if ($route !== '') {
        $_GET['landing_section'] = $route;
    }
    require dirname(__DIR__) . '/index.php';
    return true;
}
if ($route === 'analandia' || $route === 'blog' || str_starts_with($route, 'blog/') || $route === 'alugar-chacara' || str_starts_with($route, 'alugar-chacara/') || $route === 'chacara-perto-de' || str_starts_with($route, 'chacara-perto-de/')) {
    $_GET['route'] = $route;
    require dirname(__DIR__) . '/seo/index.php';
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
