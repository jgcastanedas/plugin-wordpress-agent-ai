<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Session_Cache {
    private static $instance = null;
    private $cache = array();
    private $db;

    const CACHE_TABLE = 'ai_agent_session_cache';
    const DEFAULT_TTL = 900;
    const MAX_TOKENS_PER_SESSION = 6000;

    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->init_table();
        $this->load_active_sessions();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_table() {
        $table = $this->db->prefix . self::CACHE_TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            conversation_id BIGINT UNSIGNED DEFAULT NULL,
            context_data LONGTEXT NOT NULL,
            tokens_used INT UNSIGNED DEFAULT 0,
            last_access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_session (session_id),
            INDEX idx_expires (expires_at)
        ) {$this->db->get_charset_collate()};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function load_active_sessions() {
        $table = $this->db->prefix . self::CACHE_TABLE;
        $now = current_time('mysql');

        $sessions = $this->db->get_results($this->db->prepare(
            "SELECT session_id, context_data, tokens_used FROM {$table} WHERE expires_at > %s",
            $now
        ));

        foreach ($sessions as $session) {
            $this->cache[$session->session_id] = array(
                'context' => json_decode($session->context_data, true) ?: array(),
                'tokens' => (int) $session->tokens_used,
                'expires_at' => strtotime($session->expires_at)
            );
        }
    }

    public function get($session_id) {
        if (isset($this->cache[$session_id])) {
            $session = $this->cache[$session_id];

            if ($session['expires_at'] > time()) {
                $this->touch_session($session_id);
                return $session['context'];
            }

            $this->delete($session_id);
        }

        $table = $this->db->prefix . self::CACHE_TABLE;
        $now = current_time('mysql');

        $row = $this->db->get_row($this->db->prepare(
            "SELECT context_data, tokens_used FROM {$table} WHERE session_id = %s AND expires_at > %s",
            $session_id,
            $now
        ));

        if ($row) {
            $context = json_decode($row->context_data, true) ?: array();
            $this->cache[$session_id] = array(
                'context' => $context,
                'tokens' => (int) $row->tokens_used,
                'expires_at' => time() + self::DEFAULT_TTL
            );
            $this->touch_session($session_id);
            return $context;
        }

        return null;
    }

    public function set($session_id, $context, $conversation_id = null) {
        $tokens = $this->estimate_tokens($context);
        $ttl = get_option('ai_agent_session_ttl', self::DEFAULT_TTL);
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        $context_json = json_encode($context, JSON_UNESCAPED_UNICODE);

        $table = $this->db->prefix . self::CACHE_TABLE;

        $existing = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s",
            $session_id
        ));

        if ($existing) {
            $this->db->update(
                $table,
                array(
                    'context_data' => $context_json,
                    'tokens_used' => $tokens,
                    'expires_at' => $expires_at,
                    'last_access' => current_time('mysql')
                ),
                array('session_id' => $session_id)
            );
        } else {
            $this->db->insert(
                $table,
                array(
                    'session_id' => $session_id,
                    'conversation_id' => $conversation_id,
                    'context_data' => $context_json,
                    'tokens_used' => $tokens,
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql')
                )
            );
        }

        $this->cache[$session_id] = array(
            'context' => $context,
            'tokens' => $tokens,
            'expires_at' => time() + $ttl
        );
    }

    public function update_context($session_id, $new_context_items) {
        $current = $this->get($session_id);

        if ($current === null) {
            $current = array();
        }

        $current = array_merge($current, $new_context_items);

        $max_tokens = get_option('ai_agent_max_context_tokens', self::MAX_TOKENS_PER_SESSION);
        $current = $this->trim_context($current, $max_tokens);

        $this->set($session_id, $current);

        return $current;
    }

    public function delete($session_id) {
        unset($this->cache[$session_id]);

        $table = $this->db->prefix . self::CACHE_TABLE;
        $this->db->delete($table, array('session_id' => $session_id));
    }

    public function invalidate($session_id) {
        return $this->delete($session_id);
    }

    public function has_valid_cache($session_id) {
        if (isset($this->cache[$session_id])) {
            return $this->cache[$session_id]['expires_at'] > time();
        }

        $table = $this->db->prefix . self::CACHE_TABLE;
        $now = current_time('mysql');

        $count = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE session_id = %s AND expires_at > %s",
            $session_id,
            $now
        ));

        return $count > 0;
    }

    public function get_cached_context($session_id) {
        return $this->get($session_id);
    }

    public function load_context_from_kb($session_id, $query) {
        $adapter = AI_Agent_KB_Factory::create_from_settings();

        if (!$adapter) {
            return $this->load_context_from_local($session_id, $query);
        }

        $results = $adapter->search($query, 5);

        if (isset($results['error'])) {
            return $this->load_context_from_local($session_id, $query);
        }

        $context = array_map(function($result) {
            return array(
                'title' => $result['title'] ?? '',
                'content' => $result['text'] ?? '',
                'url' => $result['metadata']['url'] ?? '',
                'score' => $result['score'] ?? 0,
                'source' => 'external_kb'
            );
        }, $results);

        $this->set($session_id, $context);

        return $context;
    }

    private function load_context_from_local($session_id, $query) {
        $kb = new AI_Agent_Knowledge_Base();
        $relevant = $kb->find_relevant_context($query, 5);
        $context_text = $kb->format_context_for_llm($relevant);

        $context = array();
        foreach ($relevant as $item) {
            $context[] = array(
                'title' => $item['item']['title'] ?? '',
                'content' => $item['item']['content'] ?? '',
                'url' => $item['item']['url'] ?? '',
                'score' => $item['score'] ?? 0,
                'source' => $item['item']['source'] ?? 'local'
            );
        }

        $this->set($session_id, $context);

        return $context;
    }

    public function refresh_if_needed($session_id, $query) {
        $context = $this->get($session_id);

        if ($context === null) {
            return $this->load_context_from_kb($session_id, $query);
        }

        $tokens = isset($this->cache[$session_id]['tokens']) ? $this->cache[$session_id]['tokens'] : 0;
        $max_tokens = get_option('ai_agent_max_context_tokens', self::MAX_TOKENS_PER_SESSION);

        $tokens_per_query = $this->estimate_tokens_for_query($query);
        if (($tokens + $tokens_per_query) > $max_tokens * 0.8) {
            return $this->load_context_from_kb($session_id, $query);
        }

        return $context;
    }

    private function touch_session($session_id) {
        $table = $this->db->prefix . self::CACHE_TABLE;
        $ttl = get_option('ai_agent_session_ttl', self::DEFAULT_TTL);
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        $this->db->update(
            $table,
            array(
                'last_access' => current_time('mysql'),
                'expires_at' => $expires_at
            ),
            array('session_id' => $session_id)
        );
    }

    private function estimate_tokens($context) {
        if (is_array($context)) {
            $text = json_encode($context);
        } else {
            $text = $context;
        }

        return (int) (strlen($text) / 4);
    }

    private function estimate_tokens_for_query($query) {
        return (int) (strlen($query) / 4);
    }

    private function trim_context($context, $max_tokens) {
        $current_tokens = $this->estimate_tokens($context);

        if ($current_tokens <= $max_tokens) {
            return $context;
        }

        usort($context, function($a, $b) {
            return ($b['score'] ?? 0) - ($a['score'] ?? 0);
        });

        $trimmed = array();
        $total_tokens = 0;

        foreach ($context as $item) {
            $item_tokens = $this->estimate_tokens($item['content'] ?? json_encode($item));

            if (($total_tokens + $item_tokens) <= $max_tokens * 0.9) {
                $trimmed[] = $item;
                $total_tokens += $item_tokens;
            }
        }

        return $trimmed;
    }

    public function cleanup_expired() {
        $table = $this->db->prefix . self::CACHE_TABLE;
        $now = current_time('mysql');

        $deleted = $this->db->query($this->db->prepare(
            "DELETE FROM {$table} WHERE expires_at <= %s",
            $now
        ));

        foreach ($this->cache as $session_id => $data) {
            if ($data['expires_at'] <= time()) {
                unset($this->cache[$session_id]);
            }
        }

        return $deleted;
    }

    public function get_stats() {
        $table = $this->db->prefix . self::CACHE_TABLE;
        $now = current_time('mysql');

        return array(
            'active_sessions' => count($this->cache),
            'total_cached' => (int) $this->db->get_var("SELECT COUNT(*) FROM {$table}"),
            'avg_tokens' => (int) $this->db->get_var("SELECT AVG(tokens_used) FROM {$table} WHERE expires_at > {$this->db->prepare('%s', $now)}"),
            'db_size' => $this->db->get_var("SELECT SUM(LENGTH(context_data)) FROM {$table}")
        );
    }

    public function clear_all() {
        $table = $this->db->prefix . self::CACHE_TABLE;
        $this->db->query("TRUNCATE TABLE {$table}");
        $this->cache = array();
    }
}

