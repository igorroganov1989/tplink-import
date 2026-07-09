<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Domain\Dto;

final readonly class ParsedCatalogItemDto
{
    /**
     * @param list<string> $hwVersions
     * @param list<string> $changedFields
     */
    public function __construct(
        public string $article,
        public string $fullArticle,
        public string $name,
        public string $category,
        public string $sourceUrl,
        public bool $needsReview,
        public array $hwVersions = [],
        public ?string $wifiStandard = null,
        public ?string $wifiSpeed = null,
        public ?string $wanSpeed = null,
        public ?int $lanPorts = null,
        public ?string $fallbackKey = null,
        public array $changedFields = [],
    ) {
    }

    public function uniqueKey(): string
    {
        if ($this->needsReview && $this->fallbackKey !== null) {
            return 'fallback:' . $this->fallbackKey;
        }

        return 'article:' . $this->fullArticle;
    }
}
