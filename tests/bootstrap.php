<?php
/**
 * Bootstrap para tests unit del plugin.
 * No carga WordPress: stubs mínimos para que las clases puedan instanciarse sin WP corriendo.
 */

define('ABSPATH', __DIR__ . '/../');
define('AI_AGENT_VERSION', '1.1.0-test');

// Funciones WP usadas por las clases bajo test
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return is_string($s) ? trim(strip_tags($s)) : ''; }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($s) { return is_string($s) ? trim(strip_tags($s)) : ''; }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($s) { $s = is_string($s) ? trim($s) : ''; return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : ''; }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($s) { return filter_var($s, FILTER_VALIDATE_URL) ? $s : ''; }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($s) { return is_string($s) ? trim(strip_tags($s)) : ''; }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
}
if (!function_exists('get_option')) {
    $GLOBALS['_options'] = array();
    function get_option($k, $d = false) { return $GLOBALS['_options'][$k] ?? $d; }
}
if (!function_exists('update_option')) {
    function update_option($k, $v) { $GLOBALS['_options'][$k] = $v; return true; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($t, $v) { return $v; }
}
if (!function_exists('home_url')) {
    function home_url($path = '') { return 'https://example.test' . $path; }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg() { return ''; }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code; public $message; public $data;
        public function __construct($code = '', $message = '', $data = array()) {
            $this->code = $code; $this->message = $message; $this->data = $data;
        }
        public function get_error_message() { return $this->message; }
    }
}

// Stub mínimo de AI_Agent_Settings para que el provider cargue
if (!class_exists('AI_Agent_Settings')) {
    class AI_Agent_Settings {
        public static function get_llm_config() {
            return array(
                'provider' => 'openai',
                'openai_key' => '',
                'anthropic_key' => '',
                'ollama_url' => 'http://localhost:11434',
                'ollama_model' => 'llama3',
            );
        }
        public static function get_webhook_secret() {
            return get_option('ai_agent_webhook_secret', '');
        }
    }
}

// Cargar archivos a testear sin WP runtime
require_once __DIR__ . '/../includes/class-ai-agent-utils.php';
require_once __DIR__ . '/../includes/class-ai-agent-llm-provider.php';
require_once __DIR__ . '/../includes/class-ai-agent-webhook.php';
