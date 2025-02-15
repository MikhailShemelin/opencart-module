<?php

class ControllerExtensionSveaWidgetProductPrice extends Controller {
    
    public function index($product_info) {
        
        $this->load->model('localisation/country');
        $this->load->model('extension/payment/svea_partpayment');
        $this->load->language('extension/payment/svea_partpayment');
        $this->load->language('extension/svea/checkout');

        $svea_country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));
        $svea_show_paymentplan = $this->model_extension_payment_svea_partpayment->getProductPriceMode();

        $calculate_price = !empty($product_info['special']) ? $product_info['special'] : $product_info['price'];
        if ($svea_show_paymentplan === '1' && $svea_country['iso_code_2'] != "DE") {
            $currency_decimals = $this->session->data['currency'] == 'EUR' ? 1 : 0;
            $prices = array();

            $symbolRight = $this->currency->getSymbolRight($this->session->data['currency']);
            $symbolLeft = $this->currency->getSymbolLeft($this->session->data['currency']);
            $currency_converted_price = floatval($this->tax->calculate($this->currency->format($calculate_price, $this->session->data['currency'], false, false), $product_info['tax_class_id'], $this->config->get('config_tax')));

            if ($svea_show_paymentplan === '1' && $svea_country['iso_code_2'] != "NL") {


                $q = "SELECT `campaignCode`,`description`,`paymentPlanType`,`contractLengthInMonths`,
                                            `monthlyAnnuityFactor`,`initialFee`, `notificationFee`,`interestRatePercent`,
                                            `numberOfInterestFreeMonths`,`numberOfPaymentFreeMonths`,`fromAmount`,`toAmount`
                                            FROM `" . DB_PREFIX . "svea_wp_campaigns`
                                            WHERE `timestamp`=(SELECT MAX(timestamp) FROM `" . DB_PREFIX . "svea_wp_campaigns` WHERE `countryCode` = '" . $svea_country['iso_code_2'] . "')
                                            AND `countryCode` = '" . $svea_country['iso_code_2'] . "'
                                            ORDER BY `monthlyAnnuityFactor` ASC";

                $query = $this->db->query($q);
                $campaigns = $query->rows;
                $priceList = $this->sveaPaymentPlanParamsHelper($currency_converted_price, $campaigns);
                $lowestCampaign = array();

                if (sizeof($priceList)) {//&& admin settings for product display is set to yes
                    foreach ($priceList as $value) {
                        if(empty($lowestCampaign) || $value['pricePerMonth'] < $lowestCampaign['pricePerMonth'] && $value['paymentPlanType'] == 0)
                        {
                            $lowestCampaign = $value;
                        }
                    }
                    return '<p class="wp-product-widget">
<svg style="vertical-align:middle;fill: #002c50;" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="94" height="35" version="1.1" viewBox="0 0 2540 930" xmlns:xlink="http://www.w3.org/1999/xlink">
 <g>
  <path d="M403 256l-172 0c-62,0 -70,-31 -70,-55 0,-49 25,-69 88,-69l334 0 0 -135 -353 0c-157,0 -230,64 -230,202 0,130 69,190 219,190l154 0c60,0 80,14 80,59 0,37 -14,57 -89,57l-338 0 0 135 359 0c156,0 229,-63 229,-198 0,-133 -61,-187 -210,-187z"></path>
  <polygon points="1137,-3 955,438 777,-3 602,-3 883,641 1034,641 1303,-3 "></polygon>
  <path d="M1572 129l226 0 0 -133 -229 0c-207,0 -304,106 -304,333 0,117 33,200 100,254 66,53 130,57 201,57l232 0 0 -133 -226 0c-94,0 -131,-35 -135,-127l361 0 0 -133 -360 0c8,-81 51,-119 133,-119z"></path>
  <path d="M2097 358l73 -191 76 191 -149 0zm1 -361l-273 644 165 0 55 -148 252 0 57 148 172 0 -275 -644 -154 0z"></path>
  <path id="streck-underline" style="fill: #00aece;" d="M2496 931l-2445 0c-17,0 -31,-14 -31,-31l0 -106c0,-17 14,-31 31,-31l2445 0c17,0 31,14 31,31l0 106c0,17 -14,31 -31,31z"></path>
 </g>
</svg><span style="padding-left:5px;font-size:15px">' . $this->language->get('widget_pay_monthly') . ' ' . $symbolLeft . ceil($lowestCampaign['pricePerMonth']) . $symbolRight . "/" . $this->language->get('widget_month'). "</span>";
                    }
            }
        }
        
    }
    
    
    
    protected function sveaPaymentPlanParamsHelper($price, $params) {
        $values = array();
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if ($price >= $value['fromAmount'] && $price <= $value['toAmount']) {
                    $pair = array();
                    $pair['pricePerMonth'] = ($value['initialFee'] + (ceil($price * $value['monthlyAnnuityFactor']) + $value['notificationFee']) * $value['contractLengthInMonths']) / $value['contractLengthInMonths'];
    
                    foreach ($value as $key => $val) {
                        if ($key == 'campaignCode') {
                            $pair[$key] = $val;
                        }
    
                    if($key == 'description'){
                        $pair[$key] = $val;
                    }
    
                    if($key == 'paymentPlanType')
                        $pair[$key] = $val;
                    }
                    array_push($values, $pair);
                }

            }
        }
        return $values;
    }
    
    
    
}