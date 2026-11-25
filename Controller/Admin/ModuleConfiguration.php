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

    private ?string $_monduWebhookNotice = null;

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

    public function registerWebhooks(): void
    {
        $existingTopics = $this->getRegisteredTopics();
        $registered = 0;

        foreach (self::REQUIRED_WEBHOOK_TOPICS as $webhookTopic) {
            if (in_array($webhookTopic, $existingTopics, true)) {
                continue;
            }

            $webhookParams = oxNew(Webhook::class, $webhookTopic)->getData();
            $response = $this->_client->registerWebhook($webhookParams);

            if (!empty($response['webhook'])) {
                $registered++;
                continue;
            }

            if (($response['status'] ?? null) === 403) {
                MonduHelper::showErrorMessage('INVALID_API_KEY');
                return;
            }

            // The API error details are already shown by the HTTP client, add a message only when there are none.
            if (empty($response['body']['errors'])) {
                MonduHelper::showErrorMessage('MONDU_REGISTER_WEBHOOK_ERROR');
            }

            return;
        }

        $this->_monduWebhookNotice = $registered > 0
            ? 'MONDU_WEBHOOKS_REGISTERED'
            : 'MONDU_WEBHOOKS_ALREADY_REGISTERED';
    }

    /**
     * Topics already registered for this shop's webhook address.
     */
    private function getRegisteredTopics(): array
    {
        $webhooks = $this->_client->getWebhooks();
        if ($webhooks === null) {
            return [];
        }

        $address = oxNew(Webhook::class, '')->getAddress();
        $topics = [];

        foreach ($webhooks as $webhook) {
            if (($webhook['address'] ?? null) === $address && isset($webhook['topic'])) {
                $topics[] = $webhook['topic'];
            }
        }

        return $topics;
    }

    public function getMonduWebhookNotice(): ?string
    {
        return $this->_monduWebhookNotice;
    }
}
