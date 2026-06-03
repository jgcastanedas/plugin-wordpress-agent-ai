<?php
/**
 * Plugin Name: AI Agent Chatbot
 * Plugin URI: https://github.com/jgcastanedas/plugin-wordpress-agent-ai
 * Description: Widget de chatbot con IA para WordPress con integración WooCommerce, base de conocimiento vectorial, webhooks para WhatsApp, dashboard de métricas y comportamiento inteligente del agente
 * Version: 1.0.0
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

define('AI_AGENT_VERSION', '1.0.0');
define('AI_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_AGENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-loader.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-database.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-settings.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-knowledge-base.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-scheduler.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-index.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-llm-provider.php';
require_once AI_AGENT_PLUGIN_DIR . 'includes/class-ai-agent-woocommerce.php';
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
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'register_post_types'));
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));
        add_action('init', array($this, 'init_scheduler'));
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

    public function activate() {
        $db = AI_Agent_Database::get_instance();
        $db->create_tables();

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