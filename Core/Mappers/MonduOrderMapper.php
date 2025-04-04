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
        $tax = array_values($basket->getProductVats(false))[0];
        $discount = $basket->getTotalDiscount()->getPrice();
        $shipping = $basket->getDeliveryCost()->getPrice();
        $shopUrl = Registry::getConfig()->getCurrentShopUrl();

        $externalReferenceId = uniqid('M_OX_');
        $data = [
            "currency" => $basket->getBasketCurrency()->name,
            "payment_method" => $paymentMethod,
            "external_reference_id" => $externalReferenceId,
            "gross_amount_cents" => round($basket->getPriceForPayment() * 100),
            "buyer" => MonduHelper::removeEmptyElementsFromArray($this->getBuyerData()),
            "billing_address" => MonduHelper::removeEmptyElementsFromArray($this->getUserBillingAddress()),
            "shipping_address" => MonduHelper::removeEmptyElementsFromArray($this->getUserDeliveryAddress()),
            "success_url" => $shopUrl . '?cl=order&fnc=execute&order_uuid=' . $monduOrderUuid . '&sDeliveryAddressMD5=' . $this->getBasketUser()->getEncodedDeliveryAddress(),
            "cancel_url" => $shopUrl . '?cl=oemonducancel',
            "declined_url" => $shopUrl . '?cl=oemondudeclined',
            "state_flow" => 'authorization_flow',
            "lines" => [[
                "tax_cents" => round($tax * 100),
                "buyer_fee_cents" => $this->buyerFeeCentsService?->getBuyerFeeCentsFromBasked($basket),
                "shipping_price_cents" => round($shipping * 100),
                "discount_cents" => round($discount * 100),
                "line_items" => $this->getOrderLineItems()
            ]]
        ];

        return MonduHelper::removeEmptyElementsFromArray($data);
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
                'net_price_per_item_cents' => round($lineItem->getUnitPrice()->getNettoPrice() * 100),
                'quantity' => $lineItem->getAmount()
            ];
        }

        return $lineItems;
    }

    protected function getBuyerData()
    {
        $user = $this->getBasketUser();

        return [
            "email" => $user->oxuser__oxusername->rawValue,
            "first_name" => $user->oxuser__oxfname->rawValue,
            "last_name" => $user->oxuser__oxlname->rawValue,
            "company_name" => $user->oxuser__oxcompany->rawValue,
            "phone" => $user->oxuser__oxfon->rawValue
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
