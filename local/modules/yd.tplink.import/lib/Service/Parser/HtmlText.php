<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Yd\TplinkImport\Domain\Config\ImportConfig;

final class HtmlText
{
    public static function stripTags(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function normalizeUrl(string $url, string $base = 'https://www.tp-link.com'): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return rtrim($url, '/') . '/';
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    public static function slugFromProductUrl(string $sourceUrl): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?? '';
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));

        return (string)end($parts);
    }

    public static function supportUrlFromSlug(string $slug): string
    {
        return 'https://www.tp-link.com/kz/support/download/' . trim($slug, '/') . '/';
    }

    public static function listingFragmentUrl(int $page): string
    {
        return ImportConfig::SOURCE_LISTING_URL . '?action=getfragment&page=' . $page;
    }
}
