<?php

namespace Sprint\Migration;

class YdTplinkImport20260709121500 extends Version
{
    protected $author = 'yd';

    protected $description = 'Add ARTICLE property to tplink_catalog_stage';

    protected $moduleVersion = '5.0.0';

    public function up(): void
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('tplink_catalog_stage', 'test_catalog');

        if (!$iblockId) {
            return;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Артикул',
            'CODE' => 'ARTICLE',
            'PROPERTY_TYPE' => 'S',
            'IS_REQUIRED' => 'Y',
        ]);
    }

    public function down(): void
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('tplink_catalog_stage', 'test_catalog');

        if (!$iblockId) {
            return;
        }

        $helper->Iblock()->deletePropertyIfExists($iblockId, 'ARTICLE');
    }
}
