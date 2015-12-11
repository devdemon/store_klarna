<?php

namespace Omnipay\Klarna\Message;

use Klarna, KlarnaCountry, KlarnaLanguage, KlarnaCurrency;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Authorize Request
 *
 * @method Response send()
 */
class CheckoutCaptureRequest extends CheckoutAbstractRequest
{
    public function getData()
    {
        $this->validate('transactionReference');

        return array(
            'transactionReference' => $this->getTransactionReference(),
            'amount' => $this->getAmount(),
        );
    }

    /**
     * Send the request with specified data
     *
     * @param  mixed $data The data to send
     * @return ResponseInterface
     */
    public function sendData($data)
    {
        $klarnaConnector = new Klarna();
        $country = KlarnaCountry::fromCode($this->getCountry());
        $language = KlarnaLanguage::fromCode($this->getLanguage());
        $currency = KlarnaCurrency::fromCode(config_item('store_currency_code'));
        $mode = ($this->getTestMode()) ? Klarna::BETA : Klarna::LIVE;
        $klarnaConnector->config(
            $this->getMerchantId(),
            $this->getSharedSecret(),
            $country,
            $language,
            $currency,
            $mode
        );

        $res = $klarnaConnector->activate($data['transactionReference'], null, null);

        return $this->response = new CheckoutCaptureResponse($this, $res);
    }
}
