<?php
/**
 * Plugin Name: AI Agent Chatbot
 * Plugin URI: https://github.com/jgcastanedas/plugin-wordpress-agent-ai
 * Description: Widget de chatbot con IA para WordPress con integración WooCommerce, base de conocimiento vectorial, webhooks para WhatsApp, dashboard de métricas y comportamiento inteligente del agente
 * Version: 1.1.0
 * Author: Julian Castaneda
 * Author URI: https://jgcastanedas.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-agent-chatbot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AI_AGENT_VERSION', '1.1.0');
define('AI_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_AGENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-loader.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-utils.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-database.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-kb-adapter.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-session-cache.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-settings.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-knowledge-base.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-scheduler.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-index.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-llm-provider.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-stream.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-woocommerce.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-wc-tools.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-tool-audit.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-privacy.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-webhook.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-widget.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-agent.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-dashboard.php';

class AI_Agent_Chatbot {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_components();
        $this->init_hooks();
    }

    private function init_components() {
        require_once AI_AGENT_PLUGIN_DIR . 'admin/class-ai-agent-admin.php';
        require_once AI_AGENT_PLUGIN_DIR . 'public/class-ai-agent-public.php';

        if (is_admin()) {
            new AI_Agent_Settings();
            new AI_Agent_Admin();
            new AI_Agent_Dashboard();
            new AI_Agent_Tool_Audit_Admin();
        }

        new AI_Agent_Privacy();
        new AI_Agent_Stream();
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'register_post_types'));
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));
        add_action('init', array($this, 'init_scheduler'));
        add_action('init', array($this, 'handle_cart_redirect'));
    }

    public function init_scheduler() {
        new AI_Agent_Scheduler();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-agent-chatbot',
            false,
            dirname(AI_AGENT_PLUGIN_BASENAME) . '/languages/'
        );
    }

    public function register_post_types() {
        register_post_type('ai_agent_knowledge', array(
            'labels' => array(
                'name' => __('Base de Conocimiento', 'ai-agent-chatbot'),
                'singular_name' => __('Documento', 'ai-agent-chatbot'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'menu_icon' => 'dashicons-media-document',
        ));
    }

    public function register_webhook_endpoints() {
        $webhook = new AI_Agent_Webhook();
        $webhook->register_routes();
    }

    public function handle_cart_redirect() {
        if (empty($_GET['ai_agent_cart']) || !class_exists('WooCommerce') || is_admin()) {
            return;
        }

        $encoded = sanitize_text_field(wp_unslash($_GET['ai_agent_cart']));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            return;
        }

        $items = explode(',', $decoded);
        foreach ($items as $item) {
            if (!str_contains($item, ':')) {
                continue;
            }
            list($product_id, $qty) = explode(':', $item, 2);
            $product_id = absint($product_id);
            $qty = max(1, min(100, absint($qty)));

            if ($product_id > 0 && function_exists('wc_get_product')) {
                $product = wc_get_product($product_id);
                if ($product && $product->is_purchasable() && WC()->cart) {
                    WC()->cart->add_to_cart($product_id, $qty);
                }
            }
        }
    }

    public function activate() {
        $db = AI_Agent_Database::get_instance();
        $db->create_tables();

        AI_Agent_Tool_Audit::init_table();

        if (!get_option('ai_agent_webhook_secret')) {
            update_option('ai_agent_webhook_secret', wp_generate_password(32, false));
        }

        $this->init_scheduler();

        flush_rewrite_rules();
        set_transient('ai_agent_activated', true, 30);
    }

    public function deactivate() {
        AI_Agent_Scheduler::deactivate();
        flush_rewrite_rules();
    }
}

function ai_agent_load() {
    return AI_Agent_Chatbot::get_instance();
}

add_action('plugins_loaded', 'ai_agent_load');

register_activation_hook(__FILE__, array(AI_Agent_Chatbot::get_instance(), 'activate'));
register_deactivation_hook(__FILE__, array(AI_Agent_Chatbot::get_instance(), 'deactivate'));

register_uninstall_hook(__FILE__, array('AI_Agent_Database', 'uninstall'));
