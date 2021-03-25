<?php
include_once(dirname(__FILE__).'/svea_common.php');
require_once(DIR_APPLICATION . '../svea/config/configInclude.php');

use \Svea\WebPay\Helper\PaymentPlanHelper\PaymentPlanCalculator;

class ControllerExtensionPaymentSveapartpayment extends SveaCommon
{
    private $paymentString = "payment_";

    public function setVersionStrings()
    {
        if (VERSION < 3.0) {
            $this->paymentString = "";
        }
    }

    /**
     * Returns the currency used for an invoice country.
     */
    protected function getPartpaymentCurrency($countryCode)
    {
        $country_currencies = array(
            'SE' => 'SEK',
            'NO' => 'NOK',
            'FI' => 'EUR',
            'DK' => 'DKK',
            'NL' => 'EUR',
            'DE' => 'EUR'
        );
        return $country_currencies[$countryCode];
    }

    public function index()
    {
        $this->setVersionStrings();

        $this->load->language('extension/payment/svea_partpayment');

        $this->load->model('checkout/order');

        $data['text_payment_options'] = $this->language->get('text_payment_options');
        $data['text_ssn'] = $this->language->get('text_ssn');
        $data['text_birthdate'] = $this->language->get('text_birthdate');
        $data['text_initials'] = $this->language->get('text_initials');
        $data['text_get_address'] = $this->language->get('text_get_address');
        $data['text_invoice_address'] = $this->language->get('text_invoice_address');
        $data['text_shipping_address'] = $this->language->get('text_shipping_address');
        $data[$this->paymentString . 'svea_partpayment_shipping_billing'] = $this->config->get($this->paymentString . 'svea_partpayment_shipping_billing');
        $data['response_no_campaign_on_amount'] = $this->language->get('response_no_campaign_on_amount');
        $data['text_required'] = $this->language->get('text_required');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');

        $data['continue'] = 'index.php?route=checkout/success';

        // Get the country from the order
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['countryCode'] = $order_info['payment_iso_code_2'];
        $data['logo'] = '<svg style="fill: #002c50;" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="94" height="35" version="1.1" viewBox="0 0 2540 930" xmlns:xlink="http://www.w3.org/1999/xlink">
            <g>
            <path d="M403 256l-172 0c-62,0 -70,-31 -70,-55 0,-49 25,-69 88,-69l334 0 0 -135 -353 0c-157,0 -230,64 -230,202 0,130 69,190 219,190l154 0c60,0 80,14 80,59 0,37 -14,57 -89,57l-338 0 0 135 359 0c156,0 229,-63 229,-198 0,-133 -61,-187 -210,-187z"></path>
            <polygon points="1137,-3 955,438 777,-3 602,-3 883,641 1034,641 1303,-3 "></polygon>
            <path d="M1572 129l226 0 0 -133 -229 0c-207,0 -304,106 -304,333 0,117 33,200 100,254 66,53 130,57 201,57l232 0 0 -133 -226 0c-94,0 -131,-35 -135,-127l361 0 0 -133 -360 0c8,-81 51,-119 133,-119z"></path>
            <path d="M2097 358l73 -191 76 191 -149 0zm1 -361l-273 644 165 0 55 -148 252 0 57 148 172 0 -275 -644 -154 0z"></path>
            <path id="streck-underline" style="fill: #00aece;" d="M2496 931l-2445 0c-17,0 -31,-14 -31,-31l0 -106c0,-17 14,-31 31,-31l2445 0c17,0 31,14 31,31l0 106c0,17 -14,31 -31,31z"></path>
            </g>
        </svg>';

        // We show the available payment plans w/monthly amounts as radiobuttons under the logo
        $data['paymentOptions'] = $this->getPaymentOptions();

        if ($data['countryCode'] == "SE") {
            $termsLink = 'https://cdn.svea.com/webpay/sv-SE/terms_paymentplan_payment_20161005.pdf';
            $companyName = 'Svea Ekonomis';
        } elseif ($data['countryCode'] == "NO") {
            $termsLink = 'https://betaling.sveafinans.no/dokumenter/Vilkaar_Svea_Checkout.pdf';
            $companyName = 'Svea Finans';
        } elseif ($data['countryCode'] == "FI") {
            $termsLink = 'https://www.svea.com/globalassets/sweden/foretag/betallosningar/e-handel/integrationspaket-logos-and-doc.-integration-test-instructions-webpay/villkorstexter_fd_finland.pdf';
            $companyName = 'Svea Ekonomi AB (publ), Filial i Finlandin';
        }

        $data['termsLink'] = $termsLink;
        $data['companyName'] = $companyName;
        $data['termsOfService1'] = $this->language->get('termsOfService1');
        $data['termsOfService2'] = $this->language->get('termsOfService2');
        $data['termsOfService3'] = $this->language->get('termsOfService3');

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/svea_partpayment')) {
            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/svea_partpayment', $data);
        } elseif (floatval(VERSION) >= 2.2) {
            return $this->load->view('extension/payment/svea_partpayment', $data);
        } else {
            return $this->load->view('default/template/extension/payment/svea_partpayment', $data);
        }
    }

    private function responseCodes($err, $msg = "")
    {
        $this->load->language('extension/payment/svea_partpayment');

        $definition = $this->language->get("response_$err");

        if (preg_match("/^response/", $definition)) {
            $definition = $this->language->get("response_error") . " $msg";
        }

        return $definition;
    }

    public function confirm()
    {
        $this->setVersionStrings();

        $this->load->language('extension/payment/svea_partpayment');

        $this->load->model('extension/payment/svea_invoice');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/svea_partpayment');
        $this->load->model('account/address');

        $response = array();

        //Get order information
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];

        //Testmode
        if ($this->config->get($this->paymentString . 'svea_partpayment_testmode_' . $countryCode) !== null) {
            $conf = $this->config->get($this->paymentString . 'svea_partpayment_testmode_' . $countryCode) == "1" ? new OpencartSveaConfigTest($this->config, $this->paymentString . 'svea_partpayment') : new OpencartSveaConfig($this->config, $this->paymentString . 'svea_partpayment');
        } else {
            $response = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        }

        $svea = \Svea\WebPay\WebPay::createOrder($conf);

        // Get the products in the cart
        $products = $this->cart->getProducts();

        // Make sure we use the currency matching the clientno
        $this->load->model('localisation/currency');
        $currency_info = $this->model_localisation_currency->getCurrencyByCode($this->getPartpaymentCurrency($countryCode));
        $currencyValue = $currency_info['value'];

        // Products
        $this->load->language('extension/payment/svea_partpayment');

        $svea = $this->addOrderRowsToWebServiceOrder($svea, $products, $currencyValue);

        // Extra charge addons like shipping and invoice fee
        $addons = $this->addTaxRateToAddons();
        $svea = $this->addAddonRowsToSveaOrder($svea, $addons, $currencyValue);
        $svea = $this->addRoundingRowIfApplicable($svea, $this->cart->getTotal(), $addons, $currencyValue);

        // Seperates the street from the housenumber according to testcases for NL and DE
        if ($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {
            $addressArr = \Svea\WebPay\Helper\Helper::splitStreetAddress($order['payment_address_1']);
        } else {
            $addressArr[1] =  $order['payment_address_1'];
            $addressArr[2] =  "";
        }

        $ssn = (isset($_GET['ssn'])) ? $_GET['ssn'] : 0;

        $item = \Svea\WebPay\BuildOrder\RowBuilders\Item::individualCustomer();
        $item = $item->setNationalIdNumber($ssn)
            ->setEmail($order['email'])
            ->setName($order['payment_firstname'], $order['payment_lastname'])
            ->setStreetAddress($addressArr[1], $addressArr[2])
            ->setZipCode($order['payment_postcode'])
            ->setLocality($order['payment_city'])
            ->setIpAddress($order['ip'])
            ->setPhoneNumber($order['telephone']);

        if ($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {
            if ($order["payment_iso_code_2"] == "NL") {
                $item = $item->setInitials($_GET['initials']);
            }
            $item = $item->setBirthDate($_GET['birthYear'], $_GET['birthMonth'], $_GET['birthDay']);
        }

        $svea = $svea->addCustomerDetails($item);

        try {
            $svea = $svea->setCountryCode($countryCode)
                ->setCurrency($this->session->data['currency'])
                ->setClientOrderNumber($this->session->data['order_id'])
                ->setOrderDate(date('c'))
                ->usePaymentPlanPayment($_GET["paySel"])
                ->doRequest();

            // If response accepted redirect to thankyou page
            if ($svea->accepted == 1) {
                $sveaOrderAddress = $this->buildPaymentAddressQuery($svea, $countryCode, $order['comment']);

                if ($this->config->get($this->paymentString . 'svea_partpayment_shipping_billing') == '1') {
                    $sveaOrderAddress = $this->buildShippingAddressQuery($svea, $sveaOrderAddress, $countryCode);
                }

                $this->model_extension_payment_svea_invoice->updateAddressField($this->session->data['order_id'], $sveaOrderAddress);

                // If Auto deliver order is set, DeliverOrder
                if ($this->config->get($this->paymentString . 'svea_partpayment_auto_deliver') === '1') {
                    $deliverObj = \Svea\WebPay\WebPay::deliverOrder($conf);
                    //Product rows
                    try {
                        $deliverObj = $deliverObj->setCountryCode($countryCode)
                            ->setOrderId($svea->sveaOrderId)
                            ->deliverPaymentPlanOrder()
                            ->doRequest();

                        // If DeliverOrder returns true, send true to view
                        if ($deliverObj->accepted == 1) {
                            $response = array("success" => true);

                            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'), 'Svea order id: '. $svea->sveaOrderId, true);
                            $completeStatus = $this->config->get('config_complete_status');
                            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $completeStatus[0], 'Svea: Order was delivered. Svea contractNumber '.$deliverObj->contractNumber, true);
                        } else {
                            $response = array("error" => $this->responseCodes($deliverObj->resultcode, $deliverObj->errormessage));
                        }
                        //if auto deliver not set, send true to view
                    } catch (Exception $e) {
                        $this->log->write($e->getMessage());
                        $response = array("error" => $this->responseCodes(0, $e->getMessage()));

                        $this->response->addHeader('Content-Type: application/json');
                        $this->response->setOutput(json_encode($response));
                    }
                } else {
                    $response = array("success" => true);

                    // Update order status for created
                    $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'), 'Svea order id: '. $svea->sveaOrderId, true);
                }
            } else {
                $response = array("error" => $this->responseCodes($svea->resultcode, $svea->errormessage));
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $response = array("error" => $this->responseCodes(0, $e->getMessage()));

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        }
    }

    private function getAddress($ssn)
    {
        $this->setVersionStrings();

        $this->load->model('extension/payment/svea_partpayment');
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];

        //Testmode
        $conf = $this->config->get($this->paymentString . 'svea_partpayment_testmode_' . $countryCode) == "1" ? new OpencartSveaConfigTest($this->config, $this->paymentString . 'svea_partpayment') : new OpencartSveaConfig($this->config, $this->paymentString . 'svea_partpayment');

        $svea = \Svea\WebPay\WebPay::getAddresses($conf)
            ->setOrderTypePaymentPlan()
            ->setCountryCode($countryCode);

        $svea = $svea->setIndividual($ssn);
        $result = array();

        try {
            $svea = $svea->doRequest();
        } catch (Exception $e) {
            $this->log->write($e->getMessage());
            $result = array("error" => $this->responseCodes(0, $e->getMessage()));
        }

        if ($svea->accepted != true) {
            $result = $this->handleGetAddressesError($svea);
        } else {
            foreach ($svea->customerIdentity as $ci) {
                $name = ($ci->fullName) ? $ci->fullName : $ci->legalName;

                $result[] = array(
                    "fullName"  => $name,
                    "street"    => $ci->street,
                    "address_2" => $ci->coAddress,
                    "zipCode"   => $ci->zipCode,
                    "locality"  => $ci->locality
                );
            }
        }
        return $result;
    }

    private function handleGetAddressesError($getAddressesResult)
    {
        $this->load->language('extension/payment/svea_partpayment');

        if ($getAddressesResult->resultcode == "NoSuchEntity") {
            return array("error" => $this->language->get('response_error') . $this->language->get('response_nosuchentity'));
        } elseif ($getAddressesResult->errormessage = "Invalid checkdigit") { // We have to match exact message because there are several error with the same resultcode
            return array("error" => $this->language->get('response_error') . $this->language->get('response_checkdigit'));
        } elseif ($getAddressesResult->errormessage = "Must be exactly ten digits") { // We have to match exact message because there are several error with the same resultcode
            return array("error" => $this->language->get('response_error') . $this->language->get('response_invalidlength'));
        } else {
            return array("error" => $getAddressesResult->errormessage);
        }
    }

    /**
     * getPaymentOptions gets the available paymentmethods for this country and the order value and returns campaigns w/monthly cost
     *
     * @return array of array("campaignCode" => same, "description" => same , "price_per_month" => (string) price/month in selected currency)
     */
    private function getPaymentOptions()
    {
        $this->setVersionStrings();

        $this->load->language('extension/payment/svea_partpayment');

        $this->load->model('extension/payment/svea_partpayment');
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];

        $result = array();

        if ($this->config->get($this->paymentString . 'svea_partpayment_testmode_' . $countryCode) !== null) {
            $svea = $this->model_extension_payment_svea_partpayment->getPaymentPlanParams($countryCode);
        } else {
            $result = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));

            return $result;
        }

        if (!isset($svea)) {
            $result = array("error" => 'Svea error: '.$this->language->get('response_27000'));
        } else {
            $currency = $order['currency_code'];

            $this->load->model('localisation/currency');

            $currencies = $this->model_localisation_currency->getCurrencies();
            $decimals = "";

            foreach ($currencies as $key => $val) {
                if ($key == $currency) {
                    if ($key == 'EUR') {
                        $decimals = 2;
                    } else {
                        $decimals = 0;
                    }
                }
            }

            $formattedPrice = round($this->currency->format(($order['total']), $currency, false, false), $decimals);

            try {
                $campaigns = PaymentPlanCalculator::getAllCalculationsFromCampaigns($formattedPrice, $svea->campaignCodes, false, $decimals);

                foreach ($campaigns as $campaign) {
                    foreach ($svea->campaignCodes as $cc) {
                        if ($campaign['campaignCode'] == $cc->campaignCode) {
                            $result[] = array(
                                "campaignCode"               => $campaign['campaignCode'],
                                "description"                => $campaign['description'],
                                "monthlyAmountToPay"         => $campaign['monthlyAmountToPay'] . " " . $currency . "/" . $this->language->get('month'),
                                "paymentPlanType"            => $campaign['paymentPlanType'],
                                "contractLengthInMonths"     => $this->language->get('contractLengthInMonths') . ": " . $campaign['contractLengthInMonths'] . " " . $this->language->get('unit'),
                                "monthlyAnnuityFactor"       => $campaign['monthlyAnnuityFactor'],
                                "initialFee"                 => $this->language->get('initialFee') . ": " . $campaign['initialFee'] . " " . $currency,
                                "notificationFee"            => $this->language->get('notificationFee') . ": " . $campaign['notificationFee'] . " " . $currency,
                                "interestRatePercent"        => $this->language->get('interestRatePercent') . ": " . $campaign['interestRatePercent'] . "%",
                                "numberOfInterestFreeMonths" => $campaign['numberOfInterestFreeMonths'] != 0 ? $this->language->get('numberOfInterestFreeMonths') . ": " . $campaign['numberOfInterestFreeMonths'] . " " . $this->language->get('unit') : 0,
                                "numberOfPaymentFreeMonths"  => $campaign['numberOfPaymentFreeMonths'] != 0 ? $this->language->get('numberOfPaymentFreeMonths') . ": " . $campaign['numberOfPaymentFreeMonths'] . " " . $this->language->get('unit') : 0,
                                "totalAmountToPay"           => $this->language->get('totalAmountToPay') . ": " . $campaign['totalAmountToPay'] . " " . $currency,
                                "effectiveInterestRate"      => $this->language->get('effectiveInterestRate') . ": " . $campaign['effectiveInterestRate'] . "%"
                            );
                            break;
                        }
                    }
                }
            } catch (Exception $exception) {
                $this->log->write('Svea: Unable to fetch calculations for campaigns. Exception: ' . $exception->getMessage());
            }
        }
        return $result;
    }

    public function getAddressAndPaymentOptions()
    {
        $this->setVersionStrings();

        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        $paymentOptions = $this->getPaymentOptions();

        if ($countryCode == "SE" || $countryCode == "DK") {
            $addresses = $this->getAddress($this->request->post['ssn']);
        } elseif ($countryCode != "SE" && $countryCode != "NO" && $countryCode != "DK" && $countryCode != "FI" && $countryCode != "NL" && $countryCode != "DE") {
            $addresses = array("error" => $this->responseCodes(40001, "The country is not supported for this paymentmethod"));
        } else {
            $addresses = array();
        }

        $result = array("addresses" => $addresses, "paymentOptions" => $paymentOptions);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    private function ShowErrorMessage($response = null)
    {
        $message = ($response !== null && isset($response->ErrorMessage)) ? $response->ErrorMessage : "Could not get any partpayment alternatives.";
        echo '$("#svea_partpayment_div").hide();
              $("#svea_partpayment_alt").hide();
              $("#svea_partpayment_err").show();
              $("#svea_partpayment_err").append("' . $message . '");
              $("a#checkout").hide();';
    }

    //update order billingaddress
    private function buildPaymentAddressQuery($svea, $countryCode, $order_comment)
    {
        $this->setVersionStrings();
        $countryId = $this->model_extension_payment_svea_invoice->getCountryIdFromCountryCode(strtoupper($countryCode));
        $paymentAddress = array();

        if (isset($svea->customerIdentity->firstName)) {
            $paymentAddress["payment_firstname"] = $svea->customerIdentity->firstName;
            $paymentAddress["firstName"] = $svea->customerIdentity->firstName;
        }

        if (isset($svea->customerIdentity->lastName)) {
            $paymentAddress["payment_lastname"] = $svea->customerIdentity->lastName;
            $paymentAddress["lastName"] = $svea->customerIdentity->lastName;
        }

        // For private individuals, if firstName, lastName is not set in GetAddresses response, we put the entire getAddress LegalName in lastName
        if (isset($svea->customerIdentity->fullName)) {
            $fullName = $svea->customerIdentity->fullName;
            $fullName = str_replace(",", "", $fullName);
            $fullName = explode(" ", $fullName, 2);

            $paymentAddress["payment_firstname"] = isset($fullName[1]) ? $fullName[1] : "";
            $paymentAddress["payment_lastname"] = isset($fullName[0]) ? $fullName[0] : "";
            $paymentAddress["firstName"] = isset($fullName[1]) ? $fullName[1] : "";
            $paymentAddress["lastName"] = isset($fullName[0]) ? $fullName[0] : "";
        }

        if (isset($svea->customerIdentity->street)) {
            $paymentAddress["payment_address_1"] = $svea->customerIdentity->street;
        }

        if (isset($svea->customerIdentity->coAddress)) {
            $paymentAddress["payment_address_2"] = $svea->customerIdentity->coAddress;
        }

        if (isset($svea->customerIdentity->locality)) {
            $paymentAddress["payment_city"] = $svea->customerIdentity->locality;
        }

        if (isset($svea->customerIdentity->zipCode)) {
            $paymentAddress["payment_postcode"] = $svea->customerIdentity->zipCode;
        }

        $paymentAddress["payment_country_id"] = $countryId['country_id'];
        $paymentAddress["payment_country"] = $countryId['country_name'];
        $paymentAddress["payment_method"] = $this->language->get('text_title');

        return $paymentAddress;
    }

    private function buildShippingAddressQuery($svea, $shippingAddress, $countryCode)
    {
        $this->setVersionStrings();

        $countryId = $this->model_extension_payment_svea_invoice->getCountryIdFromCountryCode(strtoupper($countryCode));

        if (isset($svea->customerIdentity->firstName)) {
            $shippingAddress["shipping_firstname"] = $svea->customerIdentity->firstName;
        }

        if (isset($svea->customerIdentity->lastName)) {
            $shippingAddress["shipping_lastname"] = $svea->customerIdentity->lastName;
        }

        if (isset($svea->customerIdentity->fullName)) {
            $fullName = $svea->customerIdentity->fullName;
            $fullName = str_replace(",", "", $fullName);
            $fullName = explode(" ", $fullName, 2);

            $shippingAddress["shipping_firstname"] = isset($fullName[1]) ? $fullName[1] : "";
            $shippingAddress["shipping_lastname"] = isset($fullName[0]) ? $fullName[0] : "";
        }

        if (isset($svea->customerIdentity->street)) {
            $shippingAddress["shipping_address_1"] = $svea->customerIdentity->street;
        }

        if (isset($svea->customerIdentity->coAddress)) {
            $shippingAddress["shipping_address_2"] = $svea->customerIdentity->coAddress;
        }

        if (isset($svea->customerIdentity->locality)) {
            $shippingAddress["shipping_city"] = $svea->customerIdentity->locality;
        }

        if (isset($svea->customerIdentity->zipCode)) {
            $shippingAddress["shipping_postcode"] = $svea->customerIdentity->zipCode;
        }

        $shippingAddress["shipping_country_id"] = $countryId['country_id'];
        $shippingAddress["shipping_country"] = $countryId['country_name'];

        return $shippingAddress;
    }
}
