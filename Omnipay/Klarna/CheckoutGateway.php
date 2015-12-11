<?php

namespace Omnipay\Klarna;

use Omnipay\Common\AbstractGateway;

/**
 * Klarna Gateway
 */
class CheckoutGateway extends AbstractGateway
{
    public function getName()
    {
        return 'Klarna - Checkout';
    }

    public function getDefaultParameters()
    {
        return array(
            'merchantId' => '',
            'sharedSecret' => '',
            'language' => '',
            'country' => '',
            'testMode' => false,
        );
    }

    public function getMerchantId()
    {
        return $this->getParameter('merchantId');
    }

    public function setMerchantId($value)
    {
        return $this->setParameter('merchantId', $value);
    }

    public function getSharedSecret()
    {
        return $this->getParameter('sharedSecret');
    }

    public function setSharedSecret($value)
    {
        return $this->setParameter('sharedSecret', $value);
    }

    public function getLanguage()
    {
        return $this->getParameter('language');
    }

    public function setLanguage($value)
    {
        return $this->setParameter('language', $value);
    }

    public function getCountry()
    {
        return $this->getParameter('country');
    }

    public function setCountry($value)
    {
        return $this->setParameter('country', $value);
    }

    /**
     * @param array $parameters
     * @return Message\PurchaseRequest
     */
    public function capture(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Klarna\Message\CheckoutCaptureRequest', $parameters);
    }
}