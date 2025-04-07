<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Mappers\MonduOrderMapper;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\MonduPayment\Model\MonduPayment;
use OxidEsales\Eshop\Application\Model\Order;
use Psr\Log\LoggerInterface;

class MonduCheckoutController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    protected MonduClient $_client;
    protected MonduOrderMapper $_orderMapper;
    private LoggerInterface $_logger;

    public function __construct()
    {
        parent::__construct();

        $this->_client = oxNew(MonduClient::class);
        $this->_orderMapper = oxNew(MonduOrderMapper::class);
        $this->_logger = Registry::getLogger();
    }

    public function createOrder()
    {
        $this->_orderMapper->setBasket($this->getBasket());
        $this->_orderMapper->setDeliveryAddress($this->getDelAddress());

        $paymentMethod = $this->getPaymentMethod();

        $orderData = $this->_orderMapper->getMappedOrderData($paymentMethod);
        $response = $this->_client->createOrder($orderData);
        $token = $response['uuid'] ?? 'error';

        $this->_logger->debug('MonduCheckoutController [createOrder $orderData]: ' . print_r($orderData, true));
        $this->_logger->debug('MonduCheckoutController [createOrder $response]: ' . print_r($response, true));

        if ($token !== 'error') {
            $session = Registry::getSession();
            $session->setVariable('mondu_order_uuid', $token);
        }

        echo json_encode([
            'token' => $token,
            'hostedCheckoutUrl' => $response['hosted_checkout_url'] ?? false
        ]);

        exit();
    }

    protected function getBasket()
    {
        return Registry::getSession()->getBasket();
    }

    protected function getDelAddress()
    {
        $order = oxNew(Order::class);
        return $order->getDelAddressInfo();
    }

    protected function getPaymentMethod()
    {
        $session = Registry::getSession();
        $paymentId = $session->getVariable("paymentid");
        $payment = MonduPayment::getMonduPaymentMethodFromPaymentId($paymentId);

        return $payment ? $payment['mondu_payment_method'] : 'invoice';
    }
}
