<?php

namespace OxidEsales\MonduPayment\Core;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use \OxidEsales\MonduPayment\Core\Config;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;

class ViewConfig extends ViewConfig_parent
{
	protected $_config = null;

	public function __construct()
	{
		parent::__construct();

		$this->_config = oxNew(Config::class);
	}

	public function getWidgetUrl()
	{
		return $this->_config->getWidgetUrl();
	}

	public function getMonduLogo()
	{
		return $this->_config->getMonduLogo();
	}

	public function isMonduPayment($paymentId)
	{
		return MonduHelper::isMonduPayment($paymentId);
	}
}
