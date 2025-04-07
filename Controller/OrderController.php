<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\MonduPayment\Core\Http\MonduClient;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

class OrderController extends OrderController_parent
{
    private MonduClient $_client;
    private User|null|false $_oUser;
    private LoggerInterface $_logger;

    public function __construct()
    {
        parent::__construct();

        $this->_client = oxNew(MonduClient::class);
        $this->_oUser = $this->getUser();
        $this->_logger = Registry::getLogger();
    }

    public function isMonduPayment()
    {
        $session = Registry::getSession();
        $paymentId = $session->getVariable('paymentid');

        return MonduHelper::isMonduPayment($paymentId);
    }

    public function getPaymentPageUrl()
    {
        $shopUrl = Registry::getConfig()->getShopSecureHomeURL();
        return $shopUrl . '&cl=payment&payerror=2';
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if($this->isMonduPayment()) {
            $orderUuid = Registry::getRequest()->getRequestEscapedParameter('order_uuid');

            if (!$orderUuid) {
                throw new \Exception('Mondu: Not found');
            }

            $oBasket = $this->getBasket();

            try {
                $iSuccess = $this->monduExecute($oBasket, $orderUuid);

                return $this->getNextStep($iSuccess);
            } catch (Exception $e) {
                throw new \Exception('Mondu: Error during the order process');
            }
        }

        // if user is not logged in set the user
        if(!$this->getUser() && isset($this->_oUser)){
            $this->setUser($this->_oUser);
        }

        return parent::execute();
    }

    /**
     * Save order to database, delete order_id from session and redirect to thank you page
     *
     * @param Basket $oBasket
     * @param        $orderUuid
     * @return string
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function monduExecute(Basket $oBasket, $orderUuid)
    {
        if (!Registry::getSession()->getVariable('sess_challenge')) {
            Registry::getSession()->setVariable('sess_challenge', Registry::getUtilsObject()->generateUID());
        }

        $iSuccess = 0;
        $oBasket->calculateBasket(true);
        $oOrder = oxNew(Order::class);

        try {
            $iSuccess = $oOrder->finalizeOrder($oBasket, $oBasket->getUser());
        } catch (\Exception $e) {
            Registry::get(UtilsView::class)->addErrorToDisplay($e);
        }

        if ($iSuccess === 1) {
            // performing special actions after user finishes order (assignment to special user groups)
            $this->_oUser->onOrderExecute($oBasket, $iSuccess);
        }

        $this->confirmOrder($oBasket, $orderUuid);

        return $iSuccess;
    }

    /**
     * @param $oBasket
     * @param $orderUuid
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function confirmOrder($oBasket, $orderUuid)
    {
        if (!$oBasket->getOrderId()) {
            return;
        }

        $data = [];
        $order = oxNew(Order::class);
        $order->load($oBasket->getOrderId());
        $data['external_reference_id'] = (string) $order->getFieldData('oxorder__oxordernr');

        $this->_logger->debug('MonduOrderController [execute $data]: ' . print_r($data, true));
        $this->_logger->debug('MonduOrderController [execute $orderUuid]: ' . print_r($orderUuid, true));
        $response = $this->_client->confirmOrder($orderUuid, $data);
        $this->_logger->debug('MonduOrderController [execute $response]: ' . print_r($response, true));

        if (isset($response['state']) && ($response['state'] == 'confirmed' || $response['state'] == 'pending')) {
            $isPending = $response['state'] == 'pending';

            if ($isPending) {
                $sQuery = "
                    UPDATE 
                        oxorder
                    SET 
                        oxfolder = 'ORDERFOLDER_PROBLEMS', 
                        oxtransstatus = 'PENDING'
                    WHERE 
                        OXORDERNR = '{$order->getFieldData('oxorder__oxordernr')}'
                ";
                DatabaseProvider::getDb()->execute($sQuery);
            }

            $this->_logger->debug(
                'MonduOrderController [execute $response]: ' . print_r($response, true)
            );
        }
    }
}