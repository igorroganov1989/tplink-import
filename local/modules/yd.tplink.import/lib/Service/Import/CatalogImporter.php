<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Import;

use Yd\TplinkImport\Domain\Config\ImportConfig;
use Yd\TplinkImport\Domain\Dto\ImportLogItemDto;
use Yd\TplinkImport\Domain\Dto\ParsedCatalogItemDto;
use Yd\TplinkImport\Domain\Enum\ImportItemStatus;
use Yd\TplinkImport\Service\Parser\CatalogCollector;

final class CatalogImporter
{
    public function __construct(
        private readonly CatalogCollector $collector,
        private readonly CatalogRepository $repository,
        private readonly ImportLogger $logger,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $syncedAt = $startedAt->format('c');
        $errors = [];
        $items = [];
        $counters = [
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'missing' => 0,
            'errors' => 0,
        ];

        try {
            $parsedItems = $this->collector->collect();
        } catch (\Throwable $exception) {
            $finishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $log = [
                'started_at' => $startedAt->format('c'),
                'finished_at' => $finishedAt->format('c'),
                'source_url' => ImportConfig::SOURCE_LISTING_URL,
                'counters' => array_merge($counters, ['errors' => 1]),
                'items' => [],
                'errors' => [['message' => $exception->getMessage()]],
            ];
            $this->logger->write($log);

            throw $exception;
        }

        $existing = $this->repository->loadExistingIndexed();
        $seenKeys = [];

        foreach ($parsedItems as $parsedItem) {
            $seenKeys[$parsedItem->uniqueKey()] = true;

            try {
                $result = $this->repository->upsert($parsedItem, $syncedAt, $existing);
                $status = ImportItemStatus::from($result['status']);
                ++$counters[$status->value];

                $items[] = new ImportLogItemDto(
                    fullArticle: $parsedItem->fullArticle,
                    status: $status,
                    url: $parsedItem->sourceUrl,
                    changedFields: $result['changed_fields'],
                );

                if ($result['element'] !== null) {
                    $existing[$parsedItem->uniqueKey()] = $result['element'];
                }
            } catch (\Throwable $exception) {
                ++$counters['errors'];
                $errors[] = [
                    'article' => $parsedItem->fullArticle,
                    'url' => $parsedItem->sourceUrl,
                    'message' => $exception->getMessage(),
                ];
                $items[] = new ImportLogItemDto(
                    fullArticle: $parsedItem->fullArticle,
                    status: ImportItemStatus::Error,
                    url: $parsedItem->sourceUrl,
                );
            }
        }

        foreach ($this->repository->markMissing($seenKeys, $syncedAt, $existing) as $missing) {
            ++$counters['missing'];
            $items[] = new ImportLogItemDto(
                fullArticle: $missing['full_article'],
                status: ImportItemStatus::Missing,
                url: $missing['source_url'],
                changedFields: ['MISSING_AT_SOURCE'],
            );
        }

        $finishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $log = [
            'started_at' => $startedAt->format('c'),
            'finished_at' => $finishedAt->format('c'),
            'source_url' => ImportConfig::SOURCE_LISTING_URL,
            'counters' => $counters,
            'items' => array_map(static fn (ImportLogItemDto $item): array => $item->toLogArray(), $items),
            'errors' => $errors,
        ];

        $path = $this->logger->write($log);

        return [
            'log_path' => $path,
            'counters' => $counters,
            'items' => $items,
        ];
    }
}
