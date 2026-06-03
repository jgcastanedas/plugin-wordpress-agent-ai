<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Scheduler {
    private $hook_prefix = 'ai_agent_hourly_';

    public function __construct() {
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
        add_action('init', array($this, 'schedule_events'));
        add_action($this->hook_prefix . 'indexer', array($this, 'run_hourly_indexer'));
        add_action($this->hook_prefix . 'metrics', array($this, 'update_daily_metrics'));
        add_action($this->hook_prefix . 'cleanup', array($this, 'cleanup_old_data'));

        add_action('save_post', array($this, 'on_save_post'), 20, 3);
        add_action('before_delete_post', array($this, 'on_delete_post'));
        add_action('woocommerce_update_product', array($this, 'on_product_change'));
        add_action('woocommerce_new_product', array($this, 'on_product_change'));
        add_action('woocommerce_delete_product', array($this, 'on_product_delete'));
    }

    public function on_save_post($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }

        if ($post->post_type === 'ai_agent_knowledge') {
            $index = new AI_Agent_Index();
            $index->index_content('document', $post_id, $post->post_title, $post->post_content);
            return;
        }

        if ($post->post_type === 'page') {
            $selected = get_option('ai_agent_selected_pages', array());
            if (in_array($post_id, (array) $selected)) {
                $index = new AI_Agent_Index();
                $index->index_content(
                    'page',
                    $post_id,
                    $post->post_title,
                    $post->post_content,
                    get_permalink($post_id)
                );
            }
        }
    }

    public function on_delete_post($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_agent_knowledge_index';
        $wpdb->delete($table, array('source_id' => $post_id), array('%d'));
    }

    public function on_product_change($product_id) {
        if (!class_exists('WooCommerce')) {
            return;
        }
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            $this->on_product_delete($product_id);
            return;
        }

        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        $content  = $product->get_name();
        if (!empty($categories)) {
            $content .= ' | Categorías: ' . implode(', ', $categories);
        }
        $short = $product->get_short_description() ?: wp_strip_all_tags($product->get_description());
        if (!empty($short)) {
            $content .= ' | ' . $short;
        }
        if ($product->get_price() !== '') {
            $content .= ' | Precio: ' . $product->get_price();
        }

        $index = new AI_Agent_Index();
        $index->index_content('product', $product_id, $product->get_name(), $content, get_permalink($product_id));
    }

    public function on_product_delete($product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ai_agent_knowledge_index';
        $wpdb->delete($table, array('source_type' => 'product', 'source_id' => $product_id), array('%s', '%d'));
    }

    public function add_custom_intervals($schedules) {
        $schedules['hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display' => __('Cada hora', 'ai-agent-chatbot')
        );

        $schedules['ai_agent_fifteen_minutes'] = array(
            'interval' => 15 * 60,
            'display' => __('Cada 15 minutos', 'ai-agent-chatbot')
        );

        return $schedules;
    }

    public function schedule_events() {
        if (!wp_next_scheduled($this->hook_prefix . 'indexer')) {
            wp_schedule_event(time(), 'hourly', $this->hook_prefix . 'indexer');
        }

        if (!wp_next_scheduled($this->hook_prefix . 'metrics')) {
            wp_schedule_event(time(), 'daily', $this->hook_prefix . 'metrics');
        }

        if (!wp_next_scheduled($this->hook_prefix . 'cleanup')) {
            wp_schedule_event(time(), 'daily', $this->hook_prefix . 'cleanup');
        }
    }

    public function run_hourly_indexer() {
        $last_run = get_option('ai_agent_last_index_run', 0);
        $now = time();

        if (($now - $last_run) < 1800) {
            return;
        }

        update_option('ai_agent_last_index_run', $now);

        $this->check_and_update_knowledge_base();
        $this->check_and_update_product_fiches();

        update_option('ai_agent_last_index_complete', $now);
    }

    private function check_and_update_knowledge_base() {
        $kb = new AI_Agent_Knowledge_Base();
        $index = new AI_Agent_Index();

        $selected_pages = get_option('ai_agent_selected_pages', array());
        $last_index = get_option('ai_agent_pages_last_index', 0);

        $pages_to_check = $selected_pages;
        $updates_made = 0;

        foreach ($pages_to_check as $page_id) {
            $page = get_post($page_id);

            if (!$page) {
                continue;
            }

            $page_modified = strtotime($page->post_modified);
            $page_hash = md5($page->post_content);

            $existing_hash = get_post_meta($page_id, '_ai_agent_content_hash', true);

            if ($page_modified > $last_index || $page_hash !== $existing_hash) {
                $content = $kb->get_pages_content(array($page_id));

                if (!empty($content)) {
                    $index->index_content(
                        'page',
                        $page_id,
                        $content[0]['title'],
                        $content[0]['content'],
                        $content[0]['url'] ?? ''
                    );

                    update_post_meta($page_id, '_ai_agent_content_hash', $page_hash);
                    $updates_made++;
                }
            }
        }

        return $updates_made;
    }

    private function check_and_update_product_fiches() {
        if (!class_exists('WooCommerce')) {
            return 0;
        }

        $last_check = get_option('ai_agent_products_last_check', 0);
        $now = time();

        if (($now - $last_check) < 1800) {
            return 0;
        }

        $fiche = new AI_Agent_Product_Fiche();
        $result = $fiche->index_all_products();

        update_option('ai_agent_products_last_check', $now);

        return $result['indexed'];
    }

    public function update_daily_metrics() {
        $metrics = new AI_Agent_Metrics();

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $metrics->update_daily_metrics($yesterday);

        $metrics->update_daily_metrics(date('Y-m-d'));
    }

    public function cleanup_old_data() {
        global $wpdb;

        $retention_days = apply_filters('ai_agent_retention_days', 90);

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $conversations_table = $wpdb->prefix . 'ai_agent_conversations';
        $messages_table = $wpdb->prefix . 'ai_agent_messages';

        $old_conversations = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$conversations_table} WHERE last_activity_at < %s AND status = 'closed'",
            $cutoff_date
        ));

        if (!empty($old_conversations)) {
            $placeholders = implode(',', array_fill(0, count($old_conversations), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$messages_table} WHERE conversation_id IN ({$placeholders})",
                $old_conversations
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$conversations_table} WHERE id IN ({$placeholders})",
                $old_conversations
            ));
        }

        update_option('ai_agent_cleanup_last_run', time());
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('ai_agent_hourly_indexer');
        wp_clear_scheduled_hook('ai_agent_hourly_metrics');
        wp_clear_scheduled_hook('ai_agent_hourly_cleanup');
    }

    public function get_status() {
        return array(
            'last_index_run' => get_option('ai_agent_last_index_run', 0),
            'last_index_complete' => get_option('ai_agent_last_index_complete', 0),
            'last_products_check' => get_option('ai_agent_products_last_check', 0),
            'last_cleanup' => get_option('ai_agent_cleanup_last_run', 0),
            'next_scheduled' => wp_next_scheduled($this->hook_prefix . 'indexer')
        );
    }

    public function force_reindex() {
        update_option('ai_agent_last_index_run', 0);
        update_option('ai_agent_pages_last_index', 0);
        update_option('ai_agent_products_last_check', 0);

        $this->run_hourly_indexer();
    }
}