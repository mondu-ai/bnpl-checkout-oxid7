<?php

namespace OxidEsales\MonduPayment\Services;

interface BuyerFeeCentsServiceInterface
{
    public function getBuyerFeeCentsFromBasked($basket): int;

    public function getBuyerFeeCentsFromOrder($order): int;
}
