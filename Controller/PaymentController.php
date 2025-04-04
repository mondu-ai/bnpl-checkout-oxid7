<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;
use OxidEsales\MonduPayment\Core\Config;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\MonduPayment\Model\MonduPayment;

class PaymentController extends PaymentController_parent
{
    const MONDU_PREFIX = 'mondu_';
    protected MonduClient $_client;
    protected Config $_config;
    protected $_paymentList;
    protected $_monduAllowedPaymentMethods;

    public function __construct()
    {
        parent::__construct();

        $this->_config = oxNew(Config::class);
        $this->_client = oxNew(MonduClient::class);
    }

    public function getPaymentList()
    {
        if (!$this->_paymentList) {
            $this->_paymentList = parent::getPaymentList();
        }

        if (MonduHelper::isMonduModuleActive() && $this->_config->getIsMerchantIdentified()) {
            $this->filterMonduPaymentMethods();
        } else {
            $this->removeMonduPaymentMethods();
        }

        return $this->_paymentList;
    }

    protected function filterMonduPaymentMethods()
    {
        $this->_monduAllowedPaymentMethods = $this->getMonduAllowedPaymentMethods();

        if (!$this->_monduAllowedPaymentMethods) {
            $this->_paymentList = array_filter($this->_paymentList, function ($i) {
                return !MonduHelper::isMonduPayment($i->oxpayments__oxid->value);
            });

            return $this->_paymentList;
        }

        $this->_paymentList = array_filter($this->_paymentList, function ($i) {
            $monduPaymentIdentifier = MonduPayment::getMonduPaymentMethodFromPaymentId($i->oxpayments__oxid->value);

            if ($monduPaymentIdentifier && !in_array($monduPaymentIdentifier['mondu_payment_method'], $this->_monduAllowedPaymentMethods)) {
                return false;
            }

            return true;
        });
    }

    protected function removeMonduPaymentMethods()
    {
        $this->_paymentList = array_filter($this->_paymentList, function ($i) {
            return !(stripos($i->oxpayments__oxid->value, self::MONDU_PREFIX) !== false);
        });
    }

    protected function getMonduAllowedPaymentMethods()
    {
        $methods = $this->_client->getPaymentMethods();

        if ($methods) {
            return array_map(function ($i) {
                return $i['identifier'];
            }, $methods);
        }

        return false;
    }
}
