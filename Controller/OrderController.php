<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Mappers\MonduAdjustmentMapper;
use OxidEsales\MonduPayment\Core\Mappers\MonduOrderMapper;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

class OrderController extends OrderController_parent
{
    private MonduClient $_client;
    private User|null|false $_oUser;
    private LoggerInterface $_logger;

    public function __construct()
    {
        parent::__construct();

        $this->_client = oxNew(MonduClient::class);
        $this->_oUser = $this->getUser();
        $this->_logger = Registry::getLogger();
    }

    public function isMonduPayment()
    {
        $session = Registry::getSession();
        $paymentId = $session->getVariable('paymentid');

        return MonduHelper::isMonduPayment($paymentId);
    }

    public function getPaymentPageUrl()
    {
        $shopUrl = Registry::getConfig()->getShopSecureHomeURL();
        return $shopUrl . '&cl=payment&payerror=2';
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if($this->isMonduPayment()) {
            $orderUuid = Registry::getRequest()->getRequestEscapedParameter('order_uuid');

            if (!$orderUuid) {
                throw new \Exception('Mondu: Not found');
            }

            $oBasket = $this->getBasket();
            $oBasket->calculateBasket(true);

            if ($this->hasMonduAmountChanged($oBasket, $orderUuid)) {
                return $this->rejectChangedBasket($oBasket, $orderUuid);
            }

            try {
                $iSuccess = $this->monduExecute($oBasket, $orderUuid);

                return $this->getNextStep($iSuccess);
            } catch (Exception $e) {
                throw new \Exception('Mondu: Error during the order process');
            }
        }

        // if user is not logged in set the user
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }

