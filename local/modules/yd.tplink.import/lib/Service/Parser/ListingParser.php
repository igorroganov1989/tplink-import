<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Yd\TplinkImport\Domain\Config\ImportConfig;

final class ListingParser
{
    public function __construct(
        private readonly HttpFetcher $httpFetcher = new HttpFetcher(),
    ) {
    }

    /** @return list<string> */
    public function collectProductUrls(): array
    {
        $urls = [];
        $page = 1;
        $previousCount = 0;

        while ($page <= 20) {
            $html = $this->httpFetcher->get(HtmlText::listingFragmentUrl($page));
            $found = $this->extractProductUrls($html);

            if ($found === []) {
                break;
            }

            $newCount = count(array_diff($found, $urls));
            $urls = array_values(array_unique([...$urls, ...$found]));

            if ($newCount === 0 && $page > 1) {
                break;
            }

            if (count($found) < 3 && $page > 1) {
                break;
            }

            $previousCount = count($urls);
            ++$page;

            if ($page > 2 && count($found) === $previousCount && $newCount === 0) {
                break;
            }
        }

        sort($urls);

        if ($urls === []) {
            throw new \RuntimeException('No product URLs found on listing page: ' . ImportConfig::SOURCE_LISTING_URL);
        }

        return $urls;
    }

    /** @return list<string> */
    private function extractProductUrls(string $html): array
    {
        preg_match_all(
            '#href="(/kz/home-networking/wifi-router/[a-z0-9-]+/?)"#i',
            $html,
            $matches
        );

        $urls = [];
        foreach ($matches[1] ?? [] as $path) {
            $urls[] = HtmlText::normalizeUrl($path);
        }

        return array_values(array_unique($urls));
    }
}
