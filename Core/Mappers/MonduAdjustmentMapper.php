<?php

namespace OxidEsales\MonduPayment\Core\Mappers;

use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\MonduPayment\Services\BuyerFeeCentsServiceInterface;

class MonduAdjustmentMapper
{
    /**
     * @var BuyerFeeCentsServiceInterface
     */
    private $buyerFeeCentsService = null;

    public function __construct() {
        $container = ContainerFactory::getInstance()->getContainer();

        if ($container->has(BuyerFeeCentsServiceInterface::class)) {
            $this->buyerFeeCentsService = $container->get(BuyerFeeCentsServiceInterface::class);
        }
    }

    public function getMappedOrderData($order)
    {
        $lineItems = $this->getOrderLineItems($order);
        $amounts = $this->getOrderAmounts($order, $lineItems);

        $data = [
            "currency" => $order->getOrderCurrency()->name,
            "external_reference_id" => $order->getFieldData('oxorder__oxordernr') ? (string) $order->getFieldData('oxorder__oxordernr') : $order->getId(),
            "amount" => [
                "net_price_cents" => $amounts['net_price_cents'],
                "tax_cents" => $amounts['tax_cents'],
                "gross_amount_cents" => $amounts['gross_amount_cents']
            ],
            "lines" => [[
                "tax_cents" => $amounts['tax_cents'],
                "buyer_fee_cents" => $amounts['buyer_fee_cents'],
                "shipping_price_cents" => $amounts['shipping_price_cents'],
                "discount_cents" => $amounts['discount_cents'],
                "line_items" => $lineItems
            ]]
        ];

        return MonduHelper::removeEmptyElementsFromArray($data);
    }

    /**
     * Amount fields of the Mondu order, built from the OXID order total so that they add up to it.
     */
    public function getOrderAmounts($order, ?array $lineItems = null): array
    {
        $lineItems = $lineItems ?? $this->getOrderLineItems($order);

        $itemsNetCents = 0;
        foreach ($lineItems as $lineItem) {
            $itemsNetCents += (int) $lineItem['net_price_per_item_cents'] * (int) $lineItem['quantity'];
        }

        $fees = (float) $order->getFieldData('oxpaycost')
            + (float) $order->getFieldData('oxwrapcost')
            + (float) $order->getFieldData('oxgiftcardcost')
            + ((int) $this->buyerFeeCentsService?->getBuyerFeeCentsFromOrder($order)) / 100;

        $hasDiscount = (float) $order->getFieldData('oxdiscount') > 0 || (float) $order->getFieldData('oxvoucherdiscount') > 0;

        return MonduAmountCalculator::calculate(
            $itemsNetCents,
            (float) array_sum($order->getProductVats(false)),
            (float) $order->getFieldData('oxdelcost'),
            $fees,
            (float) $order->getFieldData('oxtotalordersum'),
            $hasDiscount
        );
    }

    protected function getOrderLineItems($order)
    {
        $orderArticles = array_values($order->getOrderArticles()->getArray());
        return MonduHelper::getMappedOrderArticles($orderArticles);
    }
}
