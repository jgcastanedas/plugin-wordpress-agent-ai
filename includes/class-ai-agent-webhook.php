<?php

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
            'permission_callback' => '__return_true',
        ));
    }

    public function verify_webhook_signature($request) {
        $secret = AI_Agent_Settings::get_webhook_secret();
        if (empty($secret)) {
            return true;
        }

        $signature = $request->get_header('x-webhook-signature');
        if (empty($signature)) {
            $signature = $request->get_header('X-Webhook-Signature');
        }

        if (empty($signature)) {
            $body = $request->get_body();
            $expected_sig = hash_hmac('sha256', $body, $secret);
            $sig_from_body = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) : '';

            if (empty($sig_from_body) || !hash_equals($expected_sig, $sig_from_body)) {
                return new WP_Error(
                    'forbidden',
                    'Firma de webhook inválida',
                    array('status' => 403)
                );
            }
        }

        return true;
    }

    public function handle_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $source = $this->detect_source($request);

        $result = array(
            'source' => $source,
            'success' => false,
            'response' => '',
        );

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
        if (!$result['success']) {
            $response->set_status(400);
        }

        return $response;
    }

    private function detect_source(WP_REST_Request $request) {
        $headers = $request->get_headers();

        if (isset($headers['x_twilio_signature']) || isset($headers['x-twilio-signature'])) {
            return 'twilio';
        }

        if (isset($headers['x_hub_signature']) || isset($headers['x-hub-signature'])) {
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

        $llm_response = $this->process_message($message);

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
                $from = sanitize_text_field($message_data['from']);
                $message_text = sanitize_textarea_field($message_data['text']['body']);

                $llm_response = $this->process_message($message_text);

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
            if (isset($body['message'])) {
                $message = is_array($body['message']) ? json_encode($body['message']) : $body['message'];
            } elseif (isset($body['text'])) {
                $message = is_array($body['text']) ? json_encode($body['text']) : $body['text'];
            } elseif (isset($body['content'])) {
                $message = is_array($body['content']) ? json_encode($body['content']) : $body['content'];
            } else {
                $message = json_encode($body);
            }
        } else {
            $message = sanitize_textarea_field($body);
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

    private function process_message($message) {
        $knowledge_base = new AI_Agent_Knowledge_Base();
        $llm = new AI_Agent_LLM_Provider();

        $relevant_context = $knowledge_base->find_relevant_context($message, 5);
        $context_text = $knowledge_base->format_context_for_llm($relevant_context);

        $messages = array(
            array('role' => 'user', 'content' => $message)
        );

        $response = $llm->chat($messages, $context_text);

        if (is_wp_error($response)) {
            return 'Lo siento, ocurrió un error al procesar tu mensaje. Por favor intenta de nuevo.';
        }

        return $response;
    }

    public function test_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $test_message = isset($body['message']) ? sanitize_textarea_field($body['message']) : 'Hola, esto es un mensaje de prueba';

        $response = $this->process_message($test_message);

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $test_message,
            'response' => $response,
        ), 200);
    }

    public static function get_webhook_url() {
        return rest_url('ai-agent/v1/webhook');
    }

    public static function verify_twilio_signature($body, $signature, $auth_token) {
        $expected = base64_encode(hash_hmac('sha1', $body, $auth_token, true));
        return hash_equals($expected, $signature);
    }
}