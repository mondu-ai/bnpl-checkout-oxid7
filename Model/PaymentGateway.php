<?php


namespace OxidEsales\MonduPayment\Model;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Core\PaymentHandler\MonduDirectDebitHandler;
use OxidEsales\MonduPayment\Core\PaymentHandler\MonduInstallmentHandler;
use OxidEsales\MonduPayment\Core\PaymentHandler\MonduInvoiceHandler;

class PaymentGateway extends PaymentGateway_parent
{
    protected $paymentHandlerMap = [
        MonduPayment::MONDU_INVOICE      => MonduInvoiceHandler::class,
        MonduPayment::MONDU_DIRECT_DEBIT => MonduDirectDebitHandler::class,
        MonduPayment::MONDU_INSTALLMENT  => MonduInstallmentHandler::class
    ];

    public function executePayment($dAmount, &$oOrder)
    {
        $result = parent::executePayment($dAmount, $oOrder);
        $paymentHandler = $this->createPaymentHandler($oOrder);

        if ($paymentHandler) {
            $result = $paymentHandler->execute($oOrder);
        }

        return $result;
    }

    protected function createPaymentHandler(Order $oOrder)
    {
        $paymentId = $oOrder->getFieldData('OXPAYMENTTYPE');
        $handlerClass = $this->paymentHandlerMap[$paymentId];

        if ($handlerClass) {
            return oxNew($handlerClass);
        }
        return false;
    }
}
