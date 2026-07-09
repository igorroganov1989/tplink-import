<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Import;

use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;
use CIBlockElement;
use Yd\TplinkImport\Domain\Config\ImportConfig;
use Yd\TplinkImport\Domain\Dto\ParsedCatalogItemDto;

final class CatalogRepository
{
    private ?int $iblockId = null;

    /** @var array<string, int> */
    private array $enumCache = [];

    public function getIblockId(): int
    {
        if ($this->iblockId !== null) {
            return $this->iblockId;
        }

        Loader::includeModule('iblock');

        $row = \Bitrix\Iblock\IblockTable::getList([
            'filter' => ['=CODE' => ImportConfig::IBLOCK_CODE],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            throw new \RuntimeException(
                'Iblock "' . ImportConfig::IBLOCK_CODE . '" not found. Run sprint.migration first.'
            );
        }

        $this->iblockId = (int)$row['ID'];

        return $this->iblockId;
    }

    /** @return array<string, array<string, mixed>> */
    public function loadExistingIndexed(): array
    {
        $iblockId = $this->getIblockId();
        $indexed = [];

        $elements = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId],
            false,
            false,
            [
                'ID',
                'NAME',
                'PROPERTY_ARTICLE',
                'PROPERTY_FULL_ARTICLE',
                'PROPERTY_CATEGORY',
                'PROPERTY_WIFI_STANDARD',
                'PROPERTY_WIFI_SPEED',
                'PROPERTY_WAN_SPEED',
                'PROPERTY_LAN_PORTS',
                'PROPERTY_SOURCE_URL',
                'PROPERTY_SYNCED_AT',
                'PROPERTY_MISSING_AT_SOURCE',
                'PROPERTY_HW_VERSION',
                'PROPERTY_NEEDS_REVIEW',
            ]
        );

        while ($element = $elements->Fetch()) {
            $fullArticle = (string)($element['PROPERTY_FULL_ARTICLE_VALUE'] ?? '');
            $sourceUrl = (string)($element['PROPERTY_SOURCE_URL_VALUE'] ?? '');
            $needsReview = $this->normalizeYn($element['PROPERTY_NEEDS_REVIEW_VALUE'] ?? 'N') === 'Y';
            $name = (string)($element['NAME'] ?? '');

            $key = $needsReview
                ? 'fallback:' . $name . '|' . $sourceUrl
                : 'article:' . $fullArticle;

            $indexed[$key] = [
                'ID' => (int)$element['ID'],
                'NAME' => $name,
                'ARTICLE' => (string)($element['PROPERTY_ARTICLE_VALUE'] ?? ''),
                'FULL_ARTICLE' => $fullArticle,
                'CATEGORY' => (string)($element['PROPERTY_CATEGORY_VALUE'] ?? ''),
                'WIFI_STANDARD' => (string)($element['PROPERTY_WIFI_STANDARD_VALUE'] ?? ''),
                'WIFI_SPEED' => (string)($element['PROPERTY_WIFI_SPEED_VALUE'] ?? ''),
                'WAN_SPEED' => (string)($element['PROPERTY_WAN_SPEED_VALUE'] ?? ''),
                'LAN_PORTS' => $element['PROPERTY_LAN_PORTS_VALUE'] !== null && $element['PROPERTY_LAN_PORTS_VALUE'] !== ''
                    ? (int)$element['PROPERTY_LAN_PORTS_VALUE']
                    : null,
                'SOURCE_URL' => $sourceUrl,
                'SYNCED_AT' => (string)($element['PROPERTY_SYNCED_AT_VALUE'] ?? ''),
                'MISSING_AT_SOURCE' => $this->normalizeYn($element['PROPERTY_MISSING_AT_SOURCE_VALUE'] ?? 'N'),
                'HW_VERSION' => $this->normalizeMultiValue($element['PROPERTY_HW_VERSION_VALUE'] ?? []),
                'NEEDS_REVIEW' => $this->normalizeYn($element['PROPERTY_NEEDS_REVIEW_VALUE'] ?? 'N'),
            ];
        }

