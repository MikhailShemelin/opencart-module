<?php

require_once(DIR_APPLICATION . 'controller/payment/svea_common.php');
require_once(DIR_APPLICATION . '../svea/config/configInclude.php');

class ControllerSveaPush extends Controller
{
    public function index()
    {
        $checkout_order_id = isset($this->request->get['svea_order']) ? trim($this->request->get['svea_order']) : null;

        if ($checkout_order_id != null) {
            $test_mode = $this->config->get('sco_test_mode');

            $config = new OpencartSveaCheckoutConfig($this->config, 'checkout');
            if ($test_mode) {
                $config = new OpencartSveaCheckoutConfigTest($this->config, 'checkout');
            }

            $checkout_entry = \Svea\WebPay\WebPay::checkout($config);
            $checkout_entry->setCheckoutOrderId($checkout_order_id);

            // Get Svea Checkout order
            try {
                $response = $checkout_entry->getOrder();
                $response = lowerArrayKeys($response);
            } catch (Exception $e) {
                die($e->getMessage());
            }

            $this->updateOrders($response);

            // Set response header
            header("HTTP/1.1 200 OK");
        } else {
            header("HTTP/1.1 400 BadRequest");
        }

        // End of logic!
        exit;
    }


    private function updateOrders($response)
    {
        // - update order
        $this->updateOrder($response);

        // - update sco order
        $this->updateCheckoutOrder($response);
    }

    private function updateOrder($response)
    {
        $this->load->model('svea/checkout');
        $this->model_svea_checkout->updateOrder($response['clientordernumber'], $response);
    }

    private function updateCheckoutOrder($response)
    {
        $this->load->model('svea/checkout');

        $country = $this->model_svea_checkout->getCountry($response['countrycode']);

        $date_of_birth = null;
        if (isset($response['customer']['dateofbirth']) && !empty($response['customer']['dateofbirth'])) {
            $date_of_birth =  $response['customer']['dateofbirth'];
        }

        $data = array(
            'order_id' => $response['clientordernumber'],
            'checkout_id' => $response['orderid'],
            'status' => isset($response['status']) ? $response['status'] : null,

            'type' => isset($response['customer']['iscompany']) ? ($response['customer']['iscompany'] ? 'company' : 'person') : 'person',
            'gender' => isset($response['customer']['ismale']) ? ($response['customer']['ismale'] ? 'male' : 'female') : null,
            'date_of_birth' => $date_of_birth,

            'locale' => isset($response['locale']) ? $response['locale'] : null,
            'currency' => isset($response['currency']) ? $response['currency'] : null,
            'country' => $country['name'],
        );

        $this->model_svea_checkout->updateCheckoutOrder($data);
    }
}
