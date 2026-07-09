<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Yd\TplinkImport\Domain\Dto\ParsedCatalogItemDto;

final class CatalogCollector
{
    public function __construct(
        private readonly ListingParser $listingParser = new ListingParser(),
        private readonly ProductParser $productParser = new ProductParser(),
        private readonly SupportParser $supportParser = new SupportParser(),
    ) {
    }

    /** @return list<ParsedCatalogItemDto> */
    public function collect(): array
    {
        $productUrls = $this->listingParser->collectProductUrls();
        $items = [];

        foreach ($productUrls as $sourceUrl) {
            try {
                $product = $this->productParser->parse($sourceUrl);
                $slug = HtmlText::slugFromProductUrl($sourceUrl);
                $variants = $this->supportParser->parseVariants(
                    $slug,
                    $product['name'],
                    $product['cardHwVersions'],
                    $sourceUrl,
                );

                foreach ($variants as $variant) {
                    $needsReview = $variant->region === 'UN';
                    $hwVersions = array_values(array_filter([$variant->hwVersion]));

                    $fullArticle = $variant->fullArticle();
                    $fallbackKey = null;
                    if ($needsReview) {
                        $fallbackKey = $product['name'] . '|' . $product['sourceUrl'];
                    }

                    $items[] = new ParsedCatalogItemDto(
                        article: $product['name'],
                        fullArticle: $fullArticle,
                        name: $product['name'],
                        category: $product['category'],
                        sourceUrl: $product['sourceUrl'],
                        needsReview: $needsReview,
                        hwVersions: $hwVersions,
                        wifiStandard: $product['specs']->wifiStandard,
                        wifiSpeed: $product['specs']->wifiSpeed,
                        wanSpeed: $product['specs']->wanSpeed,
                        lanPorts: $product['specs']->lanPorts,
                        fallbackKey: $fallbackKey,
                    );
                }
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    sprintf('Failed to parse product %s: %s', $sourceUrl, $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        usort(
            $items,
            static fn (ParsedCatalogItemDto $a, ParsedCatalogItemDto $b): int => $a->fullArticle <=> $b->fullArticle
        );

        return $items;
    }
}
