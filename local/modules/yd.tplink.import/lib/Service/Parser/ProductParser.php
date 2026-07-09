<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Yd\TplinkImport\Domain\Dto\ProductSpecsDto;

final class ProductParser
{
    public function __construct(
        private readonly HttpFetcher $httpFetcher = new HttpFetcher(),
        private readonly SpecExtractor $specExtractor = new SpecExtractor(),
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     category: string,
     *     sourceUrl: string,
     *     cardHwVersions: list<string>,
     *     specs: ProductSpecsDto
     * }
     */
    public function parse(string $sourceUrl): array
    {
        $html = $this->httpFetcher->get($sourceUrl);

        $name = $this->extractModelName($html) ?? $this->extractTitleModel($html);
        if ($name === null || $name === '') {
            throw new \RuntimeException('Model name not found for ' . $sourceUrl);
        }

        return [
            'name' => $name,
            'category' => $this->extractCategory($html),
            'sourceUrl' => HtmlText::normalizeUrl($sourceUrl),
            'cardHwVersions' => $this->extractCardHwVersions($html),
            'specs' => $this->specExtractor->extract($html),
        ];
    }

    private function extractModelName(string $html): ?string
    {
        if (preg_match('#id="ga-product-name"[^>]*data-name="([^"]+)"#i', $html, $match)) {
            return trim($match[1]);
        }

        if (preg_match('#data-name="([^"]+)"[^>]*data-version="#i', $html, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function extractTitleModel(string $html): ?string
    {
        if (!preg_match('#<title>([^<|]+)#i', $html, $match)) {
            return null;
        }

        return trim($match[1]);
    }

    private function extractCategory(string $html): string
    {
        if (preg_match('#class="tp-breadcrumb[\s\S]*?</(?:nav|div)>#i', $html, $block)) {
            preg_match_all('#<a[^>]*>([^<]+)</a>#u', $block[0], $links);
            $items = array_values(array_filter(array_map('trim', $links[1] ?? [])));
            if ($items !== []) {
                $last = end($items);
                if (is_string($last) && $last !== '') {
                    return $last;
                }
            }
        }

        if (preg_match('#home-networking/wifi-router/#i', $html)) {
            return 'Роутеры Wi-Fi';
        }

        return 'Роутеры Wi-Fi';
    }

    /** @return list<string> */
    private function extractCardHwVersions(string $html): array
    {
        preg_match_all(
            '#data-name="[^"]+"[^>]*data-version="(V\d+)"#i',
            $html,
            $matches
        );

        $versions = array_values(array_unique(array_map('strtoupper', $matches[1] ?? [])));
        sort($versions, SORT_NATURAL);

        return $versions;
    }
}
