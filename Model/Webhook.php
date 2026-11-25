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
     * Mondu treats a webhook as a duplicate only on the exact address. Scheme and host are case-insensitive
     * in a URL, so only those are lowercased; http/https and www variants stay different addresses.
     */
    public static function normalizeAddress(string $address): string
    {
        $address = trim($address);

        if (preg_match('#^([a-z][a-z0-9+.-]*://)([^/?\#]*)(.*)$#is', $address, $parts)) {
            return strtolower($parts[1]) . strtolower($parts[2]) . $parts[3];
        }

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
