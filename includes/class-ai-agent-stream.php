<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Streaming SSE para respuestas del LLM.
 *
 * Endpoint: admin-ajax.php?action=ai_agent_stream
 *
 * Notas de despliegue:
 * - Nginx: asegurar `proxy_buffering off` o enviar header `X-Accel-Buffering: no` (lo hacemos abajo).
 * - Apache + mod_deflate: deshabilitar gzip para esta URL.
 * - PHP-FPM: el flush funciona si output_buffering=Off o si se hace ob_end_flush() explícitamente (lo hacemos).
 * - Cloudflare: respeta `X-Accel-Buffering: no` solo si el plan lo permite; idealmente desactivar el proxy para /wp-admin/admin-ajax.php.
 */
class AI_Agent_Stream {

    public function __construct() {
        add_action('wp_ajax_ai_agent_stream', array($this, 'handle_stream'));
        add_action('wp_ajax_nopriv_ai_agent_stream', array($this, 'handle_stream'));
    }

    public function handle_stream() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'ai_agent_nonce')) {
            status_header(403);
            exit('Forbidden');
        }

        $message = isset($_GET['message']) ? sanitize_textarea_field(wp_unslash($_GET['message'])) : '';
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : null;

        if (empty($message) || strlen($message) > 4000) {
            status_header(400);
            exit('Bad message');
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $rl_key = 'ai_agent_rl_' . md5($ip);
        $count = (int) get_transient($rl_key);
        $limit = (int) apply_filters('ai_agent_rate_limit_per_minute', 15);
        if ($count >= $limit) {
            status_header(429);
            exit('Rate limited');
        }
        set_transient($rl_key, $count + 1, 60);

        $this->send_sse_headers();

        $provider = get_option('ai_agent_llm_provider', 'openai');
        $accumulated = '';

        $on_chunk = function ($chunk) use (&$accumulated) {
            $accumulated .= $chunk;
            $this->send_event('chunk', array('text' => $chunk));
        };

        $openai_compatible = AI_Agent_LLM_Provider::get_openai_compatible_providers();

        $error = null;
        try {
            if (isset($openai_compatible[$provider])) {
                $error = $this->stream_openai_compatible($message, $provider, $on_chunk);
            } elseif ($provider === 'anthropic') {
                $error = $this->stream_anthropic($message, $on_chunk);
            } else {
                $this->send_event('error', array('message' => 'Streaming no soportado para ' . $provider));
                exit;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        if ($error) {
            $this->send_event('error', array('message' => $error));
        }

        $this->send_event('done', array('full' => $accumulated, 'session_id' => $session_id));
        exit;
    }

    private function send_sse_headers() {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @ob_implicit_flush(true);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
    }

    private function send_event($event, $data) {
        echo "event: " . $event . "\n";
        echo "data: " . wp_json_encode($data) . "\n\n";

        if (function_exists('fastcgi_finish_request')) {
            // no usar aquí — terminaría la request
        }
        @flush();
    }

    private function stream_openai_compatible($message, $provider, callable $on_chunk) {
        $api_key = AI_Agent_LLM_Provider::get_api_key($provider);
        if (empty($api_key)) {
            return ucfirst($provider) . ' API key no configurada';
        }

        $base_url = AI_Agent_LLM_Provider::get_base_url($provider);
        $model = AI_Agent_LLM_Provider::get_model($provider);

        if (empty($base_url) || empty($model)) {
            return 'Configuración incompleta para ' . $provider;
        }

        $kb = new AI_Agent_Knowledge_Base();
        $relevant = $kb->find_relevant_context($message, 5);
        $context = $kb->format_context_for_llm($relevant);

        $system = "Eres un asistente útil. Contexto:\n" . $context;

        $payload = wp_json_encode(array(
            'model' => $model,
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'messages' => array(
                array('role' => 'system', 'content' => $system),
                array('role' => 'user', 'content' => $message),
            ),
        ));

        $ch = curl_init(rtrim($base_url, '/') . '/chat/completions');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json',
            ),
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($on_chunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data: ') !== 0) continue;
                    $json = substr($line, 6);
                    if ($json === '[DONE]') continue;
                    $obj = json_decode($json, true);
                    if (isset($obj['choices'][0]['delta']['content'])) {
                        $on_chunk($obj['choices'][0]['delta']['content']);
                    }
                }
                return strlen($data);
            },
        ));

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        return $ok === false ? ($err ?: 'curl error') : null;
    }

    private function stream_anthropic($message, callable $on_chunk) {
        $api_key = AI_Agent_LLM_Provider::get_api_key('anthropic');
        if (empty($api_key)) {
            return 'Anthropic API key no configurada';
        }

        $model = get_option('ai_agent_anthropic_model', AI_Agent_LLM_Provider::DEFAULT_ANTHROPIC_MODEL);

        $kb = new AI_Agent_Knowledge_Base();
        $relevant = $kb->find_relevant_context($message, 5);
        $context = $kb->format_context_for_llm($relevant);

        $payload = wp_json_encode(array(
            'model' => $model,
            'max_tokens' => 1000,
            'stream' => true,
            'system' => "Eres un asistente útil. Contexto:\n" . $context,
            'messages' => array(
                array('role' => 'user', 'content' => $message),
            ),
        ));

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array(
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ),
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($on_chunk) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data: ') !== 0) continue;
                    $json = substr($line, 6);
                    $obj = json_decode($json, true);
                    if (isset($obj['type']) && $obj['type'] === 'content_block_delta') {
                        if (isset($obj['delta']['text'])) {
                            $on_chunk($obj['delta']['text']);
                        }
                    }
                }
                return strlen($data);
            },
        ));

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        return $ok === false ? ($err ?: 'curl error') : null;
    }
}
