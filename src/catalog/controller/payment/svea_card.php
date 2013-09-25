<?php
class ControllerPaymentsveacard extends Controller {
    protected function index() {

        //set template

    	$this->data['button_confirm'] = $this->language->get('button_confirm');
        $this->data['button_back'] = $this->language->get('button_back');

        if ($this->request->get['route'] != 'checkout/guest_step_3') {
            $this->data['back'] = 'index.php?route=checkout/payment';
        } else {
            $this->data['back'] = 'index.php?rout=checkout/guest_step_2';
        }
        //Get the country
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->data['countryCode'] = $order_info['payment_iso_code_2'];
        $this->id = 'payment';

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/svea_card.tpl')) {
            $this->template = $this->config->get('config_template') . '/template/payment/svea_card.tpl';
        } else {
            $this->template = 'default/template/payment/svea_card.tpl';
        }

        $this->data['logo'] = "<img src='admin/view/image/payment/".$this->getLogo($order_info['payment_iso_code_2'])."/svea_card.png'>";
        $this->data['continue'] = 'index.php?route=payment/svea_card/redirectSvea';


        $this->load->model('checkout/coupon');
        $this->load->model('checkout/order');
        $this->load->model('payment/svea_card');
        $this->load->model('localisation/currency');
        $this->load->language('payment/svea_card');
        include(DIR_APPLICATION.'../svea/Includes.php');

        //Testmode
        $conf = ($this->config->get('svea_card_testmode') == 1) ? (new OpencartSveaConfigTest($this->config)) : new OpencartSveaConfig($this->config);

        $svea = WebPay::createOrder($conf);
          //Get order information
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
       //Product rows
        $products = $this->cart->getProducts();
        foreach($products as $product){
           $productPriceExVat  = $this->currency->format($product['price'],'',false,false);
            //Get the tax, difference in version 1.4.x
            if(floatval(VERSION) >= 1.5){
                $productTax = $this->currency->format($this->tax->getTax($product['price'], $product['tax_class_id']),'',false,false);
                 $productPriceIncVat = $productPriceExVat + $productTax;
            }  else {
                $taxRate = $this->tax->getRate($product['tax_class_id']);
                $productPriceIncVat = (($taxRate / 100) +1) * $productPriceExVat;
            }
            $svea = $svea
                    ->addOrderRow(Item::orderRow()
                        ->setQuantity($product['quantity'])
                        ->setAmountExVat($productPriceExVat)
                        ->setAmountIncVat($productPriceIncVat)
                        ->setName($product['name'])
                        ->setUnit($this->language->get('unit'))
                        ->setArticleNumber($product['product_id'])
                        ->setDescription($product['model'])
                    );
        }

        //Shipping Fee
        if ( $this->cart->hasShipping() == 1){
            $shipping_info = $this->session->data['shipping_method'];
            $shippingExVat = $this->currency->format($shipping_info["cost"],'',false,false);

            if (floatval(VERSION) >= 1.5){
                $shippingTax = $this->tax->getTax($shippingExVat, $shipping_info["tax_class_id"]);
                $shippingIncVat = $shippingExVat + $shippingTax;
            }else{
                $taxRate = $this->tax->getRate($shipping_info['tax_class_id']);
                $shippingIncVat = (($taxRate / 100) +1) * $shippingExVat;
            }
            if ($shipping_info['cost'] > 0){
                $svea = $svea
                        ->addFee(Item::shippingFee()
                            ->setAmountExVat($shippingExVat)
                            ->setAmountIncVat( $shippingExVat + $shippingTax)
                            ->setName($shipping_info['title'])
                            ->setDescription($shipping_info['text'])
                            ->setUnit($this->language->get('unit'))
                            );
            }

        }

        //Add coupon
        if (isset($this->session->data['coupon'])){
        $coupon = $this->model_checkout_coupon->getCoupon($this->session->data['coupon']);

        $totalPrice = $this->cart->getTotal();

        if ($coupon['type'] == 'F') {
            $discount = $this->currency->format($coupon['discount'],'',false,false);
            $svea = $svea
                    ->addDiscount(
                        Item::fixedDiscount()
                            ->setAmountIncVat($discount)
                            ->setName($coupon['name'])
                            ->setUnit($this->language->get('unit'))
                        );
        } elseif ($coupon['type'] == 'P') {
            $svea = $svea
                    ->addDiscount(
                        Item::relativeDiscount()
                            ->setDiscountPercent($coupon['discount'])
                            ->setName($coupon['name'])
                            ->setUnit($this->language->get('unit'))
                        );
                }
        }

