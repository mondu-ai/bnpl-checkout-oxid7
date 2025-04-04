<?php

namespace OxidEsales\MonduPayment\Controller\Admin;

use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Core\OrderShippingProcessor;
use OxidEsales\MonduPayment\Core\OrderAdjustmentProcessor;

class OrderMain extends OrderMain_parent
{
    protected ?Order $_oOrder;
    protected OrderShippingProcessor $_monduShippingProcessor;
    protected OrderAdjustmentProcessor $_monduAdjustmentProcessor;

    public function __construct()
    {
        parent::__construct();

        $this->_oOrder = $this->getOrder();
        $this->_monduShippingProcessor = oxNew(OrderShippingProcessor::class, $this->_oOrder);
        $this->_monduAdjustmentProcessor = oxNew(OrderAdjustmentProcessor::class);
    }

    public function sendOrder()
    {
        $oOrder = $this->getOrder();
        
        if (stripos($oOrder->oxorder__oxtransstatus->value, 'pending') !== false) {
            return MonduHelper::showErrorMessage('MONDU_SENDING_PENDING_ORDER_ERROR');
        }

        if ($this->isMonduPayment() && !$this->_monduShippingProcessor->shipMonduOrder()) {
            return MonduHelper::showErrorMessage('MONDU_CREATE_INVOICE_ERROR');
        }

        parent::sendOrder();
    }

    public function resetOrder()
    {
        if ($this->isMonduPayment() && !$this->_monduShippingProcessor->cancelMonduOrderShipping()) {
            return MonduHelper::showErrorMessage('MONDU_CANCEL_INVOICE_ERROR');
        }

        return parent::resetOrder();
    }

    public function save()
    {
        if ($this->isMonduPayment()) {
            $oldOrder = $this->_oOrder;

            parent::save();

            $newOrder = $this->getOrder();
            $newOrder->oxorder__oxpaymenttype->setValue($oldOrder->getFieldData('oxpaymenttype'));
            $newOrder->save();

            if (MonduHelper::isOrderAdjusted($oldOrder, $newOrder)) {
                $this->_monduAdjustmentProcessor->setOrder($newOrder);

                if (!$this->_monduAdjustmentProcessor->adjustMonduOrder()) {
                    MonduHelper::showErrorMessage('MONDU_ADJUST_ORDER_ERROR');
                    $oldDelSetId = $oldOrder->getDelSet()->getId();
                    $newOrder->assign($oldOrder);
                    $newOrder->oxorder__oxdiscount->value = $oldOrder->oxorder__oxdiscount->value;
                    $newOrder->reloadDiscount(false);
                    $newOrder->setDelivery($oldDelSetId);
                    $newOrder->recalculateOrder();
                    $newOrder->save();
                }
            }

            return;
        }

        parent::save();
    }

    public function isMonduPayment()
    {
        return $this->_oOrder && $this->_oOrder->isMonduPayment();
    }

    protected function getOrder()
    {
        $oOrder = oxNew(Order::class);
        $soxId = $this->getEditObjectId();

        if (isset($soxId) && $soxId != "-1") {
            $oOrder->load($soxId);
            return $oOrder;
        }

        return null;
    }
}
