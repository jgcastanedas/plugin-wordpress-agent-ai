<?php

class AI_Agent_Database {
    private static $instance = null;
    private $charset_collate;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = array();

        $sql[] = "CREATE TABLE {$this->get_table('conversations')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'active',
            agent_role VARCHAR(20) DEFAULT 'advisor',
            total_messages INT UNSIGNED DEFAULT 0,
            total_tokens INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_session_id (session_id),
            KEY idx_phone (phone),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('messages')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            tokens_used INT UNSIGNED DEFAULT 0,
            model_used VARCHAR(50) DEFAULT NULL,
            cost_usd DECIMAL(10, 6) DEFAULT 0,
            intent_detected VARCHAR(100) DEFAULT NULL,
            context_used TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_conversation (conversation_id),
            KEY idx_role (role),
            KEY idx_created (created_at),
            CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id)
                REFERENCES {$this->get_table('conversations')}(id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('product_fiches')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            price DECIMAL(10, 2) DEFAULT NULL,
            sale_price DECIMAL(10, 2) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'active',
            categories VARCHAR(500) DEFAULT NULL,
            short_description VARCHAR(500) DEFAULT NULL,
            embedding LONGTEXT DEFAULT NULL,
            embedding_model VARCHAR(50) DEFAULT NULL,
            indexed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_product_id (product_id),
            KEY idx_sku (sku),
            KEY idx_status (status)
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('knowledge_index')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(20) NOT NULL,
            source_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(500) NOT NULL,
            content TEXT NOT NULL,
            url VARCHAR(1000) DEFAULT NULL,
            embedding LONGTEXT DEFAULT NULL,
            embedding_model VARCHAR(50) DEFAULT NULL,
            tokens_estimate INT UNSIGNED DEFAULT 0,
            indexed_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source_type, source_id),
            KEY idx_active (is_active),
            KEY idx_indexed (indexed_at)
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('campaigns')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) DEFAULT NULL,
            message TEXT NOT NULL,
            discount_type VARCHAR(20) DEFAULT NULL,
            discount_value DECIMAL(10, 2) DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            impressions INT UNSIGNED DEFAULT 0,
            conversions INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_code (code),
            KEY idx_active (is_active),
            KEY idx_dates (start_date, end_date)
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('cart')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED DEFAULT 1,
            price_at_add DECIMAL(10, 2) DEFAULT NULL,
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_conversation (conversation_id),
            CONSTRAINT fk_cart_conversation FOREIGN KEY (conversation_id)
                REFERENCES {$this->get_table('conversations')}(id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('metrics_daily')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            total_conversations INT UNSIGNED DEFAULT 0,
            total_messages INT UNSIGNED DEFAULT 0,
            total_tokens_used INT UNSIGNED DEFAULT 0,
            total_cost_usd DECIMAL(10, 2) DEFAULT 0,
            unique_users INT UNSIGNED DEFAULT 0,
            avg_messages_per_conversation DECIMAL(5, 2) DEFAULT 0,
            campaign_impressions INT UNSIGNED DEFAULT 0,
            campaign_conversions INT UNSIGNED DEFAULT 0,
            cart_created INT UNSIGNED DEFAULT 0,
            checkout_started INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_date (date)
        ) {$this->charset_collate};";

        $sql[] = "CREATE TABLE {$this->get_table('token_usage')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED DEFAULT NULL,
            date DATE NOT NULL,
            model VARCHAR(50) NOT NULL,
            prompt_tokens INT UNSIGNED DEFAULT 0,
            completion_tokens INT UNSIGNED DEFAULT 0,
            total_tokens INT UNSIGNED DEFAULT 0,
            cost_per_1k_prompt DECIMAL(8, 4) DEFAULT 0,
            cost_per_1k_completion DECIMAL(8, 4) DEFAULT 0,
            total_cost DECIMAL(10, 6) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date_model (date, model),
            KEY idx_conversation (conversation_id)
        ) {$this->charset_collate};";

        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('ai_agent_db_version', AI_AGENT_VERSION);
    }

    public function get_table($name) {
        global $wpdb;
        return $wpdb->prefix . 'ai_agent_' . $name;
    }

    public function drop_tables() {
        global $wpdb;
        $tables = array(
            'cart',
            'metrics_daily',
            'token_usage',
            'campaigns',
            'knowledge_index',
            'product_fiches',
            'messages',
            'conversations'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$this->get_table($table)}");
        }

        delete_option('ai_agent_db_version');
    }

    public static function uninstall() {
        $instance = self::get_instance();
        $instance->drop_tables();
    }
}

