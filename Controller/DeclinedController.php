<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsView;

class DeclinedController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    public function render()
    {
        Registry::get(UtilsView::class)->addErrorToDisplay('Mondu: Order has been declined');
        Registry::getUtils()->redirect(Registry::getConfig()->getShopSecureHomeUrl() . 'cl=payment', false);
    }
}
