<?php

class ControllerExtensionSveaCart extends Controller
{

    public function index()
    {
        $products = $this->cart->getProducts();

        $this->load->language('extension/svea/checkout');
        $this->load->model('extension/extension');

        $data['cart'] = $this->url->link('checkout/cart');
        $data['text_change_cart'] = $this->language->get('text_change_cart');
        $data['heading_cart'] = $this->language->get('heading_cart');

        // PRODUCTS
        $data['products'] = array();

        foreach ($products as $product) {

            $product['price'] = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            $product['price'] = $product['price'];

            $data['products'][] = array(
                'product_id' => $product['product_id'],
                'model' => $product['model'],
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'option' => $product['option'],
            );
        }

        // VOUCHERS
        $data['vouchers'] = array();

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $key => $voucher) {

                $voucher['amount'] = $this->currency->format($voucher['amount'], $this->session->data['currency']);
                $voucher['amount'] = $voucher['amount'];

                $data['vouchers'][] = array(
                    'key' => $key,
                    'description' => $this->language->get('item_voucher'),
                    'amount' => $voucher['amount'],
                );
            }
        }

        // TOTALS
        $this->load->model('extension/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $total_data = array(
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total
        );

        $results = $this->model_extension_extension->getExtensions('total');

        $sort_order = array();

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get($result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        $sort_order = array();

        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $totals);

        $data['totals'] = array();

        foreach ($totals as $total) {
            $total['value'] = $this->currency->format($total['value'], $this->session->data['currency']);
            $data['totals'][] = array(
                'title' => $total['title'],
                'text' => $total['value']
            );
        }

        $this->response->setOutput($this->load->view('extension/svea/cart', $data));
    }

    private function removeTrail($price)
    {
        return str_replace($this->language->get('decimal_point') . '00', '', $price);
    }

}
