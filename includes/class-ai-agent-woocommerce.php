<?php

class AI_Agent_WooCommerce {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function is_active() {
        return class_exists('WooCommerce');
    }

    public function get_products($args = array()) {
        if (!$this->is_active()) {
            return array();
        }

        $defaults = array(
            'limit' => -1,
            'status' => 'publish',
        );
        $args = wp_parse_args($args, $defaults);

        return wc_get_products($args);
    }

    public function get_product_data($product_id) {
        if (!$this->is_active()) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'status' => $product->get_status(),
            'sku' => $product->get_sku(),
            'permalink' => get_permalink($product->get_id()),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
            'type' => $product->get_type(),
        );
    }

    public function search_products($query, $limit = 10) {
        if (!$this->is_active()) {
            return array();
        }

        $products = wc_get_products(array(
            's' => $query,
            'limit' => $limit,
            'status' => 'publish',
        ));

        return array_map(function($product) {
            return $this->get_product_data($product->get_id());
        }, $products);
    }

    public function create_checkout_link($product_id, $quantity = 1, $attributes = array()) {
        if (!$this->is_active()) {
            return '';
        }

        $url = wc_get_checkout_url();

        if (!empty($attributes)) {
            $attributes_param = array();
            foreach ($attributes as $key => $value) {
                $attributes_param[] = 'attribute_' . sanitize_title($key) . '=' . urlencode($value);
            }
            $url = add_query_arg($attributes_param, $url);
        }

        return add_query_arg(array(
            'add-to-cart' => $product_id,
            'quantity' => $quantity,
        ), $url);
    }

    public function create_cart_link($product_id, $quantity = 1) {
        if (!$this->is_active()) {
            return '';
        }

        return add_query_arg(array(
            'add-to-cart' => $product_id,
            'quantity' => $quantity,
        ), wc_get_cart_url());
    }

    public function format_product_for_context($product_data) {
        $text = $product_data['name'] . "\n";
        $text .= "Precio: " . $product_data['price'] . "\n";
        $text .= "SKU: " . $product_data['sku'] . "\n";
        $text .= "Descripción: " . $product_data['description'] . "\n";

        if (!empty($product_data['categories'])) {
            $text .= "Categorías: " . implode(', ', $product_data['categories']) . "\n";
        }

        return $text;
    }

    public function get_product_by_sku($sku) {
        if (!$this->is_active() || empty($sku)) {
            return null;
        }

        $product = wc_get_product_by_slug($sku);
        if (!$product) {
            global $wpdb;
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
                $sku
            ));
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
        }

        return $product ? $this->get_product_data($product->get_id()) : null;
    }

    public function parse_payment_link($text) {
        $pattern = '/\[LINK_PAGO:([^\]]+)\]/';
        $matches = array();

        if (preg_match_all($pattern, $text, $matches)) {
            $links = array();
            foreach ($matches[1] as $url) {
                $links[] = esc_url_raw($url);
            }
            return $links;
        }

        return array();
    }

    public function replace_payment_links($text, $replace_text = 'Aquí está el link de pago:') {
        $pattern = '/\[LINK_PAGO:([^\]]+)\]/';

        return preg_replace($pattern, $replace_text . ' $1', $text);
    }
}

function ai_agent_woocommerce() {
    return AI_Agent_WooCommerce::get_instance();
}