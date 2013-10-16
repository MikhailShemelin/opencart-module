<?php
class ControllerPaymentsveapartpayment extends Controller {

    protected function index() {
        $this->load->language('payment/svea_partpayment');
        $this->data['button_confirm'] = $this->language->get('button_confirm');
        $this->data['button_back'] = $this->language->get('button_back');

        $this->data['continue'] = 'index.php?route=checkout/success';

        if ($this->request->get['route'] != 'checkout/guest_step_3') {
            $this->data['back'] = 'index.php?route=checkout/payment';
        } else {
            $this->data['back'] = 'index.php?rout=checkout/guest_step_2';
        }

        $this->id = 'payment';

        //Get the country
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->data['countryCode'] = $order_info['payment_iso_code_2'];

        $this->data['logo'] = "<img src='admin/view/image/payment/".$this->getLogo($order_info['payment_iso_code_2'])."/svea_partpayment.png'>";

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/svea_partpayment.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/svea_partpayment.tpl';
        } else {
            $this->template = 'default/template/payment/svea_partpayment.tpl';
            $this->data['partpayment_fail'] = $this->language->get('text_partpayment_fail');
        }
        $this->render();
    }


     private function responseCodes($err,$msg = "") {
        $this->load->language('payment/svea_partpayment');

        $definition = $this->language->get("response_$err");

        if (preg_match("/^response/", $definition))
             $definition = $this->language->get("response_error"). " $msg";

        return $definition;
    }

    public function confirm() {
        $this->load->language('payment/svea_partpayment');

        //Load models
        $this->load->model('checkout/order');
        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/coupon');
        floatval(VERSION) >= 1.5 ? $this->load->model('checkout/voucher') : $this->load->model('checkout/extension');

        //Load SVEA includes
        include(DIR_APPLICATION.'../svea/Includes.php');
         $response = array();
        //Get order information
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        //Testmode
        if($this->config->get('svea_partpayment_testmode_'.$countryCode) !== NULL){
            $conf = $this->config->get('svea_partpayment_testmode_'.$countryCode) == "1" ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);

        } else {
            $response = array("error" => $this->responseCodes(40001,"The country is not supported for this paymentmethod"));
            echo json_encode($response);
            exit();
        }

        $svea = WebPay::createOrder($conf);

        // Get the products in the cart
        $products = $this->cart->getProducts();

        //products
        $svea = $this->formatOrderRows($svea,$products);
        //Shipping
        if ($this->cart->hasShipping() == 1) {
               if($this->session->data['shipping_method']['cost'] > 0){
                    $svea = $this->formatShippingFeeRows($svea);
               }
        }
        //Get coupons
        if (isset($this->session->data['coupon'])) {
            $coupon = $this->model_checkout_coupon->getCoupon($this->session->data['coupon']);
            $svea = $this->formatCouponRows($svea,$coupon);
        }
        //Get vouchers
        if (isset($this->session->data['voucher']) && floatval(VERSION) >= 1.5) {
            $voucher = $this->model_checkout_voucher->getVoucher($this->session->data['voucher']);
            $svea = $this->formatVoucher($svea,$voucher);
       }


        //Seperates the street from the housenumber according to testcases
        $pattern = "/^(?:\s)*([0-9]*[A-ZÄÅÆÖØÜßäåæöøüa-z]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]+)(?:\s*)([0-9]*\s*[A-ZÄÅÆÖØÜßäåæöøüa-z]*[^\s])?(?:\s)*$/";
        preg_match($pattern, $order['payment_address_1'], $addressArr);
        if( !array_key_exists( 2, $addressArr ) ) { $addressArr[2] = ""; } //fix for addresses w/o housenumber

        $ssn = (isset($_GET['ssn'])) ? $_GET['ssn'] : 0;

        $item = Item::individualCustomer();
        $item = $item->setNationalIdNumber($ssn)
                     ->setEmail($order['email'])
                     ->setName($order['payment_firstname'],$order['payment_lastname'])
                     ->setStreetAddress($addressArr[1],$addressArr[2])
                     ->setZipCode($order['payment_postcode'])
                     ->setLocality($order['payment_city'])
                     ->setIpAddress($order['ip'])
                     ->setPhoneNumber($order['telephone']);

        if($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL"){

        $item = $item->setInitials($_GET['initials'])
                     ->setBirthDate($_GET['birthYear'], $_GET['birthMonth'], $_GET['birthDay']);

        }

        $svea = $svea->addCustomerDetails($item);
        try{
            $svea = $svea
                      ->setCountryCode($countryCode)
                      ->setCurrency($this->session->data['currency'])
                      ->setClientOrderNumber($this->session->data['order_id'])
                      ->setOrderDate(date('c'))
                      ->usePaymentPlanPayment($_GET["paySel"])
                        ->doRequest();
        }  catch (Exception $e){
            $this->log->write($e->getMessage());
            $response = array("error" => $this->responseCodes(0,$e->getMessage()));
            echo json_encode($response);
            exit();
        }


        //If response accepted redirect to thankyou page
        if ($svea->accepted == 1) {
                //If Auto deliver order is set, DeliverOrder
                if($this->config->get('svea_partpayment_auto_deliver') == 1){
                    $deliverObj = WebPay::deliverOrder($conf);
                    //Product rows
                    try{
                        $deliverObj = $deliverObj
                                ->setCountryCode($svea->customerIdentity->countryCode)
                                ->setOrderId($svea->sveaOrderId)
                                    ->deliverPaymentPlanOrder()
                                    ->doRequest();
                    }  catch (Exception $e){
                        $this->log->write($e->getMessage());
                        $response = array("error" => $this->responseCodes(0,$e->getMessage()));
                        echo json_encode($response);
                        exit();
                    }

                  //If DeliverOrder returns true, send true to veiw
                    if($deliverObj->accepted == 1){
                       $response = array("success" => true);
                       //update order status for delivered
                       $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('svea_partpayment_auto_deliver_status_id'));
                    //I not, send error codes
                    }  else {
                        $response = array("error" => $this->responseCodes($deliverObj->resultcode,$deliverObj->errormessage));
                    }
                //if auto deliver not set, send true to view
                }  else {
                     $response = array("success" => true);
                    //update order status for created
                    $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('svea_partpayment_order_status_id'));
                }

            //else send errors to view
            }  else {
                $response = array("error" => $this->responseCodes($svea->resultcode,$svea->errormessage));
            }
        echo json_encode($response);
    }



    private function getAddress($ssn){

        include(DIR_APPLICATION.'../svea/Includes.php');

        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        //Testmode
        $conf = $this->config->get('svea_partpayment_testmode_'.$countryCode) == "1" ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);

        $svea = WebPay::getAddresses($conf)
            ->setOrderTypePaymentPlan()
            ->setCountryCode($countryCode);

        $svea = $svea->setIndividual($ssn);
        $result = array();
        try{
            $svea = $svea->doRequest();
        }  catch (Exception $e){
            $this->log->write($e->getMessage());
            $result = array("error" => $this->responseCodes(0,$e->getMessage()));
        }

        if (isset($svea->errormessage)) {
            $result = array("error" => $svea->errormessage);
        }else{
            foreach ($svea->customerIdentity as $ci){

                $name = ($ci->fullName) ? $ci->fullName : $ci->legalName;

                $result = array("fullName"  => $name,
                                  "street"    => $ci->street,
                                  "zipCode"   => $ci->zipCode,
                                  "locality"  => $ci->locality,
                                  "addressSelector" => $ci->addressSelector);
            }
        }
        return $result;
       // echo json_encode($result);
    }


    private function getPaymentOptions(){
        include(DIR_APPLICATION.'../svea/Includes.php');
        $this->load->language('payment/svea_partpayment');
        $this->load->model('payment/svea_partpayment');
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];

        $result = array();
        if($this->config->get('svea_partpayment_testmode_'.$countryCode) !== NULL){
            $sveaConf = ($this->config->get('svea_partpayment_testmode_'.$countryCode) == "1") ? (new OpencartSveaConfigTest($this->config)) : new OpencartSveaConfig($this->config);
        } else {
              $result = array("error" => $this->responseCodes(40001,"The country is not supported for this paymentmethod"));
              return $result;
        }
        $svea = WebPay::getPaymentPlanParams($sveaConf);
        try{
            $svea = $svea->setCountryCode($countryCode)
                    ->doRequest();
        }  catch (Exception $e){
            $this->log->write($e->getMessage());
            $result[] = array("error" => $e->getMessage());
        }


        if (isset($svea->errormessage)) {
            $result[] = array("error" => $svea->errormessage);
        }else{
            $currency = floatval(VERSION) >= 1.5 ? $order['currency_code'] : $order['currency'];
            $this->load->model('localisation/currency');
            $currencies = $this->model_localisation_currency->getCurrencies();
            $decimals = "";
            foreach ($currencies as $key => $val) {
                if($key == $currency){
                    $decimals = $val['decimal_place'];
                }
            }
            $formattedPrice = round($this->currency->format(($order['total']),'',false,false),2);
            $campaigns = WebPay::paymentPlanPricePerMonth($formattedPrice, $svea);
                foreach ($campaigns->values as $cc)
                $result[] = array("campaignCode" => $cc['campaignCode'],
                                  "description"    => $cc['description'],
                                  "price_per_month" => (string)round($cc['pricePerMonth'],$decimals)." ".$currency."/".$this->language->get('month'));

            }


        return $result;
    }


    public function getAddressAndPaymentOptions(){
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        $paymentOptions = $this->getPaymentOptions();

        if ($countryCode == "SE" || $countryCode == "DK" || $countryCode == "NO"){
            $addresses = $this->getAddress($_GET['ssn']);
        }elseif ($countryCode != "SE" && $countryCode != "NO" && $countryCode != "DK" && $countryCode != "FI" && $countryCode != "NL" && $countryCode != "DE") {
            $addresses = array("error" => $this->responseCodes(40001,"The country is not supported for this paymentmethod"));
        }  else {
            $addresses = array();
        }
        $result = array("addresses" => $addresses, "paymentOptions" => $paymentOptions);

        echo json_encode($result);

    }


    private function ShowErrorMessage($response = null) {
        $message = ($response !== null && isset($response->ErrorMessage)) ? $response->ErrorMessage : "Could not get any partpayment alternatives.";
         echo '$("#svea_partpayment_div").hide();
              $("#svea_partpayment_alt").hide();
              $("#svea_partpayment_err").show();
              $("#svea_partpayment_err").append("' . $message . '");
              $("a#checkout").hide();';
    }

        private function formatOrderRows($svea,$products){
        $this->load->language('payment/svea_partpayment');
        //Product rows
        foreach ($products as $product) {
            $productPriceExVat  = $product['price'];

            //Get the tax, difference in version 1.4.x
            if (floatval(VERSION) >= 1.5) {
                $productTax = $this->tax->getTax($product['price'], $product['tax_class_id']);
                $tax = $this->tax->getRates($product['price'], $product['tax_class_id']);
                $taxPercent = 0;
                foreach ($tax as $key => $value) {
                    $taxPercent = $value['rate'];
                }
                //$productPriceIncVat = $productPriceExVat + $productTax;
            } else {
                $taxPercent = $this->tax->getRate($product['tax_class_id']);
                //$productPriceIncVat = (($taxPercent / 100) + 1) * $productPriceExVat;
            }
            $svea = $svea
                    ->addOrderRow(Item::orderRow()
                        ->setQuantity($product['quantity'])
                        ->setAmountExVat(floatval($productPriceExVat))
                        //->setAmountIncVat($productPriceIncVat) //Removed because bug transforming vat from 25 -> 24
                       ->setVatPercent($taxPercent)
                        ->setName($product['name'])
                        ->setUnit($this->language->get('unit'))
                        ->setArticleNumber($product['product_id'])
                        ->setDescription($product['model'])
                    );

        }

        return $svea;
    }

    public function formatShippingFeeRows($svea) {
         $this->load->language('payment/svea_partpayment');
        //Shipping Fee
            $shipping_info = $this->session->data['shipping_method'];
            $shippingExVat = $this->currency->format($shipping_info["cost"],'',false,false);

              if (floatval(VERSION) >= 1.5){
                $shippingTax = $this->tax->getTax($shippingExVat, $shipping_info["tax_class_id"]);
                $shippingIncVat = $shippingExVat + $shippingTax;
            }else{
                $taxRate = $this->tax->getRate($shipping_info['tax_class_id']);
                $shippingIncVat = (($taxRate / 100) +1) * $shippingExVat;
            }

            $svea = $svea
                    ->addFee(
                        Item::shippingFee()
                            ->setAmountExVat(floatval($shippingExVat))
                            ->setAmountIncVat(floatval($shippingIncVat))
                            ->setName($shipping_info["title"])
                            ->setDescription($shipping_info["text"])
                            ->setUnit($this->language->get('pcs'))
                       );


        return $svea;
    }

    private function formatCouponRows($svea, $coupon) {
            if ($coupon['discount'] > 0 && $coupon['type'] == 'F') {
                $discount = $this->currency->format($coupon['discount'],'',false,false);;

                $svea = $svea
                        ->addDiscount(
                            Item::fixedDiscount()
                                ->setAmountIncVat($discount)
                                ->setName($coupon['name'])
                                ->setUnit($this->language->get('pcs'))
                            );


            } elseif ($coupon['discount'] > 0 && $coupon['type'] == 'P') {

                $svea = $svea
                        ->addDiscount(
                            Item::relativeDiscount()
                                ->setDiscountPercent($coupon['discount'])
                                ->setName($coupon['name'])
                                ->setUnit($this->language->get('pcs'))
                            );
                }
            return $svea;
    }

    private function formatVoucher($svea, $voucher) {
         $voucherAmount = $voucher['amount'];
        $svea = $svea
                ->addDiscount(
                    Item::fixedDiscount()
                        ->setVatPercent(0)//No vat on voucher. Concidered a debt.
                        ->setAmountIncVat($voucherAmount)
                        ->setName($voucher['code'])
                        ->setDescription($voucher["message"])
                        ->setUnit($this->language->get('unit'))
                    );
        return $svea;
    }

    private function getLogo($countryCode){

        switch ($countryCode){
            case "SE": $country = "swedish";    break;
            case "NO": $country = "norwegian";  break;
            case "DK": $country = "danish";     break;
            case "FI": $country = "finnish";    break;
            case "NL": $country = "dutch";      break;
            case "DE": $country = "german";     break;
            default:   $country = "english";    break;
        }

        return $country;
    }
}
?>