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

    /**
     * Address without scheme and "www.", so that http/https and www/non-www variants of the shop match.
     */
    public static function normalizeAddress(string $address): string
    {
        $address = strtolower(trim($address));
        $address = preg_replace('#^https?://#', '', $address);
        $address = preg_replace('#^www\.#', '', $address);

        return $address;
    }

    public function getData()
    {
        return [
            'topic' => $this->getTopic(),
            'address' => $this->getAddress()
        ];
    }
}
