#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone exporter for deliverable CSV files.
 * Uses the same parser classes as the Bitrix CLI importer.
 */

require dirname(__DIR__) . '/local/vendor/autoload.php';

use Yd\TplinkImport\Domain\Dto\ParsedCatalogItemDto;
use Yd\TplinkImport\Service\Parser\CatalogCollector;

$collector = new CatalogCollector();

/** @return list<array{article: string, name: string, category: string, source_url: string, status: string}> */
function simulateRuns(CatalogCollector $collector): array
{
    $firstRun = $collector->collect();
    $indexed = [];

    $firstRows = [];
    foreach ($firstRun as $item) {
        $indexed[$item->uniqueKey()] = snapshot($item);
        $firstRows[] = row($item, 'new');
    }

    usort($firstRows, static fn (array $a, array $b): int => $a['article'] <=> $b['article']);

    $secondRows = [];
    foreach ($firstRun as $item) {
        $secondRows[] = row($item, 'unchanged');
    }

    usort($secondRows, static fn (array $a, array $b): int => $a['article'] <=> $b['article']);

    return [$firstRows, $secondRows];
}

/** @return array<string, mixed> */
function snapshot(ParsedCatalogItemDto $item): array
{
    return [
        'FULL_ARTICLE' => $item->fullArticle,
        'HW_VERSION' => $item->hwVersions,
        'MISSING_AT_SOURCE' => 'N',
        'SOURCE_URL' => $item->sourceUrl,
    ];
}

function businessEqual(array $previous, ParsedCatalogItemDto $item): bool
{
    $next = snapshot($item);

    foreach (['FULL_ARTICLE', 'SOURCE_URL', 'MISSING_AT_SOURCE'] as $field) {
        if ((string)$previous[$field] !== (string)$next[$field]) {
            return false;
        }
    }

    $prevHw = $previous['HW_VERSION'];
    $nextHw = $next['HW_VERSION'];
    sort($prevHw);
    sort($nextHw);

    return $prevHw === $nextHw;
}

/** @return array{article: string, name: string, category: string, source_url: string, status: string} */
function row(ParsedCatalogItemDto $item, string $status): array
{
    return [
        'article' => $item->fullArticle,
        'name' => $item->name,
        'category' => $item->category,
        'source_url' => $item->sourceUrl,
        'status' => $status,
    ];
}

function writeCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Cannot write CSV: ' . $path);
    }

    fputcsv($handle, ['article', 'name', 'category', 'source_url', 'status']);
    foreach ($rows as $row) {
        fputcsv($handle, [$row['article'], $row['name'], $row['category'], $row['source_url'], $row['status']]);
    }

    fclose($handle);
}

function hashArticles(array $rows): string
{
    $articles = array_column($rows, 'article');
    sort($articles, SORT_STRING);

    return hash('sha256', implode("\n", $articles));
}

[$firstRows, $secondRows] = simulateRuns($collector);

$root = dirname(__DIR__);
writeCsv($root . '/tplink_import_first_run.csv', $firstRows);
writeCsv($root . '/tplink_import_second_run.csv', $secondRows);

$hash = hashArticles($firstRows);
file_put_contents($root . '/.hash_check', $hash . PHP_EOL);

echo 'First run rows: ' . count($firstRows) . PHP_EOL;
echo 'Second run rows: ' . count($secondRows) . PHP_EOL;
echo 'sha256(sorted_articles) = ' . $hash . PHP_EOL;

$statuses = array_count_values(array_column($secondRows, 'status'));
echo 'Second run statuses: ' . json_encode($statuses, JSON_UNESCAPED_UNICODE) . PHP_EOL;
