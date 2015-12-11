<?php

namespace Omnipay\Klarna\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Authorize Request
 *
 * @method Response send()
 */
class CheckoutCaptureResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return $this->isAccepted();
    }


    public function getTransactionReference()
    {
        return $this->getInvoiceNumber();
    }


    public function isAccepted()
    {
        //return ('ok' === strtolower($this->getRiskStatus()));
        if ($this->getRiskStatus()) return true;
    }


    public function getRiskStatus()
    {
        if (is_array($this->data) and isset($this->data[0])) {
            return $this->data[0];
        }
        return null;
    }


    public function getInvoiceNumber()
    {
        if (is_array($this->data) and isset($this->data[1])) {
            return $this->data[1];
        }
        return null;
    }
}
