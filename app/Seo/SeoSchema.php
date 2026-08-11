<?php
declare(strict_types=1);

namespace Refugio\Seo;

final class SeoSchema
{
    public function __construct(private readonly array $site)
    {
    }

    public function forPage(array $page, ContentRepository $repository): array
    {
        $url = $this->site['url'] . $page['path'];
        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $this->site['url'] . '/#organization',
                'name' => $this->site['name'],
                'url' => $this->site['url'] . '/',
                'logo' => $this->site['url'] . $this->site['logo'],
                'sameAs' => $this->site['same_as'],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $this->site['url'] . '/#website',
                'url' => $this->site['url'] . '/',
                'name' => $this->site['name'],
                'inLanguage' => 'pt-BR',
                'publisher' => ['@id' => $this->site['url'] . '/#organization'],
            ],
        ];

        if (in_array($page['type'], ['landing', 'tourism-hub', 'city-hub', 'city-landing'], true)) {
            $graph[] = [
                '@type' => ['LodgingBusiness', 'VacationRental'],
                '@id' => $this->site['url'] . '/#lodging',
                'name' => $this->site['name'],
                'url' => $this->site['url'] . '/',
                'image' => $this->site['url'] . $this->site['property_image'],
                'description' => $this->site['description'],
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Analândia',
                    'addressRegion' => 'SP',
                    'addressCountry' => 'BR',
                ],
            ];
        }

        if (($page['type'] ?? '') === 'article') {
            $graph[] = [
                '@type' => 'BlogPosting',
                '@id' => $url . '#article',
                'headline' => $page['h1'],
                'description' => $page['description'],
                'image' => $this->site['url'] . $page['image'],
                'datePublished' => $page['date'],
                'dateModified' => $page['modified'],
                'inLanguage' => 'pt-BR',
                'mainEntityOfPage' => $url,
                'author' => ['@id' => $this->site['url'] . '/#organization'],
                'publisher' => ['@id' => $this->site['url'] . '/#organization'],
            ];
        }

        $breadcrumbs = [];
        foreach ($page['breadcrumbs'] ?? [] as $position => $crumb) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $crumb['label'],
                'item' => $this->site['url'] . $crumb['path'],
            ];
        }
        if ($breadcrumbs !== []) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $url . '#breadcrumb',
                'itemListElement' => $breadcrumbs,
            ];
        }

        if (!empty($page['faq'])) {
            $entities = [];
            foreach ($page['faq'] as $faq) {
                $entities[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($faq['answer']),
                    ],
                ];
            }
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => $entities];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
