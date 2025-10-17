<?php

namespace OxidEsales\MonduPayment\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\SettingServiceInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setting\Event\SettingChangedEvent;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\MonduPayment\Core\Config;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\MonduPayment\Model\Webhook;

class ModuleConfiguration extends ModuleConfiguration_parent
{
    protected const REQUIRED_WEBHOOK_TOPICS = ['order', 'invoice/created', 'invoice/canceled'];

    private MonduClient $_client;

    public function saveConfVars()
    {
        $oldValues = $this->getCurrentConfigVariables();

        parent::saveConfVars();

        $newValues = $this->getCurrentConfigVariables();
        foreach (array_diff($newValues, $oldValues) as $settingKey => $settingValue) {
            $this->dispatchSettingChangedEvent($settingKey);
            $this->afterSettingChange($settingKey);
        }
    }

    private function getCurrentConfigVariables()
    {
        $moduleConfiguration = $this->getContainer()->get(ModuleConfigurationDaoBridgeInterface::class)->get('oemondu');
        $moduleSettings = [];

        foreach ($moduleConfiguration->getModuleSettings() as $setting) {
            $moduleSettings[$setting->getName()] = $setting->getValue();
        }

        return $moduleSettings;
    }

    private function dispatchSettingChangedEvent($settingKey)
    {
        $this->dispatchEvent(
            new SettingChangedEvent(
                $settingKey,
                Registry::getConfig()->getShopId(),
                'oemondu'
            )
        );
    }

    private function afterSettingChange($settingKey): void
    {
        if (
            $settingKey === 'oemonduWebhookSecret' ||
            $settingKey === 'oemonduIsMerchantIdentified' ||
            $settingKey === 'oemonduErrorLogging' ||
            $settingKey === 'oemonduSandboxMode'
        ) {
            return;
        }

        $this->_client = oxNew(MonduClient::class);
        if ($settingKey === 'oemonduApiKey') {
            if (!$this->_client->getWebhooksSecret()) {
                $this->setIsMerchantIdentified(false);
                MonduHelper::showErrorMessage('INVALID_API_KEY');
                return;
            }

            $this->setIsMerchantIdentified(true);
            $this->registerWebhooks();
        }
    }

    protected function setIsMerchantIdentified(bool $isMerchantIdentified): void
    {
        oxNew(Config::class)->setIsMerchantIdentified($isMerchantIdentified);
    }

    protected function registerWebhooks(): void
    {
        foreach (self::REQUIRED_WEBHOOK_TOPICS as $webhookTopic) {
            $webhookParams = oxNew(Webhook::class, $webhookTopic)->getData();
            $response = $this->_client->registerWebhook($webhookParams);

            if ($response['status'] === 409) {
                return;
            }

            if (!$response['webhook'] && $response['status'] === 403) {
                MonduHelper::showErrorMessage('INVALID_API_KEY');
                return;
            }
        }
    }
}
