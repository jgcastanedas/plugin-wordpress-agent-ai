<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capa de "tools" sobre WooCommerce para datos transaccionales/en-vivo.
 * Usa funciones internas de WC (sin REST API) para evitar latencia y auth extra.
 *
 * Decisiones de seguridad documentadas:
 *
 * - get_order_status: por defecto, si la opción `ai_agent_require_email_for_order`
 *   está activa, exige email coincidente con el del pedido. Si no, devuelve solo
 *   info "segura" (estado + fecha + total) sin datos personales del cliente.
 *   NUNCA expone direcciones, email o notas del cliente sin verificación.
 *
 * - create_customer: crea WC_Customer con WP user asociado. Genera password aleatoria;
 *   envía email de bienvenida con reset link (no devuelve la password en chat).
 */
class AI_Agent_WC_Tools {

    public function is_active() {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }

    /**
     * Estado de pedido. $verifier_email es opcional pero recomendado.
     *
     * @return array { success, message, data? }
     */
    public function get_order_status($order_id, $verifier_email = null, $context = array()) {
        if (!$this->is_active()) {
            return $this->err('WooCommerce no está activo.');
        }

        $order_id = absint($order_id);
        if ($order_id <= 0) {
            return $this->err('Número de pedido inválido.');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->err('No encontramos ese pedido.');
        }

        $require_email = get_option('ai_agent_require_email_for_order', 'no') === 'yes';
        $verified = false;

        if (!empty($context['user_id']) && (int) $order->get_customer_id() === (int) $context['user_id']) {
            $verified = true;
        }

        if (!$verified && !empty($context['phone'])) {
            if (strcasecmp(trim($context['phone']), trim($order->get_billing_phone())) === 0) {
                $verified = true;
            }
        }

        if (!$verified && !empty($verifier_email)) {
            if (strcasecmp(trim($verifier_email), trim($order->get_billing_email())) === 0) {
                $verified = true;
            } else {
                return $this->err('El email no coincide con el del pedido.');
            }
        }

        if ($require_email && !$verified) {
            return $this->err('Para ver el estado del pedido necesito que confirmes el email con el que se hizo.');
        }

        $status = wc_get_order_status_name($order->get_status());
        $date = $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : '';
        $total = wc_price($order->get_total());

        $data = array(
            'order_id' => $order_id,
            'status'   => $status,
            'date'     => $date,
            'total'    => wp_strip_all_tags($total),
        );

        if ($verified) {
            $items = array();
            foreach ($order->get_items() as $item) {
                $items[] = $item->get_name() . ' x' . $item->get_quantity();
            }
            $data['items'] = $items;

            $tracking = $order->get_meta('_tracking_number');
            if (!empty($tracking)) {
                $data['tracking'] = $tracking;
            }

            $shipping = trim($order->get_formatted_shipping_address());
            if (!empty($shipping)) {
                $data['shipping_address'] = $shipping;
            }
        }

        $msg = sprintf(
            "Pedido #%d — Estado: *%s* — Fecha: %s — Total: %s",
            $order_id, $status, $date, wp_strip_all_tags($total)
        );

        if ($verified && !empty($data['items'])) {
            $msg .= "\nProductos: " . implode(', ', $data['items']);
        }
        if (!empty($data['tracking'])) {
            $msg .= "\nTracking: " . $data['tracking'];
        }

        return array('success' => true, 'message' => $msg, 'data' => $data);
    }

    /**
     * Lista los últimos pedidos por email o teléfono.
     */
    public function get_my_orders($identifier_type, $identifier, $limit = 5) {
        if (!$this->is_active()) {
            return $this->err('WooCommerce no está activo.');
        }

        $limit = max(1, min(20, absint($limit)));
        $args = array(
            'limit'  => $limit,
            'orderby' => 'date',
            'order'  => 'DESC',
        );

        if ($identifier_type === 'email') {
            $email = sanitize_email($identifier);
            if (empty($email)) {
                return $this->err('Email inválido.');
            }
            $args['billing_email'] = $email;
        } elseif ($identifier_type === 'phone') {
            $phone = preg_replace('/[^\d+]/', '', (string) $identifier);
            if (empty($phone)) {
                return $this->err('Teléfono inválido.');
            }
            $args['meta_query'] = array(
                array(
                    'key'   => '_billing_phone',
                    'value' => $phone,
                ),
            );
        } else {
            return $this->err('Tipo de identificador no soportado.');
        }

        $orders = wc_get_orders($args);

        if (empty($orders)) {
            return array('success' => true, 'message' => 'No encontré pedidos asociados.', 'data' => array());
        }

        $list = array();
        $lines = array();
        foreach ($orders as $order) {
            $row = array(
                'id'     => $order->get_id(),
                'status' => wc_get_order_status_name($order->get_status()),
                'date'   => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : '',
                'total'  => wp_strip_all_tags(wc_price($order->get_total())),
            );
            $list[] = $row;
            $lines[] = sprintf("- #%d · %s · %s · %s", $row['id'], $row['status'], $row['date'], $row['total']);
        }

        return array(
            'success' => true,
            'message' => "Tus pedidos recientes:\n" . implode("\n", $lines),
            'data'    => $list,
        );
    }

    /**
     * Crea cliente WooCommerce + WP user.
     * Espera array con keys: email, first_name, last_name, phone,
     *   address_1, address_2, city, postcode, state, country.
     */
    public function create_customer($data) {
        if (!$this->is_active()) {
            return $this->err('WooCommerce no está activo.');
        }

        $email = isset($data['email']) ? sanitize_email($data['email']) : '';
        if (empty($email) || !is_email($email)) {
            return $this->err('Necesito un email válido para crear el cliente.');
        }

        if (email_exists($email)) {
            return $this->err('Ya existe un cliente con ese email. Si eres tú, inicia sesión.');
        }

        $first_name = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
        $last_name  = isset($data['last_name'])  ? sanitize_text_field($data['last_name'])  : '';
        $phone      = isset($data['phone'])      ? sanitize_text_field($data['phone'])      : '';

        if (empty($first_name)) {
            return $this->err('Necesito al menos el nombre del cliente.');
        }

        $username = sanitize_user(current(explode('@', $email)), true);
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $password = wp_generate_password(20, true);

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, $username, $password);
        } else {
            $user_id = wp_create_user($username, $password, $email);
        }

        if (is_wp_error($user_id)) {
            return $this->err($user_id->get_error_message());
        }

        $customer = new WC_Customer($user_id);
        $customer->set_first_name($first_name);
        $customer->set_last_name($last_name);
        $customer->set_billing_first_name($first_name);
        $customer->set_billing_last_name($last_name);
        $customer->set_billing_email($email);
        $customer->set_billing_phone($phone);
        $customer->set_shipping_first_name($first_name);
        $customer->set_shipping_last_name($last_name);

        $address_keys = array('address_1', 'address_2', 'city', 'state', 'postcode', 'country');
        foreach ($address_keys as $k) {
            if (!empty($data[$k])) {
                $val = sanitize_text_field($data[$k]);
                $setter_b = 'set_billing_' . $k;
                $setter_s = 'set_shipping_' . $k;
                if (method_exists($customer, $setter_b)) $customer->$setter_b($val);
                if (method_exists($customer, $setter_s)) $customer->$setter_s($val);
            }
        }

        $customer->save();

        do_action('woocommerce_created_customer', $user_id, array('user_email' => $email, 'user_login' => $username), true);

        return array(
            'success' => true,
            'message' => "✅ Cliente creado. Te enviamos un email a {$email} para que configures tu contraseña.",
            'data'    => array('user_id' => $user_id, 'email' => $email),
        );
    }

    private function err($message) {
        return array('success' => false, 'message' => $message);
    }
}

function ai_agent_wc_tools() {
    static $instance = null;
    if ($instance === null) {
        $instance = new AI_Agent_WC_Tools();
    }
    return $instance;
}
