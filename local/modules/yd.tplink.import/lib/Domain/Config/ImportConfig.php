<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Domain\Config;

final class ImportConfig
{
    public const SOURCE_LISTING_URL = 'https://www.tp-link.com/kz/home-networking/wifi-router/';
    public const IBLOCK_CODE = 'tplink_catalog_stage';
    public const IBLOCK_TYPE = 'test_catalog';
    public const SITE_ID = 's1';
    public const USER_AGENT = 'YD-TPLink-Importer/1.0 (+bitrix-cli)';
    public const HTTP_SOCKET_TIMEOUT = 15;
    public const HTTP_STREAM_TIMEOUT = 45;

    /** @var list<string> */
    public const BUSINESS_FIELDS = [
        'ARTICLE',
        'FULL_ARTICLE',
        'CATEGORY',
        'WIFI_STANDARD',
        'WIFI_SPEED',
        'WAN_SPEED',
        'LAN_PORTS',
        'SOURCE_URL',
        'HW_VERSION',
        'NEEDS_REVIEW',
        'MISSING_AT_SOURCE',
    ];
}
