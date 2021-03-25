<?php

class ModelExtensionTotalSveaFee extends Model
{
    private $totalString = "total_";

    public function setVersionStrings()
    {
        if (VERSION < 3.0) {
            $this->totalString = "";
        }
    }
    /**
     * getTotal is triggered to get our contribution to the cart order_total and globals total & taxes
     *
     * @param $total
     * @return type
     */
    public function getTotal($total)
    {
        $this->setVersionStrings();

        // svea_fee applicable?
        if (($this->cart->getSubTotal() > 0) && // only checks for lower limit
            isset($this->session->data['payment_method']['code']) &&
            ($this->session->data['payment_method']['code'] == 'svea_invoice')
        ) {
            $this->load->model('localisation/country');

            $country = '';
            $address = '';

            if (isset($this->session->data['payment_address']['address_id'])) {
                $this->load->model('account/address');
                $address = $this->model_account_address->getAddress($this->session->data['payment_address']['address_id']);
            } elseif (isset($this->session->data['payment_address'])) {
                $address = $this->session->data['payment_address'];
            }

            if (is_array($address) && !empty($address)) {
                $country = $address['iso_code_2'];
            } else {
                return;
            }

            // Get svea_fee config settings for country
            $svea_fee_fee          = $this->config->get($this->totalString . 'svea_fee_fee' . "_" . $country);
            $svea_fee_sort_order   = $this->config->get($this->totalString . 'svea_fee_sort_order' . "_" . $country);
            $svea_fee_tax_class_id = $this->config->get($this->totalString . 'svea_fee_tax_class' . "_" . $country);
            $svea_fee_status       = $this->config->get($this->totalString . 'svea_fee_status' . "_" . $country);

            // svea_fee disabled?
            if ($svea_fee_status == false) {
                return;
            }

            $this->load->language('extension/total/svea_fee');

            // Add our svea_fee total to the rest of the totals
            $total['totals'][] = array(
                'code'       => 'svea_fee',
                'title'      => $this->language->get('text_svea_fee') . " (" . $country . ")",
                'text'       => '',
                'value'      => $svea_fee_fee,
                'sort_order' => $svea_fee_sort_order
            );

            // Calculate tax, add tax and fee to globals total, taxes
            if (isset($svea_fee_tax_class_id)) {
                $tax_rates = $this->tax->getRates($svea_fee_fee, $svea_fee_tax_class_id);

                foreach ($tax_rates as $tax_rate) {
                    if (!isset($total['taxes'][$tax_rate['tax_rate_id']])) {
                        $total['taxes'][$tax_rate['tax_rate_id']] = $tax_rate['amount'];
                    } else {
                        $total['taxes'][$tax_rate['tax_rate_id']] += $tax_rate['amount'];
                    }
                }

                $total['total'] += $svea_fee_fee;
            }
        }
    }
}
