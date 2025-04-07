<?php

declare(strict_types=1);

namespace OxidEsales\MonduPayment\Settings\Service;

interface ModuleSettingsServiceInterface
{
    public function isSandbox(): bool;

    public function isLoggingEnabled(): bool;

    public function getApiToken(): string;
}
