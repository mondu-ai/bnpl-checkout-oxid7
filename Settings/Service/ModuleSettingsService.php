<?php

declare(strict_types=1);

namespace OxidEsales\MonduPayment\Settings\Service;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class ModuleSettingsService implements ModuleSettingsServiceInterface
{
    public const MODULE_ID = 'oemondu';

    public function __construct(
        private ModuleSettingServiceInterface $moduleSettingService,
    ) {
    }

    public function isSandbox(): bool
    {
        return $this->moduleSettingService->getBoolean('oemonduSandboxMode', self::MODULE_ID);
    }

    public function isLoggingEnabled(): bool
    {
        return $this->moduleSettingService->getBoolean('oemonduErrorLogging', self::MODULE_ID);
    }

    public function getApiToken(): string
    {
        return (string)$this->moduleSettingService->getString('oemonduApiKey', self::MODULE_ID);
    }
}
