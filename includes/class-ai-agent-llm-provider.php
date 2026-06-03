<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_LLM_Provider {

    const DEFAULT_OPENAI_MODEL = 'gpt-4o';
    const DEFAULT_ANTHROPIC_MODEL = 'claude-sonnet-4-6';
    const DEFAULT_OLLAMA_MODEL = 'llama3';
    const DEFAULT_DEEPSEEK_MODEL = 'deepseek-chat';
    const DEFAULT_KIMI_MODEL = 'moonshot-v1-32k';
    const DEFAULT_MINIMAX_MODEL = 'MiniMax-Text-01';

    /**
     * Definición de proveedores compatibles con OpenAI Chat Completions API.
     * Permite añadir nuevos proveedores sin tocar código.
     */
    public static function get_openai_compatible_providers() {
        return array(
            'openai' => array(
                'label'      => 'OpenAI',
                'base_url'   => 'https://api.openai.com/v1',
                'default_model' => self::DEFAULT_OPENAI_MODEL,
                'models'     => array(
                    'gpt-4o'        => 'GPT-4o',
                    'gpt-4o-mini'   => 'GPT-4o mini',
                    'gpt-4-turbo'   => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ),
            ),
            'deepseek' => array(
                'label'      => 'DeepSeek (中国)',
                'base_url'   => 'https://api.deepseek.com/v1',
                'default_model' => self::DEFAULT_DEEPSEEK_MODEL,
                'models'     => array(
                    'deepseek-chat'     => 'DeepSeek Chat (V3)',
                    'deepseek-reasoner' => 'DeepSeek Reasoner (R1)',
                ),
            ),
            'kimi' => array(
                'label'      => 'Kimi / Moonshot (月之暗面)',
                'base_url'   => 'https://api.moonshot.ai/v1',
                'default_model' => self::DEFAULT_KIMI_MODEL,
                'models'     => array(
                    'moonshot-v1-8k'         => 'Moonshot v1 8k',
                    'moonshot-v1-32k'        => 'Moonshot v1 32k',
                    'moonshot-v1-128k'       => 'Moonshot v1 128k',
                    'kimi-k2-0905-preview'   => 'Kimi K2 (preview)',
                ),
            ),
            'minimax' => array(
                'label'      => 'MiniMax (海螺AI)',
                'base_url'   => 'https://api.minimax.chat/v1',
                'default_model' => self::DEFAULT_MINIMAX_MODEL,
                'models'     => array(
                    'MiniMax-Text-01' => 'MiniMax-Text-01',
                    'abab6.5-chat'    => 'abab6.5-chat',
                    'abab6.5s-chat'   => 'abab6.5s-chat',
                ),
            ),
        );
    }

    private $provider;
    private $config;

    public function __construct() {
        $this->config = AI_Agent_Settings::get_llm_config();
        $this->provider = $this->config['provider'];
    }

    public static function get_api_key($provider) {
        $env_map = array(
            'openai'    => 'AI_AGENT_OPENAI_KEY',
            'anthropic' => 'AI_AGENT_ANTHROPIC_KEY',
            'deepseek'  => 'AI_AGENT_DEEPSEEK_KEY',
            'kimi'      => 'AI_AGENT_KIMI_KEY',
            'minimax'   => 'AI_AGENT_MINIMAX_KEY',
        );

        if (isset($env_map[$provider]) && defined($env_map[$provider]) && constant($env_map[$provider])) {
            return constant($env_map[$provider]);
        }

        return get_option('ai_agent_' . $provider . '_key', '');
    }

    public static function get_model($provider) {
        $providers = self::get_openai_compatible_providers();
        $default = isset($providers[$provider]['default_model']) ? $providers[$provider]['default_model'] : '';
        return get_option('ai_agent_' . $provider . '_model', $default);
    }

    public static function get_base_url($provider) {
        $providers = self::get_openai_compatible_providers();
        return isset($providers[$provider]['base_url']) ? $providers[$provider]['base_url'] : '';
    }

    public function chat($messages, $context = '') {
        $system_prompt = $this->build_system_prompt($context);

        $full_messages = array_merge(array(
            array('role' => 'system', 'content' => $system_prompt)
        ), $messages);

        if (array_key_exists($this->provider, self::get_openai_compatible_providers())) {
            return $this->chat_openai_compatible($full_messages, $this->provider);
        }

        switch ($this->provider) {
            case 'anthropic':
                return $this->chat_with_anthropic($full_messages);
            case 'ollama':
                return $this->chat_with_ollama($full_messages);
            default:
                return new WP_Error('invalid_provider', 'Proveedor de LLM no válido: ' . $this->provider);
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

    /**
     * Llama a cualquier endpoint compatible con OpenAI Chat Completions.
     * Usado por: openai, deepseek, kimi (moonshot), minimax.
     */
    private function chat_openai_compatible($messages, $provider) {
        $api_key = self::get_api_key($provider);
        if (empty($api_key)) {
            return new WP_Error('missing_key', ucfirst($provider) . ' API key no configurada');
        }

        $base_url = self::get_base_url($provider);
        $model = self::get_model($provider);

        if (empty($base_url) || empty($model)) {
            return new WP_Error('missing_config', 'Configuración incompleta para ' . $provider);
        }

        $response = wp_remote_post(rtrim($base_url, '/') . '/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 1000,
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $msg = is_array($body['error']) ? ($body['error']['message'] ?? wp_json_encode($body['error'])) : $body['error'];
            return new WP_Error('api_error', $msg);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            return $body['choices'][0]['message']['content'];
        }

        // MiniMax a veces devuelve "reply" o "output_text" según el modelo
        if (isset($body['reply'])) {
            return $body['reply'];
        }

        return '';
    }

    private function chat_with_anthropic($messages) {
        $api_key = self::get_api_key('anthropic');
        if (empty($api_key)) {
            return new WP_Error('missing_key', 'Anthropic API key no configurada');
        }

        $system = '';
        $filtered_messages = array();

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $system !== '' ? $system . "\n\n" . $msg['content'] : $msg['content'];
                continue;
            }
            $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
            $filtered_messages[] = array('role' => $role, 'content' => $msg['content']);
        }

        if (empty($filtered_messages)) {
            return new WP_Error('empty_messages', 'No hay mensajes de usuario');
        }

        $model = get_option('ai_agent_anthropic_model', self::DEFAULT_ANTHROPIC_MODEL);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model' => $model,
                'max_tokens' => 1000,
                'system' => $system,
                'messages' => $filtered_messages,
            )),
            'timeout' => 60,
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
        $model = $this->config['ollama_model'] ?: self::DEFAULT_OLLAMA_MODEL;

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

        $response = wp_remote_post(rtrim($url, '/') . '/api/chat', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
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
            return array('success' => false, 'error' => $result->get_error_message());
        }

        return array('success' => true, 'response' => $result);
    }
}
