<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Domain\Dto;

final readonly class ProductSpecsDto
{
    public function __construct(
        public ?string $wifiStandard = null,
        public ?string $wifiSpeed = null,
        public ?string $wanSpeed = null,
        public ?int $lanPorts = null,
    ) {
    }
}
