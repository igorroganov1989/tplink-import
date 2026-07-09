<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Yd\TplinkImport\Domain\Dto\ProductSpecsDto;

final class SpecExtractor
{
    public function extract(string $html): ProductSpecsDto
    {
        $section = $this->extractSpecificationsSection($html);
        if ($section === null) {
            return new ProductSpecsDto();
        }

        $rows = $this->extractRows($section);

        return new ProductSpecsDto(
            wifiStandard: $this->findRowValue($rows, ['стандарты', 'standards', 'wireless standards']),
            wifiSpeed: $this->findRowValue($rows, ['скорость wi-fi', 'wi-fi speed', 'wifi speed']),
            wanSpeed: $this->extractWanSpeed($rows),
            lanPorts: $this->extractLanPorts($rows),
        );
    }

    private function extractSpecificationsSection(string $html): ?string
    {
        if (!preg_match('#id="div_specifications"[\s\S]*?(?=</div>\s*</div>\s*</div>\s*<div class="box")#i', $html, $match)) {
            if (!preg_match('#id="div_specifications"[\s\S]{0,50000}#i', $html, $match)) {
                return null;
            }
        }

        return $match[0];
    }

    /**
     * @return array<string, string>
     */
    private function extractRows(string $section): array
    {
        preg_match_all(
            '#<tr[^>]*>\s*<th[^>]*>([\s\S]*?)</th>\s*<td[^>]*>([\s\S]*?)</td>#iu',
            $section,
            $matches,
            PREG_SET_ORDER
        );

        $rows = [];
        foreach ($matches as $match) {
            $name = HtmlText::stripTags($match[1]);
            $value = HtmlText::stripTags($match[2]);
            if ($name !== '' && $value !== '') {
                $rows[mb_strtolower($name)] = $value;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, string> $rows
     * @param list<string> $needles
     */
    private function findRowValue(array $rows, array $needles): ?string
    {
        foreach ($rows as $name => $value) {
            foreach ($needles as $needle) {
                if (str_contains($name, $needle)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $rows
     */
    private function extractWanSpeed(array $rows): ?string
    {
        $ports = $this->findRowValue($rows, ['порты ethernet', 'ethernet ports', 'interfaces']);
        if ($ports === null) {
            return null;
        }

        if (preg_match('/(\d+\s*×\s*)?порт\s*WAN[^,;]*?(\d+\s*Гбит\/с|\d+\s*Gbps|\d+\s*Мбит\/с|\d+\s*Mbps|10\/100\/1000\s*Мбит\/с)/iu', $ports, $match)) {
            return trim($match[2]);
        }

        if (preg_match('/WAN[^0-9]*((?:\d+\/)?\d+\s*(?:Gbps|Гбит\/с|Mbps|Мбит\/с))/iu', $ports, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * @param array<string, string> $rows
     */
    private function extractLanPorts(array $rows): ?int
    {
        $ports = $this->findRowValue($rows, ['порты ethernet', 'ethernet ports', 'interfaces']);
        if ($ports === null) {
            return null;
        }

        if (preg_match('/(\d+)\s*×\s*порт(?:а|ов)?\s*LAN/iu', $ports, $match)) {
            return (int)$match[1];
        }

        if (preg_match('/(\d+)\s*x\s*LAN/iu', $ports, $match)) {
            return (int)$match[1];
        }

        return null;
    }
}
