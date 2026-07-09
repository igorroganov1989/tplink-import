<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

final readonly class SupportVariantDto
{
    public function __construct(
        public string $modelName,
        public string $region,
        public string $hwVersion,
    ) {
    }

    public function fullArticle(): string
    {
        return sprintf('%s(%s) %s', $this->modelName, $this->region, $this->hwVersion);
    }
}

final class SupportParser
{
    public function __construct(
        private readonly HttpFetcher $httpFetcher = new HttpFetcher(),
    ) {
    }

    /**
     * @param list<string> $cardHwVersions
     * @return list<SupportVariantDto>
     */
    public function parseVariants(string $slug, string $modelName, array $cardHwVersions, string $sourceUrl): array
    {
        $supportUrl = HtmlText::supportUrlFromSlug($slug);

        try {
            $html = $this->httpFetcher->get($supportUrl);
        } catch (\Throwable) {
            return $this->buildFallbackVariants($modelName, $cardHwVersions);
        }

        if (!$this->isSupportPageAvailable($html)) {
            return $this->buildFallbackVariants($modelName, $cardHwVersions);
        }

        $versionPages = $this->extractVersionPages($html, $supportUrl);
        $regionMap = [];

        foreach ($versionPages as $hwVersion => $pageUrl) {
            try {
                $versionHtml = $pageUrl === $supportUrl ? $html : $this->httpFetcher->get($pageUrl);
            } catch (\Throwable) {
                continue;
            }

            foreach ($this->extractRegionHwPairs($versionHtml, $modelName, $hwVersion) as $pair) {
                $regionMap[$pair['region'] . '|' . $pair['hw']] = $pair;
            }
        }

        if ($regionMap === []) {
            return $this->buildFallbackVariants($modelName, $cardHwVersions);
        }

        $variants = [];
        foreach ($regionMap as $pair) {
            $variants[] = new SupportVariantDto(
                modelName: $modelName,
                region: $pair['region'],
                hwVersion: $pair['hw'],
            );
        }

        usort($variants, static fn (SupportVariantDto $a, SupportVariantDto $b): int => $a->fullArticle() <=> $b->fullArticle());

        return $variants;
    }

    /** @return list<SupportVariantDto> */
    private function buildFallbackVariants(string $modelName, array $cardHwVersions): array
    {
        $fallbackHw = strtoupper((string)($cardHwVersions[0] ?? 'V1'));

        return [
            new SupportVariantDto(
                modelName: $modelName,
                region: 'UN',
                hwVersion: $fallbackHw,
            ),
        ];
    }

    private function isSupportPageAvailable(string $html): bool
    {
        return str_contains($html, 'model-version-name')
            || str_contains($html, 'download-list')
            || str_contains($html, 'select-version');
    }

    /**
     * @return array<string, string> hwVersion => url
     */
    private function extractVersionPages(string $html, string $supportUrl): array
    {
        $pages = [];

        if (preg_match_all(
            '#<li[^>]*data-value="(V\d+)"[^>]*>[\s\S]*?href="([^"]+)"#iu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $pages[strtoupper($match[1])] = HtmlText::normalizeUrl($match[2]);
            }
        }

        if ($pages === [] && preg_match('#<span id=[\'"]verison-hidden[\'"]>\s*(V\d+)\s*</span>#i', $html, $match)) {
            $pages[strtoupper($match[1])] = $supportUrl;
        }

        if ($pages === []) {
            $pages[''] = $supportUrl;
        }

        return $pages;
    }

    /**
     * @return list<array{region: string, hw: string}>
     */
    private function extractRegionHwPairs(string $html, string $modelName, string $defaultHw): array
    {
        $pattern = '#'
            . preg_quote($modelName, '#')
            . '\(([A-Z]{2,3})\)_'
            . '(V\d+)'
            . '#u';

        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $pairs = [];
        foreach ($matches as $match) {
            $key = $match[1] . '|' . strtoupper($match[2]);
            $pairs[$key] = [
                'region' => strtoupper($match[1]),
                'hw' => strtoupper($match[2]),
            ];
        }

        if ($pairs === [] && $defaultHw !== '') {
            return [];
        }

        return array_values($pairs);
    }
}
