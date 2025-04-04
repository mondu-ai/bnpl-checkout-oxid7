<?php

namespace OxidEsales\MonduPayment\Controller;

use OxidEsales\Eshop\Core\Registry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OxidEsales\MonduPayment\Core\WebhookHandler\WebhookHandler;
use OxidEsales\MonduPayment\Core\Config;
use OxidEsales\MonduPayment\Core\Utils\MonduHelper;

class MonduWebhooksController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    private $_webhookHandler;
    private $_config;
    private $_logger;

    public function __construct()
    {
        $this->_webhookHandler = oxNew(WebhookHandler::class);
        $this->_config = oxNew(Config::class);
        $this->_logger = Registry::getLogger();
    }

    public function render()
    {
        ini_set('html_errors', 'off');

        $response = $this->handleRequest();
        $response->send();

        exit();
    }

    private function handleRequest(): Response
    {
        $content = file_get_contents('php://input');
        $params = json_decode($content, true);
        $this->_logger->debug('MonduWebhooksController [handleRequest $content]: ' . print_r($content, true));
        
        // Get headers
        $headers = getallheaders();
        $this->_logger->debug('MonduWebhooksController [handleRequest $headers]: ' . print_r($headers, true));
        
        $signatureIsValid = false;
        $shopId = $this->_webhookHandler->getShopId($params);
        $shopIds = $shopId ? [['OXID' => $shopId]] : MonduHelper::getAllShopIds();

        foreach ($shopIds as $shopId) {
            if (isset($shopId['OXID'])) {
                $signature = hash_hmac('sha256', $content, $this->_webhookHandler->getWebhookSecretByShopId($shopId['OXID']));
                if ($signature === $headers['X-Mondu-Signature']) {
                    $signatureIsValid = true;
                    break;
                }
            }
        }

        if (!$signatureIsValid) {
            return new Response('Invalid signature', 401);
        }

        [$resBody, $resStatus] = $this->_webhookHandler->handleWebhook($params);

        return new Response(
            json_encode($resBody),
            $resStatus,
            ['content-type' => 'application/json']
        );
    }
}