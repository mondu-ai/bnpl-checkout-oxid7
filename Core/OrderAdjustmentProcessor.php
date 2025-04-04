<?php

namespace OxidEsales\MonduPayment\Core;

use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Core\Mappers\MonduAdjustmentMapper;
use OxidEsales\MonduPayment\Model\MonduOrder;

class OrderAdjustmentProcessor
{
    protected MonduClient $_client;
    protected ?Order $_oOrder;
    protected ?MonduOrder $_oMonduOrder;

    public function __construct(Order $order = null)
    {
        $this->_client = oxNew(MonduClient::class);
        $this->_oOrder = $order;
        $this->_oMonduOrder = null;
        $this->getMonduOrder();
    }

    public function setOrder($order)
    {
        $this->_oOrder = $order;
        $this->getMonduOrder();
    }

    public function adjustMonduOrder()
    {
        $adjustmentMapper = oxNew(MonduAdjustmentMapper::class);
        $adjustmentData = $adjustmentMapper->getMappedOrderData($this->_oOrder);

        if ($this->_oMonduOrder) {
            $response = $this->_client->adjustOrder($this->_oMonduOrder->getFieldData('order_uuid'), $adjustmentData);
            return $response;
        }

        return null;
    }

    protected function getMonduOrder()
    {
        if ($this->_oOrder && $this->_oOrder->isMonduPayment() && !$this->_oMonduOrder) {
            $this->_oMonduOrder = array_values($this->_oOrder->getMonduOrders()->getArray())[0];
        }
    }
}
