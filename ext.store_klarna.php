<?php

require __DIR__.'/vendor/autoload.php';

class Store_klarna_ext
{
    public $name = 'Store Klarna Checkout Payment Gateway';
    public $version = '1.0.1';
    public $description = 'A custom payment gateway for Expresso Store 2.1+.';
    public $settings_exist = 'n';
    public $docs_url = 'https://www.devdemon.com';
    public $hooks    = array('store_payment_gateways', 'sessions_end');
    public $settings = array();

    public $locales = array(
        'sv-se' => 'Sweden - Swedish',
        'fi-fi' => 'Finland - Finnish',
        'sv-fi' => 'Finland - Swedish',
        'nb-no' => 'Norway - Norwegian',
        'de-de' => 'Germany - German',
        'de-at' => 'Austria - German',
    );

    /**
     * This hook is called when Store is searching for available payment gateways
     * We will use it to tell Store about our custom gateway
     */
    public function store_payment_gateways($gateways)
    {
        //ee()->lang->loadfile('store_gestpay');

        if (ee()->extensions->last_call !== false) {
            $gateways = ee()->extensions->last_call;
        }

        // tell Store about our new payment gateway
        // (this must match the name of your gateway in the Omnipay directory)
        $gateways[] = 'Klarna_Checkout';

        // tell PHP where to find the gateway classes
        // Store will automatically include your files when they are needed
        $composer = require(PATH_THIRD.'store/autoload.php');
        $composer->add('Omnipay', __DIR__);

        return $gateways;
    }

    public function sessions_end($session)
    {
        if (ee()->uri->segment(1) == 'klarna_checkout_return') {
            // assign the session object prematurely, since EE won't need it anyway
            // (this hook runs inside the Session object constructor, which is a bit weird)
            ee()->session = $session;
            if (!defined('CSRF_TOKEN')) define('CSRF_TOKEN', '');

            $gateway = ee()->store->payments->load_payment_method('Klarna_Checkout');
            $klarnaOrder = ee()->input->get_post('klarna_order_id');
            $orderHash = ee()->input->get_post('order_hash');
            $cart = \Store\Model\Order::where('order_hash', $orderHash)->first();

            $connector = Klarna_Checkout_Connector::create($gateway->getSharedSecret(), $this->getEndpointUrl($gateway->getTestMode()));
            $order = new Klarna_Checkout_Order($connector, $klarnaOrder);

            try {
                $order->fetch();
                $update = array();

                // Transaction
                $trans = ee()->store->payments->new_transaction($cart);
                $trans->amount = $cart->order_owing;
                $trans->payment_method = 'Klarna_Checkout';
                $trans->type = config_item('store_cc_payment_method');
                $trans->reference = $order['reservation'];
                $trans->save();

                if ($order['status'] == 'checkout_complete') {
                //if ($order['status'] == 'created') {
                    // Update The DB
                    $cart->order_custom13 = $order['reference'];
                    $cart->order_custom14 = $order['reservation'];
                    $cart->order_custom15 = '';
                    $cart->save();

                    $update['status'] = 'created';
                    $order->update($update);

                    $cart->payment_method = $trans->payment_method;
                    ee()->store->payments->update_order_paid_total($cart);

                    if ($trans->type == 'purchase') {
                        $klarnaConnector = new Klarna();
                        $country = KlarnaCountry::fromCode($gateway->getCountry());
                        $language = KlarnaLanguage::fromCode($gateway->getLanguage());
                        $currency = KlarnaCurrency::fromCode(config_item('store_currency_code'));
                        $mode = ($gateway->getTestMode()) ? Klarna::BETA : Klarna::LIVE;
                        $klarnaConnector->config(
                            $gateway->getMerchantId(),
                            $gateway->getSharedSecret(),
                            $country,
                            $language,
                            $currency,
                            $mode
                        );

                        $res = $klarnaConnector->activate($order['reservation'], null, null);

                        if (isset($res[1])) {
                            $cart->order_custom15 = $res[1];
                            $cart->save();

                            $trans->status = 'success';
                            $trans->reference = $res[1];
                            $trans->save();
                        } else {
                            exit($res);
                        }
                    } else {
                        $trans->status = 'success';
                        $trans->save();
                    }

                    $cart->payment_method = $trans->payment_method;
                    ee()->store->payments->update_order_paid_total($cart);

                    exit('ORDER UPDATED - ' . $trans->type . ' - ' . $trans->status);
                }
            } catch (Exception $e) {
                exit($e->getMessage());
            }

            exit('OK');
        }
    }

    protected function getEndpointUrl($testMode)
    {
        if ($testMode == 'y') {
            return 'https://checkout.testdrive.klarna.com';
        }

        return 'https://checkout.klarna.com';
    }

    public function settings()
    {
        $settings = array();

        // Creates a text input with a default value of "EllisLab Brand Butter"
        $settings['merchant_id']    = array('i', '');
        $settings['shared_secret']  = array('i', '');
        $settings['locale']         = array('s', $this->locales);
        $settings['test_mode']      = array('r', array('y' => "Yes", 'n' => "No"), 'n');

        return $settings;
    }

    /**
     * Called by ExpressionEngine when the user activates the extension.
     *
     * @access      public
     * @return      void
     **/
    public function activate_extension()
    {
        foreach ($this->hooks as $hook) {
             $data = array( 'class'     =>  __CLASS__,
                            'method'    =>  $hook,
                            'hook'      =>  $hook,
                            'settings'  =>  serialize($this->settings),
                            'priority'  =>  10,
                            'version'   =>  $this->version,
                            'enabled'   =>  'y'
                );

            // insert in database
            ee()->db->insert('exp_extensions', $data);
        }
    }

    /**
     * Called by ExpressionEngine updates the extension
     *
     * @access public
     * @return void
     **/
    public function update_extension($current = '')
    {
        if ($current == $this->version) return false;

        $settings = array();

        //----------------------------------------
        // Get all existing hooks
        //----------------------------------------
        $dbexts = array();
        $query = ee()->db->select('*')->from('exp_extensions')->where('class', __CLASS__)->get();

        foreach ($query->result() as $row) {
            $dbexts[$row->hook] = $row;
            if ($row->settings) $settings = unserialize($row->settings);
        }

        //----------------------------------------
        // Add new hooks
        //----------------------------------------
        foreach ($this->hooks as $hook) {
            if (isset($dbexts[$hook]) === true) continue;

            $data = array(
                'class'     =>  __CLASS__,
                'method'    =>  $hook,
                'hook'      =>  $hook,
                'settings'  =>  serialize($settings),
                'priority'  =>  100,
                'version'   =>  $this->version,
                'enabled'   =>  'y'
            );

            // insert in database
            ee()->db->insert('exp_extensions', $data);
        }

        //----------------------------------------
        // Delete old hooks
        //----------------------------------------
        foreach ($dbexts as $hook => $ext) {
            if (in_array($hook, $this->hooks) === true) continue;

            ee()->db->where('hook', $hook);
            ee()->db->where('class', __CLASS__);
            ee()->db->delete('exp_extensions');
        }

        // Update the version number for all remaining hooks
        ee()->db->where('class', __CLASS__)->update('extensions', array('version' => $this->version));
    }

    /**
     * Called by ExpressionEngine when the user disables the extension.
     *
     * @access      public
     * @return      void
     **/
    public function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('exp_extensions');
    }
}