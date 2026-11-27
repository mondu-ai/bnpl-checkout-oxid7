<?php

namespace OxidEsales\MonduPayment\Core\Mappers;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use OxidEsales\MonduPayment\Services\BuyerFeeCentsServiceInterface;

class MonduOrderMapper
{
    protected $_basket = null;
    protected $_deliveryAddress = null;

    /**
     * @var BuyerFeeCentsServiceInterface
     */
    protected $buyerFeeCentsService = null;

    public function __construct() {
        $container = ContainerFactory::getInstance()->getContainer();

        if ($container->has(BuyerFeeCentsServiceInterface::class)) {
            $this->buyerFeeCentsService = $container->get(BuyerFeeCentsServiceInterface::class);
        }
    }

    public function getBasket()
    {
        return $this->_basket;
    }

    public function setBasket($basket)
    {
        $this->_basket = $basket;
    }

    public function getDeliveryAddress()
    {
        return $this->_deliveryAddress;
    }

    public function setDeliveryAddress($deliveryAddress)
    {
        $this->_deliveryAddress = $deliveryAddress;
    }

    protected function getBasketUser()
    {
        return $this->getBasket()->getBasketUser();
    }

    public function getMappedOrderData($paymentMethod)
    {
        $session = Registry::getSession();
        $basket = $this->getBasket();
        $monduOrderUuid = $session->getVariable('mondu_order_uuid');
        $lineItems = $this->getOrderLineItems();
        $amounts = $this->getBasketAmounts($basket, $lineItems);
        $shopUrl = Registry::getConfig()->getCurrentShopUrl();

        $oDelAdressMD5 = $this->getBasketUser()->getEncodedDeliveryAddress();
        if (Registry::getSession()->getVariable('deladrid')) {
            $oDelAdress = oxNew(\OxidEsales\Eshop\Application\Model\Address::class);
            $oDelAdress->load(Registry::getSession()->getVariable('deladrid'));
            $oDelAdressMD5 .= $oDelAdress->getEncodedDeliveryAddress();
        }

        $externalReferenceId = uniqid('M_OX_');
        $data = [
            "currency" => $basket->getBasketCurrency()->name,
            "payment_method" => $paymentMethod,
            "external_reference_id" => $externalReferenceId,
            "gross_amount_cents" => $amounts['gross_amount_cents'],
            "buyer" => MonduHelper::removeEmptyElementsFromArray($this->getBuyerData()),
            "billing_address" => MonduHelper::removeEmptyElementsFromArray($this->getUserBillingAddress()),
            "shipping_address" => MonduHelper::removeEmptyElementsFromArray($this->getUserDeliveryAddress()),
            "success_url" => $shopUrl . '?cl=order&fnc=execute&order_uuid=' . $monduOrderUuid . '&sDeliveryAddressMD5=' . $oDelAdressMD5,
            "cancel_url" => $shopUrl . '?cl=oemonducancel',
            "declined_url" => $shopUrl . '?cl=oemondudeclined',
            "state_flow" => 'authorization_flow',
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
     * Gross amount the Mondu order gets for the current state of the basket.
     */
    public function getBasketGrossAmountCents(): int
    {
        return $this->getBasketAmounts($this->getBasket(), $this->getOrderLineItems())['gross_amount_cents'];
    }

    protected function getOrderLineItems()
    {
        $basketContents = $this->getBasket()->getContents();
        $items = array_values($basketContents);
        $lineItems = [];

        foreach ($items as $lineItem) {
            $article = $lineItem->getArticle();

            $lineItems[] = [
                'external_reference_id' => $article->oxarticles__oxid->value,
                'title' => $lineItem->getTitle(),
                'net_price_per_item_cents' => (int) round($lineItem->getUnitPrice()->getNettoPrice() * 100),
                'quantity' => (int) $lineItem->getAmount()
            ];
        }

        return $lineItems;
    }

    /**
     * Amount fields of the Mondu order line, built from the basket total so that they add up to it.
     */
    protected function getBasketAmounts($basket, array $lineItems): array
    {
        $itemsNetCents = 0;
        foreach ($lineItems as $lineItem) {
            $itemsNetCents += (int) $lineItem['net_price_per_item_cents'] * (int) $lineItem['quantity'];
        }

        $fees = self::bruttoOf($basket->getPaymentCost())
            + self::bruttoOf($basket->getWrappingCost())
            + self::bruttoOf($basket->getGiftCardCost())
            + ((int) $this->buyerFeeCentsService?->getBuyerFeeCentsFromBasked($basket)) / 100;

        $hasDiscount = self::bruttoOf($basket->getTotalDiscount()) > 0 || self::bruttoOf($basket->getVoucherDiscount()) > 0;

        return MonduAmountCalculator::calculate(
            $itemsNetCents,
            (float) array_sum($basket->getProductVats(false)),
            self::bruttoOf($basket->getDeliveryCost()),
            $fees,
            self::bruttoOf($basket->getPrice()),
            $hasDiscount
        );
    }

    protected static function bruttoOf($price): float
    {
        return $price ? (float) $price->getBruttoPrice() : 0.0;
    }

    protected function getBuyerData()
    {
        $user = $this->getBasketUser();

        return [
            "email" => $user->oxuser__oxusername->rawValue,
            "first_name" => $user->oxuser__oxfname->rawValue,
            "last_name" => $user->oxuser__oxlname->rawValue,
            "company_name" => $user->oxuser__oxcompany->rawValue,
            "phone" => $user->oxuser__oxfon->rawValue,
            "vat_number" => trim((string) $user->oxuser__oxustid->rawValue)
        ];
    }

    protected function getUserBillingAddress()
    {
        $user = $this->getBasketUser();
        $billingCountry = oxNew(\OxidEsales\Eshop\Application\Model\Country::class);
        $billingCountryId = $user->oxuser__oxcountryid->rawValue;
        $billingCountryCode = $billingCountry->getCodeById($billingCountryId);

        return [
            "address_line1" => $user->oxuser__oxstreet->rawValue . ' ' . $user->oxuser__oxstreetnr->rawValue,
            "city" => $user->oxuser__oxcity->rawValue,
            "country_code" => $billingCountryCode,
            "zip_code" => $user->oxuser__oxzip->rawValue
        ];
    }

    protected function getUserDeliveryAddress()
    {
        $deliveryAddress = $this->getDeliveryAddress();

        if ($deliveryAddress != null) {
            $deliveryCountry = oxNew(\OxidEsales\Eshop\Application\Model\Country::class);
            $deliveryCountryId = $deliveryAddress->oxaddress__oxcountryid->value;
            $deliveryCountryCode = $deliveryCountry->getCodeById($deliveryCountryId);

            return [
                "address_line1" => $deliveryAddress->oxaddress__oxstreet->rawValue . ' ' . $deliveryAddress->oxaddress__oxstreetnr->rawValue,
                "city" => $deliveryAddress->oxaddress__oxcity->rawValue,
                "country_code" => $deliveryCountryCode,
                "zip_code" => $deliveryAddress->oxaddress__oxzip->rawValue
            ];
        }

        return $this->getUserBillingAddress();
    }
}
