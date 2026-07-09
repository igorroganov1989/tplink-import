<?php

namespace Sprint\Migration;

class YdTplinkImport20260709132500 extends Version
{
    protected $author = 'yd';

    protected $description = 'Set HW_VERSION as single-value and align HWxregion rule';

    protected $moduleVersion = '5.0.0';

    public function up(): void
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('tplink_catalog_stage', 'test_catalog');

        if (!$iblockId) {
            return;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'HW-ревизия',
            'CODE' => 'HW_VERSION',
            'PROPERTY_TYPE' => 'L',
            'MULTIPLE' => 'N',
        ]);
    }

    public function down(): void
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('tplink_catalog_stage', 'test_catalog');

        if (!$iblockId) {
            return;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'HW-ревизии',
            'CODE' => 'HW_VERSION',
            'PROPERTY_TYPE' => 'L',
            'MULTIPLE' => 'Y',
        ]);
    }
}
