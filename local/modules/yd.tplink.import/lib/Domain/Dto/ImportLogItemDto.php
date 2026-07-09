<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Domain\Dto;

use Yd\TplinkImport\Domain\Enum\ImportItemStatus;

final readonly class ImportLogItemDto
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public string $fullArticle,
        public ImportItemStatus $status,
        public string $url,
        public array $changedFields = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toLogArray(): array
    {
        return [
            'article' => $this->fullArticle,
            'status' => $this->status->value,
            'url' => $this->url,
            'changed_fields' => $this->changedFields,
        ];
    }
}
