<?php

namespace OxidEsales\MonduPayment\Core\Utils;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Bridge\ModuleActivationBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;

class MonduHelper
{
    public static function removeEmptyElementsFromArray(array $array)
    {
        return array_filter($array, function ($v) {
            return !is_null($v) && $v !== '';
        });
    }

    public static function showErrorMessage($message = '')
    {
        Registry::getUtilsView()->addErrorToDisplay($message, false);
    }

    public static function isMonduPayment($paymentId = '')
    {
        return stripos($paymentId, "oxmondu") !== false;
    }

    public static function isMonduModuleActive()
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $moduleActivationBridge = $container->get(ModuleActivationBridgeInterface::class);

        return $moduleActivationBridge->isActive(
            'oemondu',
            Registry::getConfig()->getShopId()
        );
    }

    public static function camelToSnakeCase($string)
    {
        return strtoupper(preg_replace("/([a-z])([A-Z])/", "$1_$2", $string));
    }

    public static function getMappedOrderArticles($orderArticles)
    {
        return array_map(function ($a) {
            $article = $a->getArticle();

            return [
                'external_reference_id' => $article->getFieldData('oxid'),
                'title' => $article->getFieldData('oxtitle'),
                'net_price_per_item_cents' => round($a->getBasePrice()->getNettoPrice() * 100),
                'quantity' => (int) $a->getFieldData('oxamount')
            ];
        }, $orderArticles);
    }

    public static function ordersHaveSameArticles($order1, $order2)
    {
        $array1 = array_values($order1->getOrderArticles()->getArray());
        $array2 = array_values($order2->getOrderArticles()->getArray());

        $mappedArr1 = self::getMappedOrderArticles($array1);
        $mappedArr2 = self::getMappedOrderArticles($array2);

        return array_diff($mappedArr1, $mappedArr2) == array_diff($mappedArr2, $mappedArr1);
    }

    public static function isOrderAdjusted($oldOrder, $newOrder)
    {
        return $oldOrder->getOrderCurrency()->name != $newOrder->getOrderCurrency()->name ||
            $oldOrder->getId() != $newOrder->getId() ||
            $oldOrder->getOrderNetSum() != $newOrder->getOrderNetSum() ||
            array_values($oldOrder->getProductVats(false))[0] != array_values($newOrder->getProductVats(false))[0] ||
            $oldOrder->getFieldData('oxtotalordersum') != $newOrder->getFieldData('oxtotalordersum') ||
            $oldOrder->getFieldData('oxorder__oxdelcost') != $newOrder->getFieldData('oxorder__oxdelcost') ||
            $oldOrder->getFieldData('oxorder__oxdiscount') != $newOrder->getFieldData('oxorder__oxdiscount') ||
            !self::ordersHaveSameArticles($oldOrder, $newOrder);
    }

    public static function getAllShopIds()
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $queryBuilderFactory = $container->get(QueryBuilderFactoryInterface::class);

        $queryBuilder = $queryBuilderFactory->create();
        $queryBuilder->select('OXID')
                     ->from('oxshops');

        return $queryBuilder->execute()->fetchAllAssociative();
    }
}
