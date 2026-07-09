<?php

return [
    'services' => [
        'value' => [
            'yd.tplink.import.catalog.repository' => [
                'className' => \Yd\TplinkImport\Service\Import\CatalogRepository::class,
            ],
            'yd.tplink.import.import.logger' => [
                'className' => \Yd\TplinkImport\Service\Import\ImportLogger::class,
            ],
            'yd.tplink.import.catalog.importer' => [
                'className' => \Yd\TplinkImport\Service\Import\CatalogImporter::class,
                'constructorParams' => static function () {
                    $locator = \Bitrix\Main\DI\ServiceLocator::getInstance();

                    return [
                        new \Yd\TplinkImport\Service\Parser\CatalogCollector(),
                        $locator->get('yd.tplink.import.catalog.repository'),
                        $locator->get('yd.tplink.import.import.logger'),
                    ];
                },
            ],
        ],
        'readonly' => true,
    ],
    'console' => [
        'value' => [
            'commands' => [
                \Yd\TplinkImport\Infrastructure\Command\ImportCommand::class,
            ],
        ],
        'readonly' => true,
    ],
];
