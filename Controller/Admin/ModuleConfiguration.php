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
        if ($existingTopics === null) {
            // without the list, creating webhooks blind could only produce duplicates or errors
            MonduHelper::showErrorMessage('MONDU_REGISTER_WEBHOOK_ERROR');
            return;
        }

        $registered = 0;
        $failed = 0;

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

            if ($this->isAlreadySubscribed($response)) {
                continue;
            }

            $failed++;
        }

        if ($failed > 0) {
            MonduHelper::showErrorMessage('MONDU_REGISTER_WEBHOOK_ERROR');
            return;
        }

        $this->_monduWebhookNotice = $registered > 0
            ? 'MONDU_WEBHOOKS_REGISTERED'
            : 'MONDU_WEBHOOKS_ALREADY_REGISTERED';
    }

    /**
     * Topics already registered for this shop's webhook address, or null when the list can not be loaded.
     */
    private function getRegisteredTopics(): ?array
    {
        $webhooks = $this->_client->getWebhooks();
        if ($webhooks === null) {
            return null;
        }

        $address = Webhook::normalizeAddress(oxNew(Webhook::class, '')->getAddress());
        $topics = [];

        foreach ($webhooks as $webhook) {
            if (
                isset($webhook['topic'], $webhook['address'])
                && Webhook::normalizeAddress((string) $webhook['address']) === $address
            ) {
                $topics[] = $webhook['topic'];
            }
        }

        return $topics;
    }

    /**
     * The API answers a duplicate subscription with 422 and an error named "webhook".
     */
    private function isAlreadySubscribed($response): bool
    {
        if (($response['status'] ?? null) !== 422) {
            return false;
        }

        foreach ($response['body']['errors'] ?? [] as $error) {
            if (($error['name'] ?? null) === 'webhook') {
                return true;
            }
        }

        return false;
    }

    public function getMonduWebhookNotice(): ?string
    {
        return $this->_monduWebhookNotice;
    }
}
