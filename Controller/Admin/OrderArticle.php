<?php

namespace OxidEsales\MonduPayment\Controller\Admin;

use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\MonduPayment\Core\OrderAdjustmentProcessor;

class OrderArticle extends OrderArticle_parent
{
    protected ?Order $_oOrder;
    protected OrderAdjustmentProcessor $_monduAdjustmentProcessor;

    public function __construct()
    {
        parent::__construct();

        $this->_oOrder = $this->getOrder();
        $this->_monduAdjustmentProcessor = oxNew(OrderAdjustmentProcessor::class);
    }

    public function updateOrder()
    {
        if ($this->isMonduPayment()) {
            $oldOrder = $this->_oOrder;
            $oldDelSetId = $oldOrder->getDelSet()->getId();
            $oldOrderArticles = $oldOrder->getOrderArticles();

            parent::updateOrder();

            $newOrder = $this->getOrder();
            $newOrder->oxorder__oxpaymenttype->setValue($oldOrder->getFieldData('oxpaymenttype'));
            $newOrder->save();

            return $this->handleMonduOrderAdjustment($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles);
        }

        parent::updateOrder();
    }

    public function addThisArticle()
    {
        if ($this->isMonduPayment()) {
            $oldOrder = $this->_oOrder;
            $oldDelSetId = $oldOrder->getDelSet()->getId();
            $oldOrderArticles = $oldOrder->getOrderArticles();

            parent::addThisArticle();

            $newOrder = $this->getOrder();
            $newOrder->oxorder__oxpaymenttype->setValue($oldOrder->getFieldData('oxpaymenttype'));
            $newOrder->save();

            return $this->handleMonduOrderAdjustment($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles);
        }

        parent::addThisArticle();
    }

    public function deleteThisArticle()
    {
        if ($this->isMonduPayment()) {
            $oldOrder = $this->_oOrder;
            $oldDelSetId = $oldOrder->getDelSet()->getId();
            $oldOrderArticles = $oldOrder->getOrderArticles();

            parent::deleteThisArticle();

            $newOrder = $this->getOrder();
            $newOrder->oxorder__oxpaymenttype->setValue($oldOrder->getFieldData('oxpaymenttype'));
            $newOrder->save();

            return $this->handleMonduOrderAdjustment($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles);
        }

        parent::deleteThisArticle();
    }

    public function storno()
    {
        if ($this->isMonduPayment()) {
            $oldOrder = $this->_oOrder;
            $oldDelSetId = $oldOrder->getDelSet()->getId();
            $oldOrderArticles = $oldOrder->getOrderArticles();

            parent::storno();

            $newOrder = $this->getOrder();
            $newOrder->oxorder__oxpaymenttype->setValue($oldOrder->getFieldData('oxpaymenttype'));
            $newOrder->save();

            return $this->handleMonduOrderAdjustment($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles);
        }

        parent::storno();
    }

    public function isMonduPayment()
    {
        return $this->_oOrder && $this->_oOrder->isMonduPayment();
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

    protected function handleMonduOrderAdjustment($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles)
    {
        if (MonduHelper::isOrderAdjusted($oldOrder, $newOrder)) {
            $this->_monduAdjustmentProcessor->setOrder($newOrder);

            if (!$this->_monduAdjustmentProcessor->adjustMonduOrder()) {
                MonduHelper::showErrorMessage('MONDU_ADJUST_ORDER_ERROR');
                $this->resetOrderData($newOrder, $oldOrder, $oldDelSetId, $oldOrderArticles);
            }
        }
    }

    protected function resetOrderData($order, $orderData, $deliverySetId, $articles)
    {
        $order->assign($orderData);
        $order->setDelivery($deliverySetId);
        $order->deleteAllArticles();
        $order->setOrderArticleList($articles);
        $order->recalculateOrder($articles);
        $order->save();
    }
}
