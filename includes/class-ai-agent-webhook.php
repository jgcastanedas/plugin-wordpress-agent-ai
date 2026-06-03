<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Webhook {
    private $namespace = 'ai-agent/v1';
    private $rest_base = 'webhook';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature'),
        ));

        register_rest_route($this->namespace, '/webhook/test', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_webhook'),
            'permission_callback' => array($this, 'verify_admin_or_nonce'),
        ));
    }

    public function verify_admin_or_nonce($request) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $nonce = $request->get_header('x-wp-nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return current_user_can('manage_options');
        }

        return new WP_Error(
            'rest_forbidden',
            __('Solo administradores pueden probar el webhook.', 'ai-agent-chatbot'),
            array('status' => 403)
        );
    }

    public function verify_webhook_signature($request) {
        $secret = AI_Agent_Settings::get_webhook_secret();
        if (empty($secret)) {
            return new WP_Error(
                'forbidden',
                __('Webhook no configurado: falta el secret.', 'ai-agent-chatbot'),
                array('status' => 503)
            );
        }

        $body = $request->get_body();

        $twilio_sig = $request->get_header('x_twilio_signature');
        if (!empty($twilio_sig)) {
            $auth_token = get_option('ai_agent_twilio_auth_token', $secret);
            $url = home_url(add_query_arg(null, null));
            if (self::verify_twilio_signature($body, $twilio_sig, $auth_token, $url)) {
                return true;
            }
            return new WP_Error('forbidden', 'Firma Twilio inválida', array('status' => 403));
        }

        $meta_sig = $request->get_header('x_hub_signature_256');
        if (!empty($meta_sig)) {
            $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
            if (hash_equals($expected, $meta_sig)) {
                return true;
            }
            return new WP_Error('forbidden', 'Firma Meta inválida', array('status' => 403));
        }

        $generic_sig = $request->get_header('x_webhook_signature');
        if (empty($generic_sig)) {
            return new WP_Error('forbidden', 'Falta firma del webhook', array('status' => 403));
        }

        $expected = hash_hmac('sha256', $body, $secret);
        if (!hash_equals($expected, $generic_sig)) {
            return new WP_Error('forbidden', 'Firma de webhook inválida', array('status' => 403));
        }

        return true;
    }

    public function handle_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $source = $this->detect_source($request);

        switch ($source) {
            case 'twilio':
                $result = $this->handle_twilio_webhook($body);
                break;
            case 'meta':
                $result = $this->handle_meta_webhook($body);
                break;
            default:
                $result = $this->handle_generic_webhook($body);
                break;
        }

        $response = new WP_REST_Response($result, 200);
        if (empty($result['success'])) {
            $response->set_status(400);
        }

        return $response;
    }

    private function detect_source(WP_REST_Request $request) {
        $headers = $request->get_headers();

        if (isset($headers['x_twilio_signature']) || isset($headers['x-twilio-signature'])) {
            return 'twilio';
        }

        if (isset($headers['x_hub_signature']) || isset($headers['x-hub-signature']) || isset($headers['x_hub_signature_256'])) {
            return 'meta';
        }

        $body = $request->get_json_params();
        if (isset($body['From']) && isset($body['Body'])) {
            return 'twilio';
        }
        if (isset($body['entry'])) {
            return 'meta';
        }

        return 'generic';
    }

    private function handle_twilio_webhook($body) {
        $from = isset($body['From']) ? sanitize_text_field($body['From']) : '';
        $message = isset($body['Body']) ? sanitize_textarea_field($body['Body']) : '';
        $to = isset($body['To']) ? sanitize_text_field($body['To']) : '';

        if (empty($message)) {
            return array(
                'source' => 'twilio',
                'success' => false,
                'response' => 'No se recibió mensaje',
            );
        }

        $llm_response = $this->process_message($message, $from);

        $woocommerce = ai_agent_woocommerce();
        $llm_response = $woocommerce->replace_payment_links($llm_response);

        return array(
            'source' => 'twilio',
            'success' => true,
            'response' => $llm_response,
            'twilio_data' => array(
                'from' => $from,
                'to' => $to,
                'message' => $message,
            ),
        );
    }

    private function handle_meta_webhook($body) {
        if (isset($body['entry'][0]['changes'][0]['value'])) {
            $value = $body['entry'][0]['changes'][0]['value'];

            if (isset($value['messages'])) {
                $message_data = $value['messages'][0];
                $from = sanitize_text_field($message_data['from'] ?? '');
                $message_text = sanitize_textarea_field($message_data['text']['body'] ?? '');

                if (empty($message_text)) {
                    return array(
                        'source' => 'meta',
                        'success' => false,
                        'response' => 'Mensaje vacío',
                    );
                }

                $llm_response = $this->process_message($message_text, $from);

                $woocommerce = ai_agent_woocommerce();
                $llm_response = $woocommerce->replace_payment_links($llm_response);

                return array(
                    'source' => 'meta',
                    'success' => true,
                    'response' => $llm_response,
                    'meta_data' => array(
                        'from' => $from,
                        'message' => $message_text,
                    ),
                );
            }
        }

        return array(
            'source' => 'meta',
            'success' => false,
            'response' => 'Formato de mensaje no soportado',
        );
    }

    private function handle_generic_webhook($body) {
        $message = '';

        if (is_array($body)) {
            if (isset($body['message']) && is_string($body['message'])) {
                $message = $body['message'];
            } elseif (isset($body['text']) && is_string($body['text'])) {
                $message = $body['text'];
            } elseif (isset($body['content']) && is_string($body['content'])) {
                $message = $body['content'];
            }
        }

        $message = sanitize_textarea_field($message);

        if (empty($message)) {
            return array(
                'source' => 'generic',
                'success' => false,
                'response' => 'No se recibió mensaje válido',
            );
        }

        $llm_response = $this->process_message($message);

        $woocommerce = ai_agent_woocommerce();
        $llm_response = $woocommerce->replace_payment_links($llm_response);

        return array(
            'source' => 'generic',
            'success' => true,
            'response' => $llm_response,
        );
    }

    private function process_message($message, $phone = null) {
        $agent = new AI_Agent_Agent();
        $result = $agent->handle_message(
            array('text' => $message, 'source' => $phone ? 'webhook' : 'generic'),
            null,
            $phone
        );

        if (empty($result['success'])) {
            return $result['message'] ?? 'Lo siento, ocurrió un error al procesar tu mensaje.';
        }

        return $result['message'];
    }

    public function test_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $test_message = isset($body['message']) ? sanitize_textarea_field($body['message']) : 'Hola, esto es un mensaje de prueba';

        $response = $this->process_message($test_message);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $test_message,
            'response' => $response,
        ), 200);
    }

    public static function get_webhook_url() {
        return rest_url('ai-agent/v1/webhook');
    }

    public static function verify_twilio_signature($body, $signature, $auth_token, $url = '') {
        if (empty($url)) {
            $url = home_url(add_query_arg(null, null));
        }

        $data = $url;
        if (is_string($body)) {
            parse_str($body, $parsed);
            ksort($parsed);
            foreach ($parsed as $k => $v) {
                $data .= $k . $v;
            }
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $auth_token, true));
        return hash_equals($expected, $signature);
    }
}
