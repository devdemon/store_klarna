<?php

namespace Omnipay\Klarna\Message;

use Omnipay\Common\Message\AbstractRequest as BaseAbstractRequest;

abstract class CheckoutAbstractRequest extends BaseAbstractRequest
{
    protected function getEndpointUrl($testMode)
    {
        if ($testMode) {
            return 'https://checkout.testdrive.klarna.com';
        }

        return 'https://checkout.klarna.com';
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
}