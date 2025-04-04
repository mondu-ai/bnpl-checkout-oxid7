<?php

namespace OxidEsales\MonduPayment\Services;

use OxidEsales\Eshop\Application\Model\Order;

class BuyerFeeCentsService implements BuyerFeeCentsServiceInterface
{
    /**
     * @param mixed|null $basket
     * @return int
     */
    public function getBuyerFeeCentsFromBasked($basket): int {
        return 0;
    }

    /**
     * @param Order|null $order
     * @return int
     */
    public function getBuyerFeeCentsFromOrder($order): int {
        return 0;
    }
}
