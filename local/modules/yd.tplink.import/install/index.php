<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

final class yd_tplink_import extends CModule
{
    public $MODULE_ID = 'yd.tplink.import';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME = 'YD';
    public $PARTNER_URI = '';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '';
        $this->MODULE_NAME = (string)Loc::getMessage('YD_TPLINK_IMPORT_MODULE_NAME');
        $this->MODULE_DESCRIPTION = (string)Loc::getMessage('YD_TPLINK_IMPORT_MODULE_DESCRIPTION');
    }

    public function DoInstall(): void
    {
        global $USER, $APPLICATION;

        if (!$USER->IsAdmin()) {
            $APPLICATION->ThrowException('Access denied');
            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall(): void
    {
        global $USER;

        if (!$USER->IsAdmin()) {
            return;
        }

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