        return $indexed;
    }

    /**
     * @param array<string, array<string, mixed>> $existing
     * @return array{status: string, changed_fields: list<string>, element: ?array<string, mixed>}
     */
    public function upsert(ParsedCatalogItemDto $item, string $syncedAt, array &$existing): array
    {
        $key = $item->uniqueKey();
        $current = $existing[$key] ?? null;
        $changedFields = $current !== null ? $this->diffBusinessFields($current, $item) : [];

        $propertyValues = $this->buildPropertyValues($item, $syncedAt, 'N');

        if ($current === null) {
            $element = new CIBlockElement();
            $id = (int)$element->Add([
                'IBLOCK_ID' => $this->getIblockId(),
                'NAME' => $item->name,
                'ACTIVE' => 'Y',
                'CODE' => $this->buildElementCode($item),
                'PROPERTY_VALUES' => $propertyValues,
            ]);

            if ($id <= 0) {
                throw new \RuntimeException('Element create failed: ' . $element->LAST_ERROR);
            }

            $snapshot = $this->snapshotFromItem($item, $syncedAt, 'N');
            $snapshot['ID'] = $id;
            $existing[$key] = $snapshot;

            return [
                'status' => 'new',
                'changed_fields' => ImportConfig::BUSINESS_FIELDS,
                'element' => $snapshot,
            ];
        }

        if ($changedFields === []) {
            $this->touchSyncedAt((int)$current['ID'], $syncedAt, 'N');

            return [
                'status' => 'unchanged',
                'changed_fields' => [],
                'element' => $current,
            ];
        }

        $element = new CIBlockElement();
        $updated = $element->Update((int)$current['ID'], [
            'NAME' => $item->name,
            'PROPERTY_VALUES' => $propertyValues,
        ]);

        if (!$updated) {
            throw new \RuntimeException('Element update failed: ' . $element->LAST_ERROR);
        }

        $snapshot = $this->snapshotFromItem($item, $syncedAt, 'N');
        $snapshot['ID'] = (int)$current['ID'];
        $existing[$key] = $snapshot;

        return [
            'status' => 'updated',
            'changed_fields' => $changedFields,
            'element' => $snapshot,
        ];
    }

    /**
     * @param array<string, true> $seenKeys
     * @param array<string, array<string, mixed>> $existing
     * @return list<array{full_article: string, source_url: string}>
     */
    public function markMissing(array $seenKeys, string $syncedAt, array &$existing): array
    {
        $result = [];

        foreach ($existing as $key => $element) {
            if (isset($seenKeys[$key])) {
                continue;
            }

            if (($element['MISSING_AT_SOURCE'] ?? 'N') === 'Y') {
                continue;
            }

            $elementApi = new CIBlockElement();
            $elementApi->Update((int)$element['ID'], [
                'PROPERTY_VALUES' => [
                    'MISSING_AT_SOURCE' => $this->enumId('MISSING_AT_SOURCE', 'Y'),
                    'SYNCED_AT' => $syncedAt,
                ],
            ]);

            $element['MISSING_AT_SOURCE'] = 'Y';
            $element['SYNCED_AT'] = $syncedAt;
            $existing[$key] = $element;

            $result[] = [
                'full_article' => (string)$element['FULL_ARTICLE'],
                'source_url' => (string)$element['SOURCE_URL'],
            ];
        }

        return $result;
    }

    private function touchSyncedAt(int $elementId, string $syncedAt, string $missing): void
    {
        $element = new CIBlockElement();
        $element->Update($elementId, [
            'PROPERTY_VALUES' => [
                'SYNCED_AT' => $syncedAt,
                'MISSING_AT_SOURCE' => $this->enumId('MISSING_AT_SOURCE', $missing),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function buildPropertyValues(ParsedCatalogItemDto $item, string $syncedAt, string $missing): array
    {
        return [
            'FULL_ARTICLE' => $item->fullArticle,
            'ARTICLE' => $item->article,
            'CATEGORY' => $item->category,
            'WIFI_STANDARD' => $item->wifiStandard,
            'WIFI_SPEED' => $item->wifiSpeed,
            'WAN_SPEED' => $item->wanSpeed,
            'LAN_PORTS' => $item->lanPorts,
            'SOURCE_URL' => $item->sourceUrl,
            'SYNCED_AT' => $syncedAt,
            'MISSING_AT_SOURCE' => $this->enumId('MISSING_AT_SOURCE', $missing),
            'HW_VERSION' => $this->resolveHwVersionEnumIds($item->hwVersions),
            'NEEDS_REVIEW' => $this->enumId('NEEDS_REVIEW', $item->needsReview ? 'Y' : 'N'),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotFromItem(ParsedCatalogItemDto $item, string $syncedAt, string $missing): array
    {
        return [
            'NAME' => $item->name,
            'ARTICLE' => $item->article,
            'FULL_ARTICLE' => $item->fullArticle,
            'CATEGORY' => $item->category,
            'WIFI_STANDARD' => (string)($item->wifiStandard ?? ''),
            'WIFI_SPEED' => (string)($item->wifiSpeed ?? ''),
            'WAN_SPEED' => (string)($item->wanSpeed ?? ''),
            'LAN_PORTS' => $item->lanPorts,
            'SOURCE_URL' => $item->sourceUrl,
            'SYNCED_AT' => $syncedAt,
            'MISSING_AT_SOURCE' => $missing,
            'HW_VERSION' => $item->hwVersions,
            'NEEDS_REVIEW' => $item->needsReview ? 'Y' : 'N',
        ];
    }

    /**
     * @param array<string, mixed> $current
     * @return list<string>
     */
    private function diffBusinessFields(array $current, ParsedCatalogItemDto $item): array
    {
        $changed = [];
        $next = $this->snapshotFromItem($item, (string)($current['SYNCED_AT'] ?? ''), 'N');

        foreach (ImportConfig::BUSINESS_FIELDS as $field) {
            $currentValue = $current[$field] ?? null;
            $nextValue = $next[$field] ?? null;

            if ($field === 'HW_VERSION') {
                $currentList = $this->normalizeMultiValue($currentValue);
                $nextList = $this->normalizeMultiValue($nextValue);
                sort($currentList);
                sort($nextList);
                if ($currentList !== $nextList) {
                    $changed[] = $field;
                }
                continue;
            }

            if ((string)$currentValue !== (string)$nextValue) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function buildElementCode(ParsedCatalogItemDto $item): string
    {
        $base = $item->needsReview
            ? ($item->fallbackKey ?? $item->fullArticle)
            : $item->fullArticle;

        $code = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $base));
        $code = trim($code, '-');

        return substr($code !== '' ? $code : 'item', 0, 90);
    }

    private function enumId(string $propertyCode, string $xmlId): int
    {
        $cacheKey = $propertyCode . ':' . $xmlId;
        if (isset($this->enumCache[$cacheKey])) {
            return $this->enumCache[$cacheKey];
        }

        $property = PropertyTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $this->getIblockId(),
                '=CODE' => $propertyCode,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        if (!$property) {
            throw new \RuntimeException('Property not found: ' . $propertyCode);
        }

        $enum = PropertyEnumerationTable::getList([
            'filter' => [
                '=PROPERTY_ID' => (int)$property['ID'],
                '=XML_ID' => $xmlId,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        if (!$enum) {
            if ($propertyCode === 'HW_VERSION' && preg_match('/^V\d+$/', $xmlId)) {
                $enumId = (int)(new \CIBlockPropertyEnum())->Add([
                    'PROPERTY_ID' => (int)$property['ID'],
                    'VALUE' => $xmlId,
                    'XML_ID' => $xmlId,
                    'SORT' => (int)substr($xmlId, 1),
                ]);
                if ($enumId <= 0) {
                    throw new \RuntimeException('Failed to create HW_VERSION enum: ' . $xmlId);
                }
                $this->enumCache[$cacheKey] = $enumId;

                return $enumId;
            }

            throw new \RuntimeException(sprintf('Enum %s for %s not found', $xmlId, $propertyCode));
        }

        $this->enumCache[$cacheKey] = (int)$enum['ID'];

        return $this->enumCache[$cacheKey];
    }

    /**
     * @param list<string> $versions
     * @return list<int>
     */
    private function resolveHwVersionEnumIds(array $versions): array
    {
        $ids = [];
        foreach ($versions as $version) {
            if ($version === '') {
                continue;
            }
            $ids[] = $this->enumId('HW_VERSION', strtoupper($version));
        }

        return $ids;
    }

    /** @return list<string> */
    private function normalizeMultiValue(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            return [(string)$value];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_array($item) && isset($item['VALUE'])) {
                $result[] = (string)$item['VALUE'];
            } elseif (is_scalar($item)) {
                $result[] = (string)$item;
            }
        }

        return array_values(array_unique(array_filter($result)));
    }

    private function normalizeYn(mixed $value): string
    {
        if (is_array($value) && isset($value['VALUE'])) {
            return (string)$value['VALUE'];
        }

        return (string)$value;
    }
}
