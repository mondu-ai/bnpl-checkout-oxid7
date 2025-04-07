<?php

namespace OxidEsales\MonduPayment\Model;

class Webhook
{
    private string $_topic;
    private string $_address;

    public function __construct($topic)
    {
        $this->_topic = $topic;
        $this->_address = $_SERVER['HTTP_ORIGIN'] . "?cl=oemonduwebhooks";
    }

    public function getTopic(): string
    {
        return $this->_topic;
    }

    public function getAddress(): string
    {
        return $this->_address;
    }

    public function getData()
    {
        return [
            'topic' => $this->getTopic(),
            'address' => $this->getAddress()
        ];
    }
}