class AI_Agent_Conversation {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_conversations';
    }

    public function create($data) {
        global $wpdb;

        $defaults = array(
            'session_id' => $this->generate_session_id(),
            'phone' => null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : null,
            'ip_address' => $this->get_client_ip(),
            'status' => 'active',
            'agent_role' => get_option('ai_agent_default_role', 'advisor')
        );

        $data = wp_parse_args($data, $defaults);

        $wpdb->insert($this->table, array(
            'session_id' => $data['session_id'],
            'phone' => $data['phone'],
            'user_agent' => $data['user_agent'],
            'ip_address' => $data['ip_address'],
            'status' => $data['status'],
            'agent_role' => $data['agent_role'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_activity_at' => current_time('mysql')
        ));

        return $wpdb->insert_id;
    }

    public function get_by_session($session_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE session_id = %s",
            $session_id
        ));
    }

    public function get_by_phone($phone) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE phone = %s ORDER BY created_at DESC",
            $phone
        ));
    }

    public function update_activity($id) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            array(
                'last_activity_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id)
        );
    }

    public function increment_stats($id, $tokens = 0) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET total_messages = total_messages + 1, total_tokens = total_tokens + %d, updated_at = %s WHERE id = %d",
            $tokens,
            current_time('mysql'),
            $id
        ));
    }

    public function update_role($id, $role) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            array('agent_role' => sanitize_text_field($role)),
            array('id' => $id)
        );
    }

    public function close($id) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            array('status' => 'closed', 'updated_at' => current_time('mysql')),
            array('id' => $id)
        );
    }

    public function get_all($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => null,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = "1=1";
        if ($args['status']) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($sql, $args['per_page'], $offset));
    }

    public function count($status = null) {
        global $wpdb;

        if ($status) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
                $status
            ));
        }

        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    public function get_conversation_messages($conversation_id, $limit = 50) {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'ai_agent_messages';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_table} WHERE conversation_id = %d ORDER BY created_at DESC LIMIT %d",
            $conversation_id,
            $limit
        ));
    }

    private function generate_session_id() {
        return uniqid('conv_', true) . '_' . wp_generate_password(16, false);
    }

    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

class AI_Agent_Message {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_messages';
    }

    public function create($data) {
        global $wpdb;

        $cost_usd = isset($data['cost_usd']) ? $data['cost_usd'] : $this->calculate_cost($data);

        $wpdb->insert($this->table, array(
            'conversation_id' => intval($data['conversation_id']),
            'role' => sanitize_text_field($data['role']),
            'content' => $data['content'],
            'tokens_used' => isset($data['tokens_used']) ? intval($data['tokens_used']) : 0,
            'model_used' => isset($data['model_used']) ? sanitize_text_field($data['model_used']) : null,
            'cost_usd' => $cost_usd,
            'intent_detected' => isset($data['intent_detected']) ? sanitize_text_field($data['intent_detected']) : null,
            'context_used' => isset($data['context_used']) ? maybe_serialize($data['context_used']) : null,
            'created_at' => current_time('mysql')
        ));

        return $wpdb->insert_id;
    }

    public function get_by_conversation($conversation_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE conversation_id = %d ORDER BY created_at ASC",
            $conversation_id
        ));
    }

    public function get_recent($limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, c.session_id, c.phone FROM {$this->table} m
            JOIN {$wpdb->prefix}ai_agent_conversations c ON m.conversation_id = c.id
            ORDER BY m.created_at DESC LIMIT %d",
            $limit
        ));
    }

    private function calculate_cost($data) {
        $tokens = isset($data['tokens_used']) ? $data['tokens_used'] : 0;
        $model = isset($data['model_used']) ? $data['model_used'] : 'gpt-4o';

        $pricing = AI_Agent_Token_Pricing::get_pricing($model);

        $prompt_cost = ($tokens * 0.75) / 1000 * $pricing['prompt'];
        $completion_cost = ($tokens * 0.25) / 1000 * $pricing['completion'];

        return $prompt_cost + $completion_cost;
    }
}

