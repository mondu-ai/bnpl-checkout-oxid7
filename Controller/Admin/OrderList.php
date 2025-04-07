<?php

namespace OxidEsales\MonduPayment\Controller\Admin;

use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Model\MonduOrder;

class OrderList extends OrderList_parent
{
    protected MonduClient $_client;
    protected ?Order $_oOrder;
    protected ?MonduOrder $_oMonduOrder;

    public function __construct()
    {
        parent::__construct();

        $this->_client = oxNew(MonduClient::class);
        $this->_oOrder = $this->getOrder();
        $this->_oMonduOrder = $this->getMonduOrder();
    }

    public function cancelOrder()
    {
        if ($this->isMonduPayment() && !$this->cancelMonduOrder()) {
            return MonduHelper::showErrorMessage('MONDU_CANCEL_ORDER_ERROR');
        }

        $this->_oOrder->cancelOrder();
        $this->resetContentCache();
        $this->init();
    }

    public function deleteEntry()
    {
        if ($this->isMonduPayment() && !$this->cancelMonduOrder()) {
            return MonduHelper::showErrorMessage('MONDU_CANCEL_ORDER_ERROR');
        }

        parent::deleteEntry();
    }

    protected function cancelMonduOrder()
    {
        if ($this->_oMonduOrder) {
            return $this->_client->cancelOrder($this->_oMonduOrder->getFieldData('order_uuid'));
        }

        return false;
    }

    protected function getOrder()
    {
        $oOrder = oxNew(Order::class);
        $oxId = $this->getEditObjectId();

        if (isset($oxId) && $oxId != "-1") {
            $oOrder->load($oxId);
            return $oOrder;
        }

        return null;
    }

    protected function getMonduOrder()
    {
        if ($this->isMonduPayment()) {
            return array_values($this->_oOrder->getMonduOrders()->getArray())[0];
        }

        return null;
    }

    protected function isMonduPayment()
    {
        return $this->_oOrder && $this->_oOrder->isMonduPayment();
    }
}
