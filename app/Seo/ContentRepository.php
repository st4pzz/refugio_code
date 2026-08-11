<?php
declare(strict_types=1);

namespace Refugio\Seo;

final class ContentRepository
{
    private array $landingPages;
    private array $articles;
    private array $cities;

    public function __construct()
    {
        $this->landingPages = require __DIR__ . '/Data/landing-pages.php';
        $this->articles = require __DIR__ . '/Data/articles.php';
        $this->cities = [];
        foreach (require __DIR__ . '/Data/cities.php' as $slug => $city) {
            $city['validation_errors'] = CityContentValidator::errors($city);
            $city['indexable'] = CityContentValidator::isIndexable($city);
            $this->cities[$slug] = $city;
        }
    }

    public function site(): array
    {
        return [
            'name' => 'Refúgio do Cuscuzeiro',
            'url' => 'https://www.refugiodocuscuzeiro.com.br',
            'logo' => '/assets/images/logo_refugio.webp',
            'property_image' => '/assets/images/seo/chacara-refugio-cuscuzeiro-analandia.webp',
            'description' => 'Chácara para aluguel por temporada em Analândia, no interior de São Paulo.',
            'same_as' => [
                'https://www.instagram.com/refugiodocuscuzeiro/',
                'https://www.tiktok.com/@refugio_do_cuscuzeiro',
                'https://www.airbnb.com/h/refugiodocuscuzeiro',
            ],
        ];
    }

    public function findPage(string $route): ?array
    {
        $route = trim($route, '/');
        if ($route === 'blog') {
            return [
                'type' => 'blog-index',
                'path' => '/blog/',
                'title' => 'Blog de Analândia: guias e dicas de viagem | Refúgio do Cuscuzeiro',
                'description' => 'Guias para conhecer Analândia: atrações, trilhas, cachoeiras, hospedagem, roteiros e dicas práticas para planejar sua viagem.',
                'h1' => 'Guias e dicas para conhecer Analândia e região',
                'intro' => 'Informação local para organizar seus passeios, escolher onde ficar e aproveitar melhor o interior de São Paulo.',
                'image' => '/assets/images/seo/pedra-do-cuscuzeiro-analandia.webp',
                'alt' => 'Formação rochosa do Cuscuzeiro em Analândia',
                'breadcrumbs' => [
                    ['label' => 'Home', 'path' => '/'],
                    ['label' => 'Blog', 'path' => '/blog/'],
                ],
                'faq' => [],
            ];
        }

        if ($route === 'chacara-perto-de') {
            return $this->cityHubPage();
        }

        if (str_starts_with($route, 'chacara-perto-de/')) {
            $slug = substr($route, strlen('chacara-perto-de/'));
            return isset($this->cities[$slug]) && ($this->cities[$slug]['active'] ?? false)
                ? $this->cityPage($this->cities[$slug])
                : null;
        }

        if (str_starts_with($route, 'blog/')) {
            $slug = substr($route, 5);
            return $this->articles[$slug] ?? null;
        }

        return $this->landingPages[$route] ?? null;
    }

    public function articles(): array
    {
        return array_values($this->articles);
    }

    public function cities(bool $indexableOnly = false): array
    {
        $cities = array_values(array_filter($this->cities, static function (array $city) use ($indexableOnly): bool {
            return ($city['active'] ?? false) === true && (!$indexableOnly || ($city['indexable'] ?? false) === true);
        }));
        usort($cities, static fn(array $left, array $right): int => [$left['priority'], $left['name']] <=> [$right['priority'], $right['name']]);
        return $cities;
    }

    public function city(string $slug): ?array
    {
        return $this->cities[$slug] ?? null;
    }

    public function related(array $slugs): array
    {
        $related = [];
        foreach ($slugs as $slug) {
            if (isset($this->articles[$slug])) {
                $related[] = $this->articles[$slug];
            }
        }
        return $related;
    }

