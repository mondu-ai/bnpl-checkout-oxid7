<?php

namespace OxidEsales\MonduPayment\Core;

use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Mappers\MonduInvoiceMapper;
use OxidEsales\MonduPayment\Model\MonduInvoice;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Model\MonduOrder;

class OrderShippingProcessor
{
    protected MonduClient $client;
    protected ?Order $_oOrder;
    protected ?MonduOrder $_oMonduOrder;

    public function __construct(Order $order = null)
    {
        $this->client = oxNew(MonduClient::class);
        $this->_oOrder = $order;
        $this->_oMonduOrder = null;
        $this->getMonduOrder();
    }

    public function shipMonduOrder()
    {
        if ($this->_oOrder && $this->_oMonduOrder) {
            $monduInvoice = $this->createMonduInvoice();

            if ($monduInvoice) {
                $this->storeMonduInvoice($monduInvoice);
                return true;
            }
        }

        return false;
    }

    public function cancelMonduOrderShipping()
    {
        if ($this->_oOrder && $this->_oMonduOrder) {
            if ($this->cancelMonduInvoice()) {
                return true;
            }
        }

        return false;
    }

    protected function createMonduInvoice()
    {
        $invoiceDataMapper = oxNew(MonduInvoiceMapper::class);
        $invoiceData = $invoiceDataMapper->getMappedInvoiceData($this->_oOrder);

        return $this->client->createInvoice($this->_oMonduOrder->getFieldData('order_uuid'), $invoiceData);
    }

    protected function cancelMonduInvoice()
    {
        if ($this->_oOrder->getMonduInvoices()) {
            $monduInvoice = array_values($this->_oOrder->getMonduInvoices()->getArray())[0];

            if ($monduInvoice) {
                return $this->client->cancelInvoice(
                    $this->_oMonduOrder->getFieldData('order_uuid'),
                    $monduInvoice->getFieldData('invoice_uuid')
                );
            }
        }
    }

    protected function storeMonduInvoice($invoice)
    {
        $monduInvoice = oxNew(MonduInvoice::class);
        $monduInvoice->createMonduInvoiceFromResponse($invoice, $this->_oMonduOrder);
    }

    protected function getMonduOrder()
    {
        if ($this->_oOrder && $this->_oOrder->isMonduPayment() && !$this->_oMonduOrder) {
            $this->_oMonduOrder = array_values($this->_oOrder->getMonduOrders()->getArray())[0];
        }
    }
}
