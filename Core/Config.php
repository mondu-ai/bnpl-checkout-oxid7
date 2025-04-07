<?php

namespace OxidEsales\MonduPayment\Core;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\GdprOptinModule\Service\ModuleSettingsInterface;
use OxidEsales\MonduPayment\Settings\Service\ModuleSettingsServiceInterface;

class Config
{
    protected const API_URL = 'https://api.mondu.ai/api/v1';
    protected const WIDGET_URL = 'https://checkout.mondu.ai/widget.js';
    protected const SANDBOX_API_URL = 'https://api.demo.mondu.ai/api/v1';
    protected const SANDBOX_WIDGET_URL = 'https://checkout.demo.mondu.ai/widget.js';
    protected const LOCAL_API_URL = 'http://localhost:3000/api/v1';
    protected const LOCAL_WIDGET_URL = 'http://localhost:3002/widget.js';
    protected const LOGO_URL = 'https://checkout.mondu.ai/logo.svg';

    public const MODULE_ID = 'oemondu';

    private $moduleSettings;

    public function __construct()
    {
        $this->moduleSettings = $this->getService(ModuleSettingsServiceInterface::class);
    }

    public function isSandbox()
    {
        return $this->moduleSettings->isSandbox();
    }

    public function getBaseApiUrl()
    {
        return $this->isSandbox() ? self::SANDBOX_API_URL : self::API_URL;
    }

    public function getWidgetUrl()
    {
        return $this->isSandbox() ? self::SANDBOX_WIDGET_URL : self::WIDGET_URL;
    }

    public function getApiUrl($url = '')
    {
        return $this->getBaseApiUrl() . '/' . $url;
    }

    public function getApiToken($shopId = null)
    {
        return $this->moduleSettings->getApiToken();
    }

    public function getModuleName()
    {
        return 'oxid';
    }

    public function getModuleVersion()
    {
        $moduleData = $this->getModuleData();

        if ($moduleData) {
            return $moduleData['version'];
        }

        return '';
    }

    public function getShopVersion()
    {
        return oxNew(\OxidEsales\EshopCommunity\Core\ShopVersion::class)::getVersion();
    }

    public function getWebhooksSecret($shopId = null)
    {
        return Registry::getConfig()->getShopConfVar(
            'oemonduWebhookSecret',
            $shopId,
            self::MODULE_ID
        );
    }

    public function setWebhooksSecret($webhookSecret, $shopId)
    {
        Registry::getConfig()->saveShopConfVar(
            'str',
            'oemonduWebhookSecret',
            $webhookSecret,
            $shopId,
            self::MODULE_ID
        );
    }

    public function getIsMerchantIdentified($shopId = null)
    {
        return Registry::getConfig()->getShopConfVar('oemonduIsMerchantIdentified', $shopId, self::MODULE_ID);
    }

    public function setIsMerchantIdentified($isMerchantIdentified, $shopId = null)
    {
        Registry::getConfig()->saveShopConfVar(
            'bool',
            'oemonduIsMerchantIdentified',
            $isMerchantIdentified,
            $shopId,
            self::MODULE_ID
        );
    }

    public function getMonduLogo()
    {
        return self::LOGO_URL;
    }

    public function isLoggingEnabled($shopId = null)
    {
        return $this->moduleSettings->isLoggingEnabled();
    }

    protected function getModuleData()
    {
        $module = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);

        if ($module->load('oemondu')) {
            return $module->getModuleData();
        }

        return null;
    }

    protected function getService(string $id): object
    {
        return ContainerFacade::get($id);
    }
}
