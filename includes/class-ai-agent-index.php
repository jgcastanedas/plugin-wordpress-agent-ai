<?php

if (!defined('ABSPATH')) {
    exit;
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
            return $b['score'] <=> $a['score'];
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
        return AI_Agent_Utils::cosine_similarity($a, $b);
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
