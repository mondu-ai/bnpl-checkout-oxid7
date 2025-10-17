<?php

namespace OxidEsales\MonduPayment\Core\Exception;

use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\MonduPayment\Core\Logger;

class InvalidRequestException extends StandardException
{
    protected $_requestBody;
    protected $_response;

    public function __construct($message, $requestBody = null, $response = null)
    {
        $logger = oxNew(Logger::class)->getLogger();
        $logger->error($message);

        $this->_requestBody = $requestBody;
        $this->_response = $response;

        parent::__construct($message);
    }

    public function getRequestBody()
    {
        return $this->_requestBody;
    }

    public function getResponseStatus()
    {
        if ($this->_response && $this->_response['status']) {
            return $this->_response['status'];
        }

        return $this->_response;
    }

    public function getResponseBody()
    {
        return $this->_response;
    }
}