class AI_Agent_Product_Fiche {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_product_fiches';
    }

    public function create_or_update($product_id, $data) {
        global $wpdb;

        $existing = $this->get_by_product_id($product_id);

        $fiche_data = array(
            'product_id' => $product_id,
            'name' => sanitize_text_field($data['name']),
            'sku' => isset($data['sku']) ? sanitize_text_field($data['sku']) : null,
            'price' => isset($data['price']) ? floatval($data['price']) : null,
            'sale_price' => isset($data['sale_price']) ? floatval($data['sale_price']) : null,
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'categories' => isset($data['categories']) ? sanitize_text_field($data['categories']) : null,
            'short_description' => $this->optimize_description($data['description'] ?? '', $data['short_description'] ?? ''),
            'indexed_at' => current_time('mysql')
        );

        if ($existing) {
            $wpdb->update($this->table, $fiche_data, array('product_id' => $product_id));
            return $existing->id;
        } else {
            $wpdb->insert($this->table, $fiche_data);
            return $wpdb->insert_id;
        }
    }

    public function get_by_product_id($product_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE product_id = %d",
            $product_id
        ));
    }

    public function get_all_active() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY name ASC"
        );
    }

    public function get_for_embedding() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, product_id, name, sku, price, sale_price, categories, short_description FROM {$this->table} WHERE status = 'active'"
        );
    }

    public function search($query, $limit = 10) {
        global $wpdb;

        $search_term = '%' . $wpdb->esc_like($query) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE status = 'active' AND (
                name LIKE %s OR sku LIKE %s OR short_description LIKE %s
            ) LIMIT %d",
            $search_term, $search_term, $search_term, $limit
        ));
    }

    private function optimize_description($description, $short_description = '') {
        if (!empty($short_description)) {
            $text = wp_strip_all_tags($short_description);
        } else {
            $text = wp_strip_all_tags($description);
        }

        $text = preg_replace('/\s+/', ' ', $text);

        $max_chars = 400;
        if (strlen($text) > $max_chars) {
            $text = substr($text, 0, $max_chars);
            $last_space = strrpos($text, ' ');
            if ($last_space !== false) {
                $text = substr($text, 0, $last_space);
            }
            $text .= '...';
        }

        return $text;
    }

    public function update_embedding($id, $embedding, $model) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            array(
                'embedding' => maybe_serialize($embedding),
                'embedding_model' => sanitize_text_field($model)
            ),
            array('id' => $id)
        );
    }

    public function index_all_products() {
        if (!class_exists('WooCommerce')) {
            return array('total' => 0, 'indexed' => 0);
        }

        $products = wc_get_products(array('limit' => -1, 'status' => 'publish'));

        $count = 0;
        foreach ($products as $product) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            $categories_str = implode(', ', $categories);

            $this->create_or_update($product->get_id(), array(
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'sale_price' => $product->get_sale_price(),
                'status' => $product->get_status(),
                'categories' => $categories_str,
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description()
            ));

            $count++;
        }

        return array('total' => count($products), 'indexed' => $count);
    }
}

class AI_Agent_Cart {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_cart';
    }

    public function add_item($conversation_id, $product_id, $quantity = 1) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, quantity FROM {$this->table} WHERE conversation_id = %d AND product_id = %d",
            $conversation_id,
            $product_id
        ));

        if ($existing) {
            $wpdb->update(
                $this->table,
                array('quantity' => $existing->quantity + $quantity),
                array('id' => $existing->id)
            );
            return $existing->id;
        }

        $product = wc_get_product($product_id);

        $wpdb->insert($this->table, array(
            'conversation_id' => $conversation_id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price_at_add' => $product ? $product->get_price() : 0,
            'added_at' => current_time('mysql')
        ));

        return $wpdb->insert_id;
    }

    public function remove_item($id) {
        global $wpdb;
        $wpdb->delete($this->table, array('id' => $id));
    }

    public function update_quantity($id, $quantity) {
        global $wpdb;

        if ($quantity <= 0) {
            return $this->remove_item($id);
        }

        $wpdb->update(
            $this->table,
            array('quantity' => $quantity),
            array('id' => $id)
        );
    }

    public function get_by_conversation($conversation_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title as product_name, pm.meta_value as product_image
            FROM {$this->table} c
            JOIN {$wpdb->posts} p ON c.product_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE c.conversation_id = %d",
            $conversation_id
        ));
    }

    public function get_checkout_url($conversation_id) {
        $items = $this->get_by_conversation($conversation_id);

        if (empty($items)) {
            return wc_get_checkout_url();
        }

        $cart_url = wc_get_cart_url();
        $params = array();

        foreach ($items as $item) {
            $params[] = $item->product_id . ':' . $item->quantity;
        }

        return add_query_arg(array(
            'ai_agent_cart' => base64_encode(implode(',', $params))
        ), $cart_url);
    }

    public function clear($conversation_id) {
        global $wpdb;
        $wpdb->delete($this->table, array('conversation_id' => $conversation_id));
    }

    public function get_total($conversation_id) {
        global $wpdb;

        $items = $this->get_by_conversation($conversation_id);
        $total = 0;

        foreach ($items as $item) {
            $total += floatval($item->price_at_add) * $item->quantity;
        }

        return $total;
    }

    public function count_items($conversation_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(quantity) FROM {$this->table} WHERE conversation_id = %d",
            $conversation_id
        ));
    }
}

class AI_Agent_Campaign {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ai_agent_campaigns';
    }

    public function create($data) {
        global $wpdb;

        $wpdb->insert($this->table, array(
            'name' => sanitize_text_field($data['name']),
            'code' => isset($data['code']) ? sanitize_text_field($data['code']) : null,
            'message' => sanitize_textarea_field($data['message']),
            'discount_type' => isset($data['discount_type']) ? sanitize_text_field($data['discount_type']) : null,
            'discount_value' => isset($data['discount_value']) ? floatval($data['discount_value']) : null,
            'start_date' => isset($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => isset($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
            'created_at' => current_time('mysql')
        ));

        return $wpdb->insert_id;
    }

    public function get_active() {
        global $wpdb;

        $now = current_time('mysql');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE is_active = 1 AND (start_date IS NULL OR start_date <= %s) AND (end_date IS NULL OR end_date >= %s)",
            $now,
            $now
        ));
    }

    public function get_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE code = %s AND is_active = 1",
            sanitize_text_field($code)
        ));
    }

    public function increment_impression($id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET impressions = impressions + 1 WHERE id = %d",
            $id
        ));
    }

    public function increment_conversion($id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET conversions = conversions + 1 WHERE id = %d",
            $id
        ));
    }
}

class AI_Agent_Token_Pricing {
    private static $pricing = array(
        'gpt-4o' => array('prompt' => 0.005, 'completion' => 0.015),
        'gpt-4-turbo' => array('prompt' => 0.01, 'completion' => 0.03),
        'gpt-3.5-turbo' => array('prompt' => 0.0005, 'completion' => 0.0015),
        'claude-3-5-sonnet-20250514' => array('prompt' => 0.003, 'completion' => 0.015),
        'claude-3-opus' => array('prompt' => 0.015, 'completion' => 0.075),
        'claude-3-sonnet' => array('prompt' => 0.003, 'completion' => 0.015),
        'llama3' => array('prompt' => 0, 'completion' => 0),
        'mistral' => array('prompt' => 0, 'completion' => 0),
    );

    public static function get_pricing($model) {
        return isset(self::$pricing[$model]) ? self::$pricing[$model] : array('prompt' => 0, 'completion' => 0);
    }

    public static function calculate_cost($model, $prompt_tokens, $completion_tokens) {
        $pricing = self::get_pricing($model);

        $prompt_cost = ($prompt_tokens / 1000) * $pricing['prompt'];
        $completion_cost = ($completion_tokens / 1000) * $pricing['completion'];

        return $prompt_cost + $completion_cost;
    }

    public static function get_available_models() {
        return array(
            'openai' => array(
                'gpt-4o' => 'GPT-4o ($5/1M prompt, $15/1M completion)',
                'gpt-4-turbo' => 'GPT-4 Turbo ($10/1M prompt, $30/1M completion)',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo ($0.50/1M prompt, $1.50/1M completion)'
            ),
            'anthropic' => array(
                'claude-3-5-sonnet-20250514' => 'Claude 3.5 Sonnet ($3/1M prompt, $15/1M completion)',
                'claude-3-opus' => 'Claude 3 Opus ($15/1M prompt, $75/1M completion)',
                'claude-3-sonnet' => 'Claude 3 Sonnet ($3/1M prompt, $15/1M completion)'
            ),
            'ollama' => array(
                'llama3' => 'Llama 3 (Local - Gratis)',
                'mistral' => 'Mistral (Local - Gratis)'
            )
        );
    }
}

class AI_Agent_Metrics {
    private $table_daily;
    private $table_token_usage;

    public function __construct() {
        global $wpdb;
        $this->table_daily = $wpdb->prefix . 'ai_agent_metrics_daily';
        $this->table_token_usage = $wpdb->prefix . 'ai_agent_token_usage';
    }

    public function record_message($conversation_id, $tokens, $cost, $model) {
        global $wpdb;

        $date = date('Y-m-d');

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_token_usage} WHERE date = %s AND model = %s AND conversation_id IS NULL",
            $date,
            $model
        ));

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_token_usage} SET total_tokens = total_tokens + %d, total_cost = total_cost + %f WHERE id = %d",
                $tokens,
                $cost,
                $existing->id
            ));
        } else {
            $pricing = AI_Agent_Token_Pricing::get_pricing($model);

            $wpdb->insert($this->table_token_usage, array(
                'date' => $date,
                'model' => $model,
                'total_tokens' => $tokens,
                'prompt_tokens' => intval($tokens * 0.75),
                'completion_tokens' => intval($tokens * 0.25),
                'cost_per_1k_prompt' => $pricing['prompt'],
                'cost_per_1k_completion' => $pricing['completion'],
                'total_cost' => $cost
            ));
        }
    }

    public function update_daily_metrics($date = null) {
        global $wpdb;

        if (!$date) {
            $date = date('Y-m-d');
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(m.id) as total_messages,
                COALESCE(SUM(m.tokens_used), 0) as total_tokens,
                COALESCE(SUM(m.cost_usd), 0) as total_cost,
                COUNT(DISTINCT c.phone) as unique_users,
                COUNT(DISTINCT c.session_id) as unique_sessions
            FROM {$wpdb->prefix}ai_agent_conversations c
            LEFT JOIN {$wpdb->prefix}ai_agent_messages m ON c.id = m.conversation_id
            WHERE DATE(c.created_at) = %s",
            $date
        ));

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_daily} WHERE date = %s",
            $date
        ));

        $data = array(
            'date' => $date,
            'total_conversations' => $stats->total_conversations ?: 0,
            'total_messages' => $stats->total_messages ?: 0,
            'total_tokens_used' => $stats->total_tokens ?: 0,
            'total_cost_usd' => $stats->total_cost ?: 0,
            'unique_users' => $stats->unique_users ?: 0
        );

        if ($stats->total_conversations > 0) {
            $data['avg_messages_per_conversation'] = $stats->total_messages / $stats->total_conversations;
        }

        if ($existing) {
            $wpdb->update($this->table_daily, $data, array('id' => $existing->id));
        } else {
            $wpdb->insert($this->table_daily, $data);
        }
    }

    public function get_daily_metrics($days = 30) {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_daily} WHERE date >= %s ORDER BY date ASC",
            $start_date
        ));
    }

    public function get_token_usage_by_model($days = 30) {
        global $wpdb;

        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT model, SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost, COUNT(*) as requests
            FROM {$this->table_token_usage}
            WHERE date >= %s
            GROUP BY model
            ORDER BY total_tokens DESC",
            $start_date
        ));
    }

    public function get_summary() {
        global $wpdb;

        $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_agent_conversations");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_agent_messages");
        $total_tokens = $wpdb->get_var("SELECT COALESCE(SUM(total_tokens), 0) FROM {$wpdb->prefix}ai_agent_messages");
        $total_cost = $wpdb->get_var("SELECT COALESCE(SUM(cost_usd), 0) FROM {$wpdb->prefix}ai_agent_messages");

        $today_start = date('Y-m-d 00:00:00');
        $today_conversations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_agent_conversations WHERE created_at >= %s",
            $today_start
        ));

        return array(
            'total_conversations' => (int) $total_conversations,
            'total_messages' => (int) $total_messages,
            'total_tokens' => (int) $total_tokens,
            'total_cost_usd' => floatval($total_cost),
            'today_conversations' => (int) $today_conversations
        );
    }
}