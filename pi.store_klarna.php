<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (defined('PATH_THIRD')) {
    require PATH_THIRD.'store/autoload.php';
}

$plugin_info = array(
    'pi_name'         => 'Store - Klarna (Checkout)',
    'pi_version'      => '1.0',
    'pi_author'       => 'DevDemon',
    'pi_author_url'   => 'http://www.devdemon.com/',
    'pi_description'  => 'Outputs Klarna Specific Tags',
    'pi_usage'        => Store_klarna::usage()
);

class Store_klarna
{

    public $return_data = '';

    // --------------------------------------------------------------------

    /**
     * Memberlist
     *
     * This function returns a list of members
     *
     * @access  public
     * @return  string
     */
    public function __construct()
    {

    }

    public function checkout()
    {
        session_start();
        $cart = ee()->store->orders->get_cart();
        $gateway = ee()->store->payments->load_payment_method('Klarna_Checkout');
        $klarnaOrder = !empty($_SESSION['klarna_order_id']) ? $_SESSION['klarna_order_id'] : null;

        // URLS
        $termsUrl = ee()->functions->create_url(ee()->TMPL->fetch_param('terms_url'));
        //$authorizeUrl = ee()->functions->create_url(ee()->TMPL->fetch_param('authorize_url'));
        $confirmationUrl = ee()->functions->create_url(ee()->TMPL->fetch_param('confirmation_url'));
        $pushUrl = ee()->functions->create_url('/klarna_checkout_return');

        // Vars
        $vars = array();
        $vars['klarna_order_id'] = '';
        $vars['klarna_widget'] = '';
        $vars['klarna_error'] = '';

        //----------------------------------------
        // Create the Cart Items Array
        //----------------------------------------
        $items = array();

        foreach ($cart->items as $item) {
            $items[] = array(
                'name'       => $item->title,
                'reference'  => $item->stock->sku ?: $item->product->entry_id,
                'unit_price' => intval(bcmul($item->item_total / $item->item_qty, '100', 0)), // Unit Price needs to be WITH tax!
                'quantity'   => (int) $item->item_qty,
                //'tax_rate'   => ($item->item_tax > 0) ? (int) number_format((($item->item_tax / $item->price) * 100) * 100, 0) : 0,
                'tax_rate'   => ($item->item_tax > 0) ? (int) intval(bcmul(($cart->tax_rate * 100), '100', 0)) : 0,
            );
        }

        //----------------------------------------
        // Shipping?
        //----------------------------------------
        if ($cart->order_shipping_total > 0) {
            $items[] = array(
                'name'       => $cart->shipping_method,
                'reference'  => 'SHIPPING',
                'unit_price' => intval(bcmul($cart->order_shipping, '100', 0)),
                'quantity'   => 1,
                //'tax_rate'   => ($cart->order_shipping_tax > 0) ? (int) number_format((($item->order_shipping_tax / $item->order_shipping) * 100) * 100, 0) : 0,
                'tax_rate'   => ($cart->order_shipping_tax > 0) ? (int) intval(bcmul(($cart->tax_rate * 100), '100', 0)) : 0,
            );
        }

        //----------------------------------------
        // Update Existing Order
        //----------------------------------------
        if ($klarnaOrder) {
            $connector = Klarna_Checkout_Connector::create($gateway->getSharedSecret(), $this->getEndpointUrl($gateway->getTestMode()));
            $order = new Klarna_Checkout_Order($connector, $klarnaOrder);

            // Reset cart
            $update = array();
            $update['cart']['items'] = $items;

            try {
                $order->fetch();
                $order->update($update);

                $vars['klarna_order_id'] = $order['id'];
                $vars['klarna_widget'] = $order['gui']['snippet'];
            } catch (Exception $e) {
                $vars['klarna_error'] = $e->getMessage();
            }
        }

        //----------------------------------------
        // Create new one
        //----------------------------------------
        if (!$klarnaOrder) {
            $create = array();
            $create['merchant_reference']['orderid1'] = $cart->id;
            $create['cart']['items'] = $items;
            $create['purchase_country'] = strtoupper($gateway->getCountry());
            $create['purchase_currency'] = config_item('store_currency_code');
            $create['locale'] = strtolower($gateway->getLanguage().'-'.$gateway->getCountry());
            $create['merchant']['id'] = $gateway->getMerchantId();
            $create['merchant']['terms_uri'] = $termsUrl;
            $create['merchant']['checkout_uri'] = ee()->functions->fetch_current_uri();
            $create['merchant']['confirmation_uri'] = $confirmationUrl . '?klarna_order_id={checkout.order.id}&order_hash=' . $cart->order_hash;
            $create['merchant']['push_uri'] = $pushUrl . '?klarna_order_id={checkout.order.id}&order_hash=' . $cart->order_hash;

            $connector = Klarna_Checkout_Connector::create($gateway->getSharedSecret(), $this->getEndpointUrl($gateway->getTestMode()));
            $order = new Klarna_Checkout_Order($connector);

            try {
                $order->create($create);
                $order->fetch();

                $vars['klarna_order_id'] = $order['id'];
                $vars['klarna_widget'] = $order['gui']['snippet'];

                // Store location of checkout session
                $_SESSION['klarna_order_id'] = $order['id'];
            } catch (Exception $e) {
                $vars['klarna_error'] = $e->getMessage();
            }
        }

        return ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
    }

    public function confirmation()
    {
        session_start();
        $cart = ee()->store->orders->get_cart();
        $gateway = ee()->store->payments->load_payment_method('Klarna_Checkout');
        $klarnaOrder = !empty($_SESSION['klarna_order_id']) ? $_SESSION['klarna_order_id'] : null;

        $vars = array();
        $vars['klarna_order_id'] = '';
        $vars['klarna_widget'] = '';
        $vars['klarna_error'] = '';

        if (!$klarnaOrder) {
            $vars['klarna_error'] = 'no_order';
            return ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
        }

        $connector = Klarna_Checkout_Connector::create($gateway->getSharedSecret(), $this->getEndpointUrl($gateway->getTestMode()));
        $order = new Klarna_Checkout_Order($connector, $klarnaOrder);

        try {
            $order->fetch();

            $vars['klarna_order_id'] = $order['id'];
            $vars['klarna_widget'] = $order['gui']['snippet'];
            $vars['klarna_status'] = $order['status'];

            // Store location of checkout session
            $_SESSION['klarna_order_id'] = $order['id'];
        } catch (Exception $e) {
            $vars['klarna_error'] = $e->getMessage();
        }

        unset($_SESSION['klarna_checkout']);
        unset($_SESSION['klarna_order_id']);

        return ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
    }

    public function clear_checkout()
    {
        session_start();
        unset($_SESSION['klarna_checkout']);
        unset($_SESSION['klarna_order_id']);

        return 'Session Cleared';
    }

    protected function getEndpointUrl($testMode)
    {
        if ($testMode) {
            return 'https://checkout.testdrive.klarna.com';
        }

        return 'https://checkout.klarna.com';
    }

    /**
     * Usage
     *
     * This function describes how the plugin is used.
     *
     * @access  public
     * @return  string
     */
    public static function usage()
    {
        ob_start();  ?>



    {exp:store_klarna:checkout}



    <?php
        $buffer = ob_get_contents();
        ob_end_clean();

        return $buffer;
    }
    // END
}