class AI_Agent_Session_Manager {
    private $cache;

    public function __construct() {
        $this->cache = AI_Agent_Session_Cache::get_instance();
    }

    public function get_or_create_session($session_id, $conversation_id = null) {
        $context = $this->cache->get($session_id);

        if ($context === null) {
            $context = $this->cache->load_context_from_kb($session_id, '');
        }

        return $context;
    }

    public function update_session($session_id, $context) {
        $this->cache->set($session_id, $context);
    }

    public function add_context_item($session_id, $item) {
        $this->cache->update_context($session_id, array($item));
    }

    public function invalidate_session($session_id) {
        $this->cache->invalidate($session_id);
    }

    public function get_context($session_id) {
        return $this->cache->get($session_id);
    }

    public function get_context_for_query($session_id, $query) {
        return $this->cache->refresh_if_needed($session_id, $query);
    }

    public function format_context_for_llm($context) {
        if (empty($context)) {
            return '';
        }

        $formatted = '';

        foreach ($context as $item) {
            $source = strtoupper($item['source'] ?? 'CONTENT');
            $title = $item['title'] ?? 'Sin título';
            $content = $item['content'] ?? '';

            $formatted .= "\n\n[{$source}] {$title}:\n{$content}";

            if (isset($item['url']) && !empty($item['url'])) {
                $formatted .= "\nFuente: {$item['url']}";
            }
        }

        return $formatted;
    }
}