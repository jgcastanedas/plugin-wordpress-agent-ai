<?php

class AI_Agent_Knowledge_Base {
    private $embedding_provider = 'openai';
    private $embeddings = array();

    public function __construct() {
        $this->embedding_provider = get_option('ai_agent_embedding_provider', 'openai');
    }

    public function get_pages_content($page_ids = array()) {
        if (empty($page_ids)) {
            $page_ids = get_option('ai_agent_selected_pages', array());
        }

        $content = array();
        foreach ($page_ids as $page_id) {
            $page = get_post($page_id);
            if ($page && $page->post_content) {
                $content[] = array(
                    'id' => $page_id,
                    'title' => $page->post_title,
                    'content' => wp_strip_all_tags($page->post_content),
                    'url' => get_permalink($page_id)
                );
            }
        }
        return $content;
    }

    public function get_woocommerce_products() {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish',
        ));

        $product_data = array();
        foreach ($products as $product) {
            $product_data[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'status' => $product->get_status(),
                'sku' => $product->get_sku(),
                'permalink' => get_permalink($product->get_id()),
                'image' => wp_get_attachment_url($product->get_image_id()),
                'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
            );
        }
        return $product_data;
    }

    public function create_product_checkout_link($product_id, $quantity = 1) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        return add_query_arg(array(
            'add-to-cart' => $product_id,
            'quantity' => $quantity
        ), wc_get_checkout_url());
    }

    public function get_custom_documents() {
        $args = array(
            'post_type' => 'ai_agent_knowledge',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        $documents = get_posts($args);

        $content = array();
        foreach ($documents as $doc) {
            $content[] = array(
                'id' => $doc->ID,
                'title' => $doc->post_title,
                'content' => wp_strip_all_tags($doc->post_content),
            );
        }
        return $content;
    }

    public function create_embeddings($text) {
        if ($this->embedding_provider === 'openai') {
            return $this->create_openai_embedding($text);
        }
        return $this->create_local_embedding($text);
    }

    private function create_openai_embedding($text) {
        $api_key = get_option('ai_agent_openai_key', '');
        if (empty($api_key)) {
            return null;
        }

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'text-embedding-3-small',
                'input' => $text,
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'][0]['embedding'] ?? null;
    }

    private function create_local_embedding($text) {
        $words = str_word_count(strtolower($text), 1);
        $word_freq = array_count_values($words);
        $embedding = array_fill(0, 384, 0.0);
        $total_words = count($words);
        if ($total_words === 0) {
            return $embedding;
        }

        foreach ($word_freq as $word => $freq) {
            $hash = crc32($word);
            $idx = abs($hash) % 384;
            $embedding[$idx] += $freq / $total_words;
        }

        $norm = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $embedding)));
        if ($norm > 0) {
            $embedding = array_map(function($x) use ($norm) { return $x / $norm; }, $embedding);
        }

        return $embedding;
    }

    public function build_context($user_query) {
        $context = array();

        $pages = $this->get_pages_content();
        foreach ($pages as $page) {
            $context[] = array(
                'source' => 'page',
                'title' => $page['title'],
                'content' => $page['content'],
                'url' => $page['url']
            );
        }

        $products = $this->get_woocommerce_products();
        foreach ($products as $product) {
            $context[] = array(
                'source' => 'product',
                'title' => $product['name'],
                'content' => $product['name'] . '. ' . $product['description'] . '. Precio: ' . $product['price'],
                'checkout_link' => $this->create_product_checkout_link($product['id']),
                'price' => $product['price'],
                'sku' => $product['sku']
            );
        }

        $docs = $this->get_custom_documents();
        foreach ($docs as $doc) {
            $context[] = array(
                'source' => 'document',
                'title' => $doc['title'],
                'content' => $doc['content']
            );
        }

        return $context;
    }

    public function find_relevant_context($query, $max_results = 5) {
        $context = $this->build_context($query);
        $query_embedding = $this->create_embeddings($query);

        if (!$query_embedding) {
            return array_slice($context, 0, $max_results);
        }

        $scored = array();
        foreach ($context as $item) {
            $content = $item['content'];
            if (strlen($content) > 8000) {
                $content = substr($content, 0, 8000);
            }
            $item_embedding = $this->create_embeddings($content);
            if ($item_embedding) {
                $similarity = $this->cosine_similarity($query_embedding, $item_embedding);
                $scored[] = array(
                    'item' => $item,
                    'score' => $similarity
                );
            } else {
                $scored[] = array(
                    'item' => $item,
                    'score' => 0
                );
            }
        }

        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($scored, 0, $max_results);
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

    public function format_context_for_llm($relevant_context) {
        $formatted = "";

        foreach ($relevant_context as $item) {
            $source = $item['item']['source'];
            $title = $item['item']['title'];

            $formatted .= "\n\n[" . strtoupper($source) . "] " . $title . ":\n";
            $formatted .= $item['item']['content'];

            if ($source === 'product' && isset($item['item']['checkout_link'])) {
                $formatted .= "\nLink de compra: " . $item['item']['checkout_link'];
            }
        }

        return $formatted;
    }
}