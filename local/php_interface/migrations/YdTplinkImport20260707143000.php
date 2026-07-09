<?php

namespace Sprint\Migration;

class YdTplinkImport20260707143000 extends Version
{
    protected $author = 'yd';

    protected $description = 'Create test_catalog iblock tplink_catalog_stage with import properties';

    protected $moduleVersion = '5.0.0';

    public function up(): void
    {
        $helper = $this->getHelperManager();

        $helper->Iblock()->saveIblockType([
            'ID' => 'test_catalog',
            'SECTIONS' => 'N',
            'EDIT_FILE_BEFORE' => '',
            'EDIT_FILE_AFTER' => '',
            'IN_RSS' => 'N',
            'SORT' => 500,
            'LANG' => [
                'ru' => [
                    'NAME' => 'Тестовый каталог',
                    'SECTION_NAME' => 'Разделы',
                    'ELEMENT_NAME' => 'Элементы',
                ],
                'en' => [
                    'NAME' => 'Test catalog',
                    'SECTION_NAME' => 'Sections',
                    'ELEMENT_NAME' => 'Elements',
                ],
            ],
        ]);

        $iblockId = $helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'test_catalog',
            'LID' => ['s1'],
            'CODE' => 'tplink_catalog_stage',
            'API_CODE' => 'TplinkCatalogStage',
            'NAME' => 'TP-Link Catalog (Test Import)',
            'ACTIVE' => 'Y',
            'SORT' => 500,
            'VERSION' => 2,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
            'LIST_PAGE_URL' => '',
            'DETAIL_PAGE_URL' => '',
        ]);

        $hwVersionValues = [];
        for ($i = 1; $i <= 30; ++$i) {
            $value = 'V' . $i;
            $hwVersionValues[] = [
                'VALUE' => $value,
                'XML_ID' => $value,
                'SORT' => $i * 10,
                'DEF' => 'N',
            ];
        }

        $properties = [
            [
                'NAME' => 'Артикул',
                'CODE' => 'ARTICLE',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
            ],
            [
                'NAME' => 'Полный артикул',
                'CODE' => 'FULL_ARTICLE',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
            ],
            [
                'NAME' => 'Категория',
                'CODE' => 'CATEGORY',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
            ],
            [
                'NAME' => 'Wi-Fi стандарт',
                'CODE' => 'WIFI_STANDARD',
                'PROPERTY_TYPE' => 'S',
            ],
            [
                'NAME' => 'Скорость Wi-Fi',
                'CODE' => 'WIFI_SPEED',
                'PROPERTY_TYPE' => 'S',
            ],
            [
                'NAME' => 'Скорость WAN-порта',
                'CODE' => 'WAN_SPEED',
                'PROPERTY_TYPE' => 'S',
            ],
            [
                'NAME' => 'Кол-во LAN-портов',
                'CODE' => 'LAN_PORTS',
                'PROPERTY_TYPE' => 'N',
            ],
            [
                'NAME' => 'Ссылка на карточку',
                'CODE' => 'SOURCE_URL',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
            ],
            [
                'NAME' => 'Дата синхронизации',
                'CODE' => 'SYNCED_AT',
                'PROPERTY_TYPE' => 'S',
                'IS_REQUIRED' => 'Y',
            ],
            [
                'NAME' => 'Не найден на источнике',
                'CODE' => 'MISSING_AT_SOURCE',
                'PROPERTY_TYPE' => 'L',
                'IS_REQUIRED' => 'Y',
                'VALUES' => [
                    ['VALUE' => 'N', 'XML_ID' => 'N', 'SORT' => 100, 'DEF' => 'Y'],
                    ['VALUE' => 'Y', 'XML_ID' => 'Y', 'SORT' => 200, 'DEF' => 'N'],
                ],
            ],
            [
                'NAME' => 'HW-ревизии',
                'CODE' => 'HW_VERSION',
                'PROPERTY_TYPE' => 'L',
                'MULTIPLE' => 'N',
                'VALUES' => $hwVersionValues,
            ],
            [
                'NAME' => 'Требует проверки',
                'CODE' => 'NEEDS_REVIEW',
                'PROPERTY_TYPE' => 'L',
                'IS_REQUIRED' => 'Y',
                'VALUES' => [
                    ['VALUE' => 'N', 'XML_ID' => 'N', 'SORT' => 100, 'DEF' => 'Y'],
                    ['VALUE' => 'Y', 'XML_ID' => 'Y', 'SORT' => 200, 'DEF' => 'N'],
                ],
            ],
        ];

        foreach ($properties as $property) {
            $helper->Iblock()->saveProperty($iblockId, $property);
        }
    }

    public function down(): void
    {
        $helper = $this->getHelperManager();
        $helper->Iblock()->deleteIblockIfExists('tplink_catalog_stage', 'test_catalog');
        $helper->Iblock()->deleteIblockTypeIfExists('test_catalog');
    }
}
