<?php

namespace OxidEsales\MonduPayment\Model;

use OxidEsales\Eshop\Core\Model\ListModel;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use Psr\Log\LoggerInterface;

class Order extends Order_parent
{
    /**
     * @var mixed|\oxdeliveryset|\oxField|\OxidEsales\Eshop\Core\Field|\OxidEsales\EshopCommunity\Application\Model\oxDeliverySet|MonduClient
     */
    private mixed $client;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $_logger;

    public function __construct()
    {
        parent::__construct();
        $this->client = oxNew(MonduClient::class);
        $this->_logger = Registry::getLogger();
    }

    public function getMonduOrders()
    {
        if ($this->isMonduPayment()) {
            $sQuery = 'SELECT * FROM `oemondu_orders` WHERE `oxid_order_id`=:oxorderid ORDER BY created_at DESC';

            $oMonduOrders = oxNew(ListModel::class);
            $oMonduOrders->init(MonduOrder::class);
            $oMonduOrders->selectString($sQuery, [':oxorderid' => $this->getId()]);

            return $oMonduOrders;
        }

        return [];
    }

    public function getMonduInvoices()
    {
        if ($this->isMonduPayment()) {
            $sQuery = 'SELECT * FROM `oemondu_invoices` WHERE `invoice_id` IN (:oxorderid, :oxordernr) ORDER BY created_at DESC';

            $oMonduInvoices = oxNew(ListModel::class);
            $oMonduInvoices->init(MonduInvoice::class);
            $oMonduInvoices->selectString(
                $sQuery, [
                    ':oxorderid' => $this->getId(),
                    ':oxordernr' => $this->getFieldData('oxorder__oxordernr')
                ]
            );

            return $oMonduInvoices;
        }

        return [];
    }

    public function isMonduPayment()
    {
        return MonduHelper::isMonduPayment($this->getFieldData('oxpaymenttype'));
    }

    public function deleteAllArticles()
    {
        $orderArticles = $this->getOrderArticles();

        foreach ($orderArticles as $orderArticle) {
            $orderArticle->delete();
        }

        $this->recalculateOrder();
    }

    /**
     * Order checking, processing and saving method.
     * Before saving performed checking if order is still not executed (checks in
     * database oxorder table for order with know ID), if yes - returns error code 3,
     * if not - loads payment data, assigns all info from basket to new Order object
     * and saves full order with error status. Then executes payment. On failure -
     * deletes order and returns error code 2. On success - saves order (\OxidEsales\Eshop\Application\Model\Order::save()),
     * removes article from wishlist (\OxidEsales\Eshop\Application\Model\Order::_updateWishlist()), updates voucher data
     * (\OxidEsales\Eshop\Application\Model\Order::_markVouchers()). Finally sends order confirmation email to customer
     * (\OxidEsales\Eshop\Core\Email::SendOrderEMailToUser()) and shop owner (\OxidEsales\Eshop\Core\Email::SendOrderEMailToOwner()).
     * If this is order recalculation, skipping payment execution, marking vouchers as used
     * and sending order by email to shop owner and user
     * Mailing status (1 if OK, 0 on error) is returned.
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket              Basket object
     * @param object                                     $oUser                Current User object
     * @param bool                                       $blRecalculatingOrder Order recalculation
     *
     * @return integer
     */
    public function finalizeOrder(
        \OxidEsales\Eshop\Application\Model\Basket $oBasket,
        $oUser,
        $blRecalculatingOrder = false,
        $isPending = false
    ) {
        $result = parent::finalizeOrder($oBasket, $oUser, $blRecalculatingOrder);

        $this->_logger->debug('MonduOrder [finalizeOrder $result]: ' . print_r($result, true));
        if (
            $this->isMonduPayment() &&
            $this->getMonduOrders()
        ) {
            $monduOrderUuid = array_values($this->getMonduOrders()->getArray())[0]->getFieldData( 'order_uuid' );

            $this->_logger->debug('MonduOrder [finalizeOrder $monduOrderUuid]: ' . print_r($monduOrderUuid, true));
            if (!$monduOrderUuid || !$this->getFieldData( 'oxorder__oxordernr')) {
                return $result;
            }

            $this->client->updateOrderExternalInfo(
                $monduOrderUuid,
                ['external_reference_id' => (string) $this->getFieldData('oxorder__oxordernr')]
            );

            if ($isPending) {

                $this->oxorder__oxfolder = new \OxidEsales\Eshop\Core\Field('ORDERFOLDER_PROBLEMS');
                $this->oxorder__oxtransstatus = new \OxidEsales\Eshop\Core\Field('PENDING');

                $monduOrder = $this->getOrder($monduOrderUuid);

                if ($monduOrder) {
                    $monduOrder->updateOrderState($params['order_state']);
                }
                $this->save();
            }
        }

        if ($this->isMonduPayment() && !$this->getMonduOrders()) {
            $this->_logger->debug('MonduOrder [empty Mondu orders]: ' . print_r($result, true));
        }

        return $result;
    }

    private function getOrder($orderUuid)
    {
        $monduOrder = oxNew(MonduOrder::class);
        $monduOrder->loadByOrderUuid($orderUuid);
        return $monduOrder;
    }
}