        return parent::execute();
    }

    /**
     * Save order to database, delete order_id from session and redirect to thank you page
     *
     * @param Basket $oBasket
     * @param        $orderUuid
     * @return string
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function monduExecute(Basket $oBasket, $orderUuid)
    {
        if (!Registry::getSession()->getVariable('sess_challenge')) {
            Registry::getSession()->setVariable('sess_challenge', Registry::getUtilsObject()->generateUID());
        }

        $iSuccess = 0;
        $oOrder = oxNew(Order::class);

        try {
            $iSuccess = $oOrder->finalizeOrder($oBasket, $oBasket->getUser());
        } catch (\Exception $e) {
            Registry::get(UtilsView::class)->addErrorToDisplay($e);
        }

        if ($iSuccess === 1) {
            // performing special actions after user finishes order (assignment to special user groups)
            $this->_oUser->onOrderExecute($oBasket, $iSuccess);
        }

        if ($oBasket->getOrderId()) {
            Registry::getSession()->deleteVariable(MonduCheckoutController::SESSION_ORDER_AMOUNT);
            // the order is stored (also when only the order mail failed)
            $this->syncMonduOrderAmount($oBasket->getOrderId(), $orderUuid);
        } elseif ($iSuccess !== Order::ORDER_STATE_ORDEREXISTS) {
            $this->cancelUnplacedMonduOrder($orderUuid, $iSuccess);
        }

        $this->confirmOrder($oBasket, $orderUuid);

        return $iSuccess;
    }

    /**
     * OXID recalculates the basket when the buyer comes back from the hosted checkout. Shipping, discounts or
     * costs can come out different from the order page, where the Mondu order was created (for example when two
     * shipping rules have the same priority). Like OXID's own basket check, the order is then not placed with an
     * amount the buyer did not see.
     */
    protected function hasMonduAmountChanged(Basket $oBasket, $orderUuid): bool
    {
        $created = Registry::getSession()->getVariable(MonduCheckoutController::SESSION_ORDER_AMOUNT);
        if (!is_array($created) || ($created['uuid'] ?? null) !== $orderUuid) {
            // no amount stored for this Mondu order, e.g. the order was placed already
            return false;
        }

        $mapper = oxNew(MonduOrderMapper::class);
        $mapper->setBasket($oBasket);
        $amount = $mapper->getBasketGrossAmountCents();

        if ($amount === (int) $created['gross_amount_cents']) {
            return false;
        }

        $this->_logger->warning(
            "MonduOrderController [hasMonduAmountChanged]: Mondu order {$orderUuid} was created with "
            . "{$created['gross_amount_cents']} cents, the basket has {$amount} cents now"
        );

        return true;
    }

    /**
     * The authorized Mondu order does not match the basket any more: it is canceled and the buyer goes back to the
     * order page, sees the new total and confirms again, which creates a new Mondu order.
     */
    protected function rejectChangedBasket(Basket $oBasket, $orderUuid): string
    {
        Registry::getSession()->deleteVariable(MonduCheckoutController::SESSION_ORDER_AMOUNT);
        $this->cancelUnplacedMonduOrder($orderUuid, 'basket changed');

        $next = $oBasket->getProductsCount() ? 'order' : 'basket';
        Registry::getUtilsView()->addErrorToDisplay('MONDU_BASKET_CHANGED', false, true, '', $next);

        return $next;
    }

    /**
     * OXID did not place the order after the buyer authorized the payment on the hosted checkout (for example the
     * payment method or the shipping is not valid any more). The authorized Mondu order would stay open without an
     * OXID order, so it is canceled. The buyer gets the OXID error and can order again.
     */
    protected function cancelUnplacedMonduOrder($orderUuid, $orderState): void
    {
        // an OXID order for this Mondu order exists already, e.g. the buyer came back twice
        $stored = DatabaseProvider::getDb()->getOne(
            'SELECT 1 FROM oemondu_orders WHERE order_uuid = ?',
            [$orderUuid]
        );
        if ($stored) {
            return;
        }

        $this->_client->cancelOrder($orderUuid);
        $this->_logger->warning(
            "MonduOrderController [cancelUnplacedMonduOrder]: OXID did not place the order (state {$orderState}), Mondu order {$orderUuid} canceled"
        );
    }

    /**
     * Fallback after the order is placed: if the OXID order still differs from the Mondu order (the basket check
     * above could not run, or the stored order differs from the basket), the Mondu order is adjusted to it. If Mondu does not accept the adjustment,
     * the order is moved to the problems folder so that the merchant sees it before invoicing.
     */
    protected function syncMonduOrderAmount($orderId, $orderUuid): void
    {
        if (!$orderId || !$orderUuid) {
            return;
        }

        $order = oxNew(Order::class);
        if (!$order->load($orderId)) {
            return;
        }

        $monduOrder = $this->_client->getMonduOrder($orderUuid);
        if (!$monduOrder || !isset($monduOrder['real_price_cents'])) {
            return;
        }

        $adjustmentMapper = oxNew(MonduAdjustmentMapper::class);
        $oxidAmount = $adjustmentMapper->getOrderAmounts($order)['gross_amount_cents'];
        $monduAmount = (int) $monduOrder['real_price_cents'];

        if ($oxidAmount === $monduAmount) {
            return;
        }

        $this->_client->adjustOrder($orderUuid, $adjustmentMapper->getMappedOrderData($order));

        // the adjust response does not carry the recalculated amount, read the order again
        $adjusted = $this->_client->getMonduOrder($orderUuid);
        if ($adjusted && (int) ($adjusted['real_price_cents'] ?? -1) === $oxidAmount) {
            $this->_logger->info(
                "MonduOrderController [syncMonduOrderAmount]: order {$orderUuid} adjusted from {$monduAmount} to {$oxidAmount} cents"
            );
            return;
        }

        $this->_logger->error(
            "MonduOrderController [syncMonduOrderAmount]: Mondu order {$orderUuid} has {$monduAmount} cents, "
            . "OXID order {$order->getFieldData('oxorder__oxordernr')} has {$oxidAmount} cents, the adjustment was not accepted"
        );
        DatabaseProvider::getDb()->execute(
            "UPDATE oxorder SET oxfolder = 'ORDERFOLDER_PROBLEMS' WHERE oxid = ?",
            [$orderId]
        );
    }

    /**
     * @param $oBasket
     * @param $orderUuid
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function confirmOrder($oBasket, $orderUuid)
    {
        if (!$oBasket->getOrderId()) {
            return;
        }

        $data = [];
        $order = oxNew(Order::class);
        $order->load($oBasket->getOrderId());
        $data['external_reference_id'] = (string) $order->getFieldData('oxorder__oxordernr');

        $this->_logger->debug('MonduOrderController [execute $data]: ' . print_r($data, true));
        $this->_logger->debug('MonduOrderController [execute $orderUuid]: ' . print_r($orderUuid, true));
        $response = $this->_client->confirmOrder($orderUuid, $data);
        $this->_logger->debug('MonduOrderController [execute $response]: ' . print_r($response, true));

        if (isset($response['state']) && ($response['state'] == 'confirmed' || $response['state'] == 'pending')) {
            $isPending = $response['state'] == 'pending';

            if ($isPending) {
                $sQuery = "
                    UPDATE 
                        oxorder
                    SET 
                        oxfolder = 'ORDERFOLDER_PROBLEMS', 
                        oxtransstatus = 'PENDING'
                    WHERE 
                        OXORDERNR = '{$order->getFieldData('oxorder__oxordernr')}'
                ";
                DatabaseProvider::getDb()->execute($sQuery);
            }

            $this->_logger->debug(
                'MonduOrderController [execute $response]: ' . print_r($response, true)
            );
        }
    }
}