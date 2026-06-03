<?php

class AI_Agent_Scheduler {
    private $hook_prefix = 'ai_agent_hourly_';

    public function __construct() {
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
        add_action('init', array($this, 'schedule_events'));
        add_action($this->hook_prefix . 'indexer', array($this, 'run_hourly_indexer'));
        add_action($this->hook_prefix . 'metrics', array($this, 'update_daily_metrics'));
        add_action($this->hook_prefix . 'cleanup', array($this, 'cleanup_old_data'));
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

class AI_Agent_Index {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_knowledge_index';
    }

    public function index_content($source_type, $source_id, $title, $content, $url = '') {
        global $wpdb;

        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);

        $tokens_estimate = str_word_count($content);
        $tokens_estimate = (int) ($tokens_estimate * 1.33);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE source_type = %s AND source_id = %d",
            $source_type,
            $source_id
        ));

        $kb = new AI_Agent_Knowledge_Base();
        $embedding = $kb->create_embeddings($content);

        $data = array(
            'source_type' => $source_type,
            'source_id' => $source_id,
            'title' => sanitize_text_field($title),
            'content' => $content,
            'url' => esc_url_raw($url),
            'embedding' => $embedding ? maybe_serialize($embedding) : null,
            'embedding_model' => get_option('ai_agent_embedding_provider', 'openai'),
            'tokens_estimate' => $tokens_estimate,
            'indexed_at' => current_time('mysql'),
            'is_active' => 1
        );

        if ($existing) {
            $wpdb->update($this->table, $data, array('id' => $existing->id));
            return $existing->id;
        } else {
            $wpdb->insert($this->table, $data);
            return $wpdb->insert_id;
        }
    }

    public function get_content($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND is_active = 1",
            $id
        ));
    }

    public function search($query, $limit = 10) {
        $kb = new AI_Agent_Knowledge_Base();
        $query_embedding = $kb->create_embeddings($query);

        if (!$query_embedding) {
            return $this->fallback_search($query, $limit);
        }

        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1"
        );

        $scored = array();
        foreach ($results as $item) {
            if ($item->embedding) {
                $embedding = maybe_unserialize($item->embedding);
                if ($embedding) {
                    $similarity = $this->cosine_similarity($query_embedding, $embedding);
                    $scored[] = array(
                        'item' => $item,
                        'score' => $similarity
                    );
                }
            }
        }

        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($scored, 0, $limit);
    }

    private function fallback_search($query, $limit = 10) {
        global $wpdb;

        $query = sanitize_text_field($query);
        $search_term = '%' . $wpdb->esc_like($query) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE is_active = 1 AND (
                title LIKE %s OR content LIKE %s
            ) ORDER BY indexed_at DESC LIMIT %d",
            $search_term,
            $search_term,
            $limit
        ));
    }

    private function cosine_similarity($a, $b) {
        if (count($a) !== count($b)) {
            return 0;
        }

        $dot_product = 0;
        $norm_a = 0;
        $norm_b = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dot_product += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }

        $norm_a = sqrt($norm_a);
        $norm_b = sqrt($norm_b);

        if ($norm_a === 0 || $norm_b === 0) {
            return 0;
        }

        return $dot_product / ($norm_a * $norm_b);
    }

    public function get_stats() {
        global $wpdb;

        return array(
            'total_items' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1"),
            'pages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE source_type = 'page' AND is_active = 1"),
            'documents' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE source_type = 'document' AND is_active = 1"),
            'products' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE source_type = 'product' AND is_active = 1"),
            'total_tokens_estimate' => (int) $wpdb->get_var("SELECT SUM(tokens_estimate) FROM {$this->table} WHERE is_active = 1"),
            'last_indexed' => $wpdb->get_var("SELECT MAX(indexed_at) FROM {$this->table} WHERE is_active = 1")
        );
    }

    public function rebuild_all() {
        $kb = new AI_Agent_Knowledge_Base();

        $selected_pages = get_option('ai_agent_selected_pages', array());
        $pages = $kb->get_pages_content($selected_pages);

        $indexed = 0;
        foreach ($pages as $page) {
            $this->index_content('page', $page['id'], $page['title'], $page['content'], $page['url'] ?? '');
            $indexed++;
        }

        $docs = $kb->get_custom_documents();
        foreach ($docs as $doc) {
            $this->index_content('document', $doc['id'], $doc['title'], $doc['content']);
            $indexed++;
        }

        if (class_exists('WooCommerce')) {
            $fiche = new AI_Agent_Product_Fiche();
            $products = $fiche->get_for_embedding();

            foreach ($products as $product) {
                $content = $product->name;
                if ($product->categories) {
                    $content .= ' | Categorías: ' . $product->categories;
                }
                if ($product->short_description) {
                    $content .= ' | ' . $product->short_description;
                }
                if ($product->price) {
                    $content .= ' | Precio: ' . $product->price;
                }

                $this->index_content('product', $product->product_id, $product->name, $content);
                $indexed++;
            }
        }

        return $indexed;
    }
}