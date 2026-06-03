<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Public {

    const RATE_LIMIT_WINDOW = 60;
    const RATE_LIMIT_MAX = 15;

    public function __construct() {
        add_action('wp_ajax_ai_agent_chat', array($this, 'handle_chat_ajax'));
        add_action('wp_ajax_nopriv_ai_agent_chat', array($this, 'handle_chat_ajax'));
    }

    public function handle_chat_ajax() {
        check_ajax_referer('ai_agent_nonce', 'nonce');

        if (!$this->check_rate_limit()) {
            wp_send_json_error(array(
                'error' => __('Demasiadas solicitudes. Intenta en un momento.', 'ai-agent-chatbot')
            ), 429);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : null;

        if (empty($message)) {
            wp_send_json_error(array('error' => __('No se recibió mensaje', 'ai-agent-chatbot')));
        }

        if (strlen($message) > 4000) {
            wp_send_json_error(array('error' => __('Mensaje demasiado largo', 'ai-agent-chatbot')));
        }

        $agent = new AI_Agent_Agent();
        $result = $agent->handle_message(
            array('text' => $message, 'source' => 'widget'),
            $session_id,
            null
        );

        if (empty($result['success'])) {
            wp_send_json_error(array(
                'error' => $result['message'] ?? __('Error procesando el mensaje', 'ai-agent-chatbot')
            ));
        }

        $woocommerce = ai_agent_woocommerce();
        $response = $woocommerce->replace_payment_links($result['message']);

        wp_send_json_success(array(
            'response'       => $response,
            'session_id'     => $result['session_id'] ?? null,
            'intent'         => $result['intent'] ?? null,
            'cart_items'     => $result['cart_items'] ?? 0,
            'cart_url'       => $result['cart_url'] ?? null,
        ));
    }

    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $key = 'ai_agent_rl_' . md5($ip);
        $count = (int) get_transient($key);

        $limit = (int) apply_filters('ai_agent_rate_limit_per_minute', self::RATE_LIMIT_MAX);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    private function get_client_ip() {
        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '0.0.0.0';
    }
}

function ai_agent_public_init() {
    return new AI_Agent_Public();
}

add_action('init', 'ai_agent_public_init');
