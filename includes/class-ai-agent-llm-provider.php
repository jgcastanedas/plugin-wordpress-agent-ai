<?php

class AI_Agent_LLM_Provider {
    private $provider;
    private $config;

    public function __construct() {
        $this->config = AI_Agent_Settings::get_llm_config();
        $this->provider = $this->config['provider'];
    }

    public function chat($messages, $context = '') {
        $system_prompt = $this->build_system_prompt($context);

        $full_messages = array_merge(array(
            array('role' => 'system', 'content' => $system_prompt)
        ), $messages);

        switch ($this->provider) {
            case 'openai':
                return $this->chat_with_openai($full_messages);
            case 'anthropic':
                return $this->chat_with_anthropic($full_messages);
            case 'ollama':
                return $this->chat_with_ollama($full_messages);
            default:
                return new WP_Error('invalid_provider', 'Proveedor de LLM no válido');
        }
    }

    private function build_system_prompt($context) {
        $has_woocommerce = class_exists('WooCommerce');

        $prompt = "Eres un asistente de chatbot para un sitio web de WordPress.";

        if (!empty($context)) {
            $prompt .= "\n\nContexto relevante para responder:\n" . $context;
        }

        if ($has_woocommerce) {
            $prompt .= "\n\nEl sitio tiene WooCommerce activo. Cuando el usuario pregunte por productos o precios, incluye la información disponible y el link de pago generado.";
        }

        $prompt .= "\n\nResponde de manera útil, concisa y en el mismo idioma que el usuario. Si no tienes información suficiente, indícalo claramente.";
        $prompt .= "\n\nIMPORTANTE: Cuando menciones un producto y su link de pago, usa el formato: [LINK_PAGO:url] para que el sistema pueda procesar el pago.";

        return $prompt;
    }

    private function chat_with_openai($messages) {
        $api_key = $this->config['openai_key'];
        if (empty($api_key)) {
            return new WP_Error('missing_key', 'OpenAI API key no configurada');
        }

        $model = 'gpt-4o';

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
            )),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return $body['choices'][0]['message']['content'] ?? '';
    }

    private function chat_with_anthropic($messages) {
        $api_key = $this->config['anthropic_key'];
        if (empty($api_key)) {
            return new WP_Error('missing_key', 'Anthropic API key no configurada');
        }

        $system = '';
        $filtered_messages = array();

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $filtered_messages[] = $msg;
            }
        }

        $last_message = end($filtered_messages);
        $user_content = $last_message['content'] ?? '';

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1000,
                'system' => $system,
                'messages' => array(
                    array('role' => 'user', 'content' => $user_content)
                ),
            )),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return $body['content'][0]['text'] ?? '';
    }

    private function chat_with_ollama($messages) {
        $url = $this->config['ollama_url'];
        $model = $this->config['ollama_model'];

        if (empty($url) || empty($model)) {
            return new WP_Error('missing_config', 'Configuración de Ollama incompleta');
        }

        $system_msg = '';
        $filtered_messages = array();

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_msg = $msg['content'];
            } else {
                $filtered_messages[] = $msg;
            }
        }

        $payload = array(
            'model' => $model,
            'messages' => $filtered_messages,
            'stream' => false,
        );

        if (!empty($system_msg)) {
            $payload['system'] = $system_msg;
        }

        $response = wp_remote_post($url . '/api/chat', array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($payload),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']);
        }

        return $body['message']['content'] ?? '';
    }

    public static function test_connection($provider, $config) {
        $instance = new self();
        $instance->provider = $provider;
        $instance->config = $config;

        $test_messages = array(
            array('role' => 'user', 'content' => 'Di "OK" si puedes leerme')
        );

        $result = $instance->chat($test_messages, '');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error' => $result->get_error_message()
            );
        }

        return array(
            'success' => true,
            'response' => $result
        );
    }
}