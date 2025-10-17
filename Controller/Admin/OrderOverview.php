<?php

namespace OxidEsales\MonduPayment\Controller\Admin;

use OxidEsales\MonduPayment\Core\OrderShippingProcessor;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\Eshop\Application\Model\Order;

class OrderOverview extends OrderOverview_parent
{
    protected ?Order $_oOrder;
    protected OrderShippingProcessor $_monduShippingProcessor;

    public function __construct()
    {
        parent::__construct();

        $this->_oOrder = $this->getOrder();
        $this->_monduShippingProcessor = oxNew(OrderShippingProcessor::class, $this->_oOrder);
    }

    public function render()
    {
        if ($this->isMonduPayment()) {
            $monduOrder = array_values($this->_oOrder->getMonduOrders()->getArray())[0];
            $this->_aViewData["oemonduAuthorizedNetTerm"] = $monduOrder ? $monduOrder->getFieldData('authorized_net_term') : null;
        }

        return parent::render();
    }

    public function sendorder()
    {
        $oOrder = $this->getOrder();
        
        if (stripos($oOrder->oxorder__oxtransstatus->value, 'pending') !== false) {
            return MonduHelper::showErrorMessage('MONDU_SENDING_PENDING_ORDER_ERROR');
        }

        if ($this->isMonduPayment() && !$this->_monduShippingProcessor->shipMonduOrder()) {
            return MonduHelper::showErrorMessage('MONDU_CREATE_INVOICE_ERROR');
        }

        if ($this->isMonduPayment() && !$this->_monduShippingProcessor->shipMonduOrder()) {
            return MonduHelper::showErrorMessage('MONDU_CREATE_INVOICE_ERROR');
        }

        parent::sendorder();
    }

    public function resetorder()
    {
        if ($this->isMonduPayment() && !$this->_monduShippingProcessor->cancelMonduOrderShipping()) {
            return MonduHelper::showErrorMessage('MONDU_CANCEL_INVOICE_ERROR');
        }

        return parent::resetorder();
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