    public function sitemapEntries(): array
    {
        $entries = [
            ['path' => '/', 'lastmod' => null],
            ['path' => '/a-chacara', 'lastmod' => null],
            ['path' => '/avaliacoes-dos-hospedes', 'lastmod' => null],
            ['path' => '/comodidades', 'lastmod' => null],
            ['path' => '/galeria-de-fotos', 'lastmod' => null],
            ['path' => '/videos-do-refugio', 'lastmod' => null],
            ['path' => '/conheca-analandia', 'lastmod' => null],
            ['path' => '/reserva-direta', 'lastmod' => null],
            ['path' => '/localizacao', 'lastmod' => null],
        ];
        foreach ($this->landingPages as $page) {
            if (!empty($page['redirect_to'])) {
                continue;
            }
            $entries[] = ['path' => $page['path'], 'lastmod' => '2026-08-11'];
        }
        $entries[] = ['path' => '/chacara-perto-de/', 'lastmod' => '2026-08-11'];
        foreach ($this->cities(true) as $city) {
            $entries[] = ['path' => '/chacara-perto-de/' . $city['slug'] . '/', 'lastmod' => $city['content_modified_at']];
        }
        $entries[] = ['path' => '/blog/', 'lastmod' => '2026-08-11'];
        foreach ($this->articles as $article) {
            $entries[] = ['path' => $article['path'], 'lastmod' => $article['modified']];
        }
        foreach (['privacidade', 'termos', 'exclusao-de-dados', 'cancelamento', 'regras'] as $policy) {
            $entries[] = ['path' => '/politicas/' . $policy, 'lastmod' => null];
        }
        return $entries;
    }

    private function cityHubPage(): array
    {
        return [
            'type' => 'city-hub',
            'indexable' => true,
            'path' => '/chacara-perto-de/',
            'title' => 'Chácaras para Alugar no Interior de SP | Refúgio do Cuscuzeiro',
            'description' => 'Encontre informações para viajar de diferentes cidades até o Refúgio do Cuscuzeiro, chácara de temporada em Analândia, interior de SP.',
            'h1' => 'Chácara para alugar no interior de São Paulo',
            'intro' => 'Compare contextos de viagem saindo de diferentes cidades paulistas e planeje um final de semana em Analândia com informações editoriais verificadas.',
            'image' => '/assets/images/seo/paisagem-analandia-cuscuzeiro.webp',
            'alt' => 'Paisagem do interior de São Paulo em Analândia',
            'breadcrumbs' => [
                ['label' => 'Home', 'path' => '/'],
                ['label' => 'Chácara perto de', 'path' => '/chacara-perto-de/'],
            ],
            'faq' => [],
        ];
    }

    private function cityPage(array $city): array
    {
        return array_merge($city, [
            'type' => 'city-landing',
            'path' => '/chacara-perto-de/' . $city['slug'] . '/',
            'breadcrumbs' => [
                ['label' => 'Home', 'path' => '/'],
                ['label' => 'Chácara perto de', 'path' => '/chacara-perto-de/'],
                ['label' => $city['name'], 'path' => '/chacara-perto-de/' . $city['slug'] . '/'],
            ],
        ]);
    }

    public function notFound(): array
    {
        return [
            'type' => 'not-found',
            'path' => '/pagina-nao-encontrada/',
            'title' => 'Página não encontrada | Refúgio do Cuscuzeiro',
            'description' => 'A página solicitada não foi encontrada.',
            'h1' => 'Página não encontrada',
            'intro' => 'O endereço pode ter mudado. Use os links abaixo para continuar navegando.',
            'image' => '/assets/images/cuscuzeiro.webp',
            'alt' => 'Paisagem natural de Analândia',
            'category' => '',
            'sections' => [[
                'heading' => 'Continue pelo site',
                'paragraphs' => ['Visite o <a href="/blog/">blog de Analândia</a>, conheça <a href="/alugar-chacara/">a chácara</a> ou volte para a <a href="/">página inicial</a>.'],
            ]],
            'faq' => [],
            'related' => [],
            'breadcrumbs' => [['label' => 'Home', 'path' => '/'], ['label' => 'Página não encontrada', 'path' => '/pagina-nao-encontrada/']],
        ];
    }
}
