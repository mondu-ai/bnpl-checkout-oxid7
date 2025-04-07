<?php

namespace OxidEsales\MonduPayment\Model;

use OxidEsales\Eshop\Core\Model\BaseModel;

class MonduOrder extends BaseModel
{
    const TABLE_NAME = 'oemondu_orders';

    protected $_oOxidOrder = null;
    protected $_aSkipSaveFields = ['created_at', 'updated_at'];

    public function __construct()
    {
        parent::__construct();
        $this->init(self::TABLE_NAME);
    }

    public function createMonduOrderFromResponse($order, $oxidOrder)
    {
        $this->_oOxidOrder = $oxidOrder;
        $this->assign($this->_mapMonduOrderData($order));
        $this->save();
    }

    public function updateOrderState($state)
    {
        if ($this->exists()) {
            $this->oemondu_orders__order_state->rawValue = $state;
            $this->save();
        }
    }

    public function loadByOrderUuid($orderUuid)
    {
        $query = $this->buildSelectString(array('order_uuid' => $orderUuid));
        $this->_isLoaded = $this->assignRecord($query);

        return $this->_isLoaded;
    }

    protected function _mapMonduOrderData($order)
    {
        return array(
            'oemondu_orders__oxid_order_id'  => $this->_oOxidOrder->getId(),
            'oemondu_orders__order_uuid'  => $order['uuid'],
            'oemondu_orders__order_state'  => $order['state'],
            'oemondu_orders__authorized_net_term' => $order['authorized_net_term']
        );
    }
}