        //Get vouchers
        if (isset($this->session->data['voucher'])) {
            $voucher = $this->model_checkout_voucher->getVoucher($this->session->data['voucher']);

            $totalPrice = $this->cart->getTotal();

            $voucherAmount =  $this->currency->format($voucher['amount'],'',false,false);

            $svea = $svea
                    ->addDiscount(
                        Item::fixedDiscount()
                            ->setAmountIncVat($voucherAmount)
                            ->setName($voucher['code'])
                            ->setDescription($voucher["message"])
                            ->setUnit($this->language->get('unit'))
                        );

        }
        
        $server_url = $this->config->get('config_url');

        $form = $svea
            ->setCountryCode($order['payment_iso_code_2'])
            ->setCurrency($this->session->data['currency'])
            ->setClientOrderNumber($this->session->data['order_id'])
            ->setOrderDate(date('c'));

        $form =  $form->usePaymentMethod(PaymentMethod::KORTCERT)
           ->setCancelUrl($server_url.'index.php?route=payment/svea_card/responseSvea')
           ->setReturnUrl($server_url.'index.php?route=payment/svea_card/responseSvea')
           ->getPaymentForm();


        //print form with hidden buttons
        $fields = $form->htmlFormFieldsAsArray;
        $this->data['form_start_tag'] = $fields['form_start_tag'];
        $this->data['merchant_id'] = $fields['input_merchantId'];
        $this->data['input_message'] = $fields['input_message'];
        $this->data['input_mac'] = $fields['input_mac'];
        $this->data['input_submit'] = $fields['input_submit'];
        $this->data['form_end_tag'] = $fields['form_end_tag'];
        $this->data['submitMessage'] = $this->language->get('button_confirm');

        $this->render();
    }

    public function responseSvea(){
        $this->load->model('checkout/order');
        $this->load->model('payment/svea_card');
        $this->load->language('payment/svea_card');
        include(DIR_APPLICATION.'../svea/Includes.php');

        //Get the country
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order_info['payment_iso_code_2'];

         //Testmode
        $conf = ($this->config->get('svea_card_testmode') == 1) ? (new OpencartSveaConfigTest($this->config)) : new OpencartSveaConfig($this->config);

        $resp = new SveaResponse($_REQUEST, $countryCode, $conf); //HostedPaymentResponse

        $this->session->data['order_id'] = $resp->response->clientOrderNumber;

        if($resp->response->resultcode != '0'){
            if ($resp->response->accepted == '1'){
                $this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('svea_card_order_status_id'));

                header("Location: index.php?route=checkout/success");
                flush();
            }else{
                $this->session->data['error_warning'] = $this->responseCodes($resp->response->resultcode, $resp->response->errormessage);
                $this->renderFailure($resp->response);
            }
        }else{
            $this->renderFailure($resp->response);
        }

    }

    private function renderFailure($rejection){
        $this->data['continue'] = 'index.php?route=checkout/cart';
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/svea_hostedg_failure.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/svea_hostedg_failure.tpl';
		} else {
			$this->template = 'default/template/payment/svea_hostedg_failure.tpl';
		}


		$this->children = array(
			'common/column_right',
			'common/footer',
			'common/column_left',
			'common/header'
		);

        $this->data['text_message'] = "<br />".  $this->responseCodes($rejection->resultcode, $rejection->errormessage)."<br /><br /><br />";
        $this->data['heading_title'] = $this->language->get('error_heading');
        $this->data['footer'] = "";

        $this->data['button_continue'] = $this->language->get('button_continue');
		$this->data['button_back'] = $this->language->get('button_back');

        $this->data['continue'] = 'index.php?route=checkout/cart';
        $this->response->setOutput($this->render(TRUE), $this->config->get('config_compression'));
    }

     private function responseCodes($err,$msg = "") {
        $err = (phpversion()>= 5.3) ? $err = strstr($err, "(", TRUE) : $err = mb_strstr($err, "(", TRUE);

        $this->load->language('payment/svea_card');

        $definition = $this->language->get("response_$err");

        if (preg_match("/^response/", $definition))
             $definition = $this->language->get("response_error"). " $msg";

        return $definition;
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