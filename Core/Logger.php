<?php

declare(strict_types=1);

namespace OxidEsales\MonduPayment\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use Psr\Log\LogLevel;

class Logger
{
    protected $_config;

    public function __construct()
    {
        $this->_config = oxNew(Config::class);
    }

    public function getLogger(): MonologLogger
    {
        $logger = new MonologLogger('oemondulogger');

        if ($this->_config->isLoggingEnabled()) {
            $logger->pushHandler(
                new StreamHandler(Registry::getConfig()->getLogsDir() . 'oemondu.log', LogLevel::INFO)
            );
        }

        return $logger;
    }
}
