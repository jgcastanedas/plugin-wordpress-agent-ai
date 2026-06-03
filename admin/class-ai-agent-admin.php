<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Admin {
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'add_knowledge_base_menu'));
        add_action('add_meta_boxes', array($this, 'add_knowledge_meta_boxes'));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ai-agent') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        wp_enqueue_style(
            'ai-agent-admin',
            AI_AGENT_PLUGIN_URL . 'admin/admin.css',
            array(),
            AI_AGENT_VERSION
        );

        wp_enqueue_media();
    }

    public function add_knowledge_base_menu() {
        add_submenu_page(
            'ai-agent-chatbot',
            __('Documentos', 'ai-agent-chatbot'),
            __('Documentos', 'ai-agent-chatbot'),
            'manage_options',
            'edit.php?post_type=ai_agent_knowledge',
            null
        );

        add_submenu_page(
            'ai-agent-chatbot',
            __('Procesar Embeddings', 'ai-agent-chatbot'),
            __('Procesar Embeddings', 'ai-agent-chatbot'),
            'manage_options',
            'ai-agent-embeddings',
            array($this, 'render_embeddings_page')
        );
    }

    public function render_embeddings_page() {
        if (isset($_POST['process_embeddings']) && wp_verify_nonce($_POST['_wpnonce'], 'ai_agent_process_embeddings')) {
            $this->process_embeddings();
        }

        $kb = new AI_Agent_Knowledge_Base();
        $pages = $kb->get_pages_content();
        $docs = $kb->get_custom_documents();
        $products = $kb->get_woocommerce_products();
        ?>
        <div class="wrap">
            <h1><?php _e('Procesar Embeddings', 'ai-agent-chatbot'); ?></h1>

            <div class="ai-agent-status-card">
                <h2><?php _e('Estado de Indexación', 'ai-agent-chatbot'); ?></h2>
                <table class="widefat">
                    <tr>
                        <td><?php _e('Páginas indexadas:', 'ai-agent-chatbot'); ?></td>
                        <td><?php echo count($pages); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Documentos personalizados:', 'ai-agent-chatbot'); ?></td>
                        <td><?php echo count($docs); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Productos WooCommerce:', 'ai-agent-chatbot'); ?></td>
                        <td><?php echo count($products); ?></td>
                    </tr>
                </table>
            </div>

            <div class="ai-agent-actions">
                <h2><?php _e('Acciones', 'ai-agent-chatbot'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ai_agent_process_embeddings'); ?>
                    <input type="hidden" name="process_embeddings" value="1" />
                    <p><?php _e('Procesa los embeddings para mejorar la búsqueda semántica del chatbot.', 'ai-agent-chatbot'); ?></p>
                    <button type="submit" class="button button-primary">
                        <?php _e('Procesar Embeddings', 'ai-agent-chatbot'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    private function process_embeddings() {
        echo '<div class="notice notice-success"><p>';
        _e('Embeddings procesados correctamente (modo demo - sin API externa)', 'ai-agent-chatbot');
        echo '</p></div>';
    }

    public function add_knowledge_meta_boxes() {
        add_meta_box(
            'ai_agent_knowledge_info',
            __('Información del Documento', 'ai-agent-chatbot'),
            array($this, 'render_knowledge_meta_box'),
            'ai_agent_knowledge',
            'side'
        );
    }

    public function render_knowledge_meta_box($post) {
        $source = get_post_meta($post->ID, '_ai_agent_source', true);
        ?>
        <label for="ai_agent_source"><?php _e('Fuente:', 'ai-agent-chatbot'); ?></label>
        <select name="ai_agent_source" id="ai_agent_source">
            <option value="manual" <?php selected($source, 'manual'); ?>><?php _e('Manual', 'ai-agent-chatbot'); ?></option>
            <option value="uploaded" <?php selected($source, 'uploaded'); ?>><?php _e('Archivo subido', 'ai-agent-chatbot'); ?></option>
            <option value="url" <?php selected($source, 'url'); ?>><?php _e('Desde URL', 'ai-agent-chatbot'); ?></option>
        </select>
        <?php
    }

    public function save_knowledge_meta($post_id) {
        if (isset($_POST['ai_agent_source'])) {
            update_post_meta($post_id, '_ai_agent_source', sanitize_text_field($_POST['ai_agent_source']));
        }
    }
}