<?php

namespace OxidEsales\MonduPayment\Core;

use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\MonduPayment\Model\MonduPayment;

class Events
{
    protected static function createMonduOrdersTable()
    {
        $query = "CREATE TABLE IF NOT EXISTS `oemondu_orders` (
            `OXID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
            `OXID_ORDER_ID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
            `ORDER_UUID` VARCHAR(36) NOT NULL,
            `ORDER_STATE` VARCHAR(20) NOT NULL,
            `AUTHORIZED_NET_TERM` INT,
            `CREATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `UPDATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`OXID`),
            FOREIGN KEY (`OXID_ORDER_ID`) REFERENCES `oxorder` (`OXID`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

        DatabaseProvider::getDb()->execute($query);
    }

    protected static function createMonduInvoicesTable()
    {
        $query = "CREATE TABLE IF NOT EXISTS `oemondu_invoices` (
            `OXID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
            `INVOICE_ID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
            `INVOICE_UUID` VARCHAR(36) NOT NULL,
            `MONDU_ORDER_ID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
            `INVOICE_STATE` VARCHAR(20) NOT NULL,
            `CREATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `UPDATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`OXID`),
            FOREIGN KEY (`MONDU_ORDER_ID`) REFERENCES `oemondu_orders` (`OXID`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

        DatabaseProvider::getDb()->execute($query);
    }

    protected static function addPaymentMethods()
    {
        $payment = oxNew(Payment::class);
        $language = Registry::getLang();
        $languages = $language->getLanguageIds();

        foreach (MonduPayment::MONDU_PAYMENT_METHODS as $paymentMethod) {
            if (!$payment->load($paymentMethod['payment_id'])) {
                $payment->setId($paymentMethod['payment_id']);
                $payment->oxpayments__oxactive = new Field(1);
                $payment->oxpayments__oxaddsum = new Field(0);
                $payment->oxpayments__oxaddsumtype = new Field('abs');
                $payment->oxpayments__oxfromboni = new Field(0);
                $payment->oxpayments__oxfromamount = new Field(0);
                $payment->oxpayments__oxtoamount = new Field(10000);

                foreach ($paymentMethod['translations'] as $languageAbbreviation => $translation) {
                    $languageId = array_search($languageAbbreviation, $languages);
                    if ($languageId !== false) {
                        $payment->setLanguage($languageId);
                        $payment->oxpayments__oxdesc = new Field($translation['name']);
                        $payment->oxpayments__oxlongdesc = new Field($translation['description']);
                        $payment->save();
                    }
                }

                $payment->save();
            }
        }
    }

    protected static function enablePaymentMethods()
    {
        $payment = oxNew(Payment::class);

        foreach (MonduPayment::MONDU_PAYMENT_METHODS as $paymentMethod) {
            if ($payment->load($paymentMethod['payment_id'])) {
                $payment->oxpayments__oxactive = new Field(1);
                $payment->save();
            }
        }
    }

    protected static function enableMonduRDFA()
    {
        $query = "INSERT IGNORE INTO `oxobject2payment` (`OXID`, `OXPAYMENTID`, `OXOBJECTID`, `OXTYPE`) VALUES('oemondurdfa', 'oxmondu', 'Mondu', 'rdfapayment')";
        DatabaseProvider::getDb()->execute($query);
    }

    protected static function disablePaymentMethods()
    {
        $payment = oxNew(Payment::class);

        foreach (MonduPayment::MONDU_PAYMENT_METHODS as $paymentMethod) {
            if ($payment->load($paymentMethod['payment_id'])) {
                $payment->oxpayments__oxactive = new Field(0);
                $payment->save();
            }
        }
    }

    protected static function disableMonduRDFA()
    {
        $query = "DELETE FROM `oxobject2payment` WHERE `OXID` = 'oemondurdfa'";
        DatabaseProvider::getDb()->execute($query);
    }

    protected static function deleteSessionBasket()
    {
        Registry::getSession()->delBasket();
    }

    public static function onActivate()
    {
        self::createMonduOrdersTable();
        self::createMonduInvoicesTable();

        self::addPaymentMethods();
        self::enablePaymentMethods();
        self::enableMonduRDFA();
    }

    public static function onDeactivate()
    {
        self::disablePaymentMethods();
        self::disableMonduRDFA();
        self::deleteSessionBasket();
    }
}
