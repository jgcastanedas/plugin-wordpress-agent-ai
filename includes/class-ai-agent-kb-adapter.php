<?php

if (!defined('ABSPATH')) {
    exit;
}

interface AI_Agent_KB_Adapter_Interface {
    public function connect();
    public function disconnect();
    public function is_connected();
    public function search($query, $top_k = 5);
    public function upsert($id, $embedding, $metadata);
    public function delete($id);
    public function sync_content($items);
    public function get_stats();
}

abstract class AI_Agent_KB_Adapter implements AI_Agent_KB_Adapter_Interface {
    protected $config;
    protected $connected = false;

    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, array(
            'host' => '',
            'api_key' => '',
            'index_name' => 'ai-agent-kb',
            'dimension' => 1536,
            'metric' => 'cosine'
        ));
    }

    abstract protected function do_connect();

    public function connect() {
        if ($this->is_connected()) {
            return true;
        }
        return $this->do_connect();
    }

    public function disconnect() {
        $this->connected = false;
    }

    public function is_connected() {
        return $this->connected;
    }

    protected function log_error($method, $message) {
        error_log("AI Agent KB ({$method}): {$message}");
    }
}

class AI_Agent_KB_Pinecone extends AI_Agent_KB_Adapter {
    private $environment;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->environment = $config['environment'] ?? 'us-east-1';
    }

    protected function do_connect() {
        if (empty($this->config['host']) || empty($this->config['api_key'])) {
            $this->log_error('Pinecone', 'Missing host or API key');
            return false;
        }

        $response = wp_remote_get($this->config['host'] . '/describe', array(
            'headers' => array(
                'Api-Key' => $this->config['api_key']
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $this->log_error('Pinecone', $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $this->connected = true;
            return true;
        }

        $this->log_error('Pinecone', 'Connection failed with code: ' . $code);
        return false;
    }

    public function search($query, $top_k = 5) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $embedding = $this->create_embedding($query);
        if (!$embedding) {
            return array('error' => 'Failed to create embedding');
        }

        $payload = array(
            'vector' => $embedding,
            'topK' => $top_k,
            'includeMetadata' => true
        );

        $url = $this->config['host'] . '/query';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Api-Key' => $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['matches'])) {
            return array_map(function($match) {
                return array(
                    'id' => $match['id'],
                    'score' => $match['score'],
                    'metadata' => $match['metadata'] ?? array(),
                    'text' => $match['metadata']['text'] ?? '',
                    'title' => $match['metadata']['title'] ?? ''
                );
            }, $body['matches']);
        }

        return array('error' => 'No results found');
    }

    public function upsert($id, $embedding, $metadata) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $payload = array(
            'vectors' => array(array(
                'id' => $id,
                'values' => $embedding,
                'metadata' => $metadata
            ))
        );

        $url = $this->config['host'] . '/vectors/upsert';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Api-Key' => $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        return array('success' => true);
    }

    public function delete($id) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $payload = array(
            'ids' => array($id)
        );

        $url = $this->config['host'] . '/vectors/delete';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Api-Key' => $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        return array('success' => true);
    }

    public function sync_content($items) {
        $vectors = array();
        $kb = new AI_Agent_Knowledge_Base();

        foreach ($items as $item) {
            $text = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : $item;
            $embedding = $kb->create_embeddings($text);

            if ($embedding) {
                $vectors[] = array(
                    'id' => $item['id'] ?? uniqid('vec_'),
                    'values' => $embedding,
                    'metadata' => array(
                        'title' => $item['title'] ?? '',
                        'text' => substr($text, 0, 10000),
                        'url' => $item['url'] ?? '',
                        'source' => $item['source'] ?? 'wordpress',
                        'created' => current_time('mysql')
                    )
                );
            }
        }

        if (empty($vectors)) {
            return array('success' => false, 'error' => 'No vectors to sync');
        }

        $payload = array('vectors' => $vectors);
        $url = $this->config['host'] . '/vectors/upsert';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Api-Key' => $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        return array('success' => true, 'count' => count($vectors));
    }

    public function get_stats() {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $response = wp_remote_get($this->config['host'] . '/describe', array(
            'headers' => array('Api-Key' => $this->config['api_key']),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array(
            'total_vectors' => $body['totalVectorCount'] ?? 0,
            'dimension' => $body['dimension'] ?? 0,
            'status' => $body['status'] ?? 'unknown'
        );
    }

    private function create_embedding($text) {
        $kb = new AI_Agent_Knowledge_Base();
        return $kb->create_embeddings($text);
    }

    public static function test_connection($config) {
        $adapter = new self($config);
        $result = $adapter->connect();
        $adapter->disconnect();
        return $result;
    }
}

class AI_Agent_KB_PostgreSQL extends AI_Agent_KB_Adapter {
    private $connection = null;

    protected function do_connect() {
        if (!function_exists('pg_connect')) {
            $this->log_error('PostgreSQL', 'pg_connect not available');
            return false;
        }

        $host = $this->config['host'];
        $port = $this->config['port'] ?? '5432';
        $dbname = $this->config['database'] ?? 'ai_agent';
        $user = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        $sslmode = $this->config['sslmode'] ?? 'require';

        $params = array(
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $dbname,
            'user'     => $user,
            'password' => $password,
            'sslmode'  => $sslmode,
        );

        $parts = array();
        foreach ($params as $k => $v) {
            $v = str_replace(array("\\", "'"), array("\\\\", "\\'"), (string) $v);
            $parts[] = $k . "='" . $v . "'";
        }
        $conn_string = implode(' ', $parts);

        $this->connection = @pg_connect($conn_string);

        if (!$this->connection) {
            $this->log_error('PostgreSQL', 'Connection failed');
            return false;
        }

        $this->connected = true;
        $this->init_schema();
        return true;
    }

    private function init_schema() {
        $sql = "
        CREATE TABLE IF NOT EXISTS ai_agent_knowledge (
            id SERIAL PRIMARY KEY,
            source_type VARCHAR(50) NOT NULL,
            source_id BIGINT DEFAULT NULL,
            title VARCHAR(500) NOT NULL,
            content TEXT NOT NULL,
            url VARCHAR(1000) DEFAULT NULL,
            embedding vector(1536),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS ai_agent_knowledge_embedding_idx ON ai_agent_knowledge USING ivfflat (embedding vector_cosine_ops);
        CREATE INDEX IF NOT EXISTS ai_agent_knowledge_source_idx ON ai_agent_knowledge(source_type, source_id);
        ";

        pg_query($this->connection, $sql);
    }

    public function search($query, $top_k = 5) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $kb = new AI_Agent_Knowledge_Base();
        $embedding = $kb->create_embeddings($query);

        if (!$embedding) {
            return array('error' => 'Failed to create embedding');
        }

        $embedding_clean = array_map('floatval', $embedding);
        $embedding_str = '[' . implode(',', $embedding_clean) . ']';
        $top_k = max(1, min(100, intval($top_k)));

        $sql = "SELECT id, source_type, source_id, title, content, url,
                1 - (embedding <=> $1::vector) as similarity
                FROM ai_agent_knowledge
                ORDER BY embedding <=> $1::vector
                LIMIT $2";

        $result = pg_query_params($this->connection, $sql, array($embedding_str, $top_k));

        if (!$result) {
            return array('error' => pg_last_error($this->connection));
        }

        $results = array();
        while ($row = pg_fetch_assoc($result)) {
            $results[] = array(
                'id' => $row['id'],
                'score' => (float) $row['similarity'],
                'metadata' => array(
                    'source_type' => $row['source_type'],
                    'source_id' => $row['source_id'],
                    'url' => $row['url']
                ),
                'title' => $row['title'],
                'text' => $row['content']
            );
        }

        return $results;
    }

    public function upsert($id, $embedding, $metadata) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $embedding_clean = array_map('floatval', $embedding);
        $embedding_str = '[' . implode(',', $embedding_clean) . ']';

        $sql = "INSERT INTO ai_agent_knowledge (title, content, url, embedding, source_type)
                VALUES ($1, $2, $3, $4::vector, $5)
                ON CONFLICT DO NOTHING";

        $result = pg_query_params($this->connection, $sql, array(
            $metadata['title'] ?? '',
            $metadata['text'] ?? '',
            $metadata['url'] ?? '',
            $embedding_str,
            $metadata['source'] ?? 'wordpress',
        ));

        return array('success' => $result !== false);
    }

    public function delete($id) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $sql = "DELETE FROM ai_agent_knowledge WHERE id = $1";
        $result = pg_query_params($this->connection, $sql, array(intval($id)));

        return array('success' => $result !== false);
    }

    public function sync_content($items) {
        $count = 0;
        foreach ($items as $item) {
            $text = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : $item;
            $kb = new AI_Agent_Knowledge_Base();
            $embedding = $kb->create_embeddings($text);

            if ($embedding) {
                $result = $this->upsert($item['id'] ?? uniqid('vec_'), $embedding, $item);
                if ($result['success']) {
                    $count++;
                }
            }
        }

        return array('success' => true, 'count' => $count);
    }

    public function get_stats() {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $result = pg_query($this->connection, "SELECT COUNT(*) as total FROM ai_agent_knowledge");
        $row = pg_fetch_assoc($result);

        return array('total_vectors' => $row['total'] ?? 0);
    }

    public function disconnect() {
        if ($this->connection) {
            pg_close($this->connection);
            $this->connection = null;
        }
        parent::disconnect();
    }

    public static function test_connection($config) {
        $adapter = new self($config);
        $result = $adapter->connect();
        $adapter->disconnect();
        return $result;
    }
}

class AI_Agent_KB_Supabase extends AI_Agent_KB_Adapter {
    private $project_id;

    public function __construct($config = array()) {
        parent::__construct($config);
        $this->project_id = $config['project_id'] ?? '';
    }

    protected function do_connect() {
        if (empty($this->config['api_key']) || empty($this->project_id)) {
            $this->log_error('Supabase', 'Missing API key or project ID');
            return false;
        }

        $response = wp_remote_get(
            "https://{$this->project_id}.supabase.co/rest/v1/",
            array(
                'headers' => array(
                    'apikey' => $this->config['api_key'],
                    'Authorization' => 'Bearer ' . $this->config['api_key']
                ),
                'timeout' => 10
            )
        );

        if (is_wp_error($response)) {
            $this->log_error('Supabase', $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $this->connected = true;
            return true;
        }

        return false;
    }

    public function search($query, $top_k = 5) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $kb = new AI_Agent_Knowledge_Base();
        $embedding = $kb->create_embeddings($query);

        if (!$embedding) {
            return array('error' => 'Failed to create embedding');
        }

        $embedding_b64 = base64_encode(json_encode($embedding));

        $response = wp_remote_post(
            "https://{$this->project_id}.supabase.co/rest/v1/rpc/match_knowledge",
            array(
                'headers' => array(
                    'apikey' => $this->config['api_key'],
                    'Authorization' => 'Bearer ' . $this->config['api_key'],
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation'
                ),
                'body' => json_encode(array(
                    'query_embedding' => $embedding_b64,
                    'match_threshold' => 0.7,
                    'match_count' => $top_k
                )),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (is_array($body)) {
            return array_map(function($item) {
                return array(
                    'id' => $item['id'],
                    'score' => $item['similarity'] ?? 0,
                    'metadata' => array(
                        'source_type' => $item['source_type'] ?? '',
                        'url' => $item['url'] ?? ''
                    ),
                    'title' => $item['title'] ?? '',
                    'text' => $item['content'] ?? ''
                );
            }, $body);
        }

        return array('error' => 'No results');
    }

    public function upsert($id, $embedding, $metadata) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $embedding_b64 = base64_encode(json_encode($embedding));

        $response = wp_remote_post(
            "https://{$this->project_id}.supabase.co/rest/v1/ai_agent_knowledge",
            array(
                'headers' => array(
                    'apikey' => $this->config['api_key'],
                    'Authorization' => 'Bearer ' . $this->config['api_key'],
                    'Content-Type' => 'application/json',
                    'Prefer' => 'resolution=merge-duplicates'
                ),
                'body' => json_encode(array(
                    'id' => $id,
                    'title' => $metadata['title'] ?? '',
                    'content' => $metadata['text'] ?? '',
                    'url' => $metadata['url'] ?? '',
                    'source_type' => $metadata['source'] ?? 'wordpress',
                    'embedding' => $embedding_b64
                )),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        return array('success' => true);
    }

    public function delete($id) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $response = wp_remote_request(
            "https://{$this->project_id}.supabase.co/rest/v1/ai_agent_knowledge?id=eq.{$id}",
            array(
                'method' => 'DELETE',
                'headers' => array(
                    'apikey' => $this->config['api_key'],
                    'Authorization' => 'Bearer ' . $this->config['api_key']
                ),
                'timeout' => 30
            )
        );

        return array('success' => !is_wp_error($response));
    }

    public function sync_content($items) {
        $kb = new AI_Agent_Knowledge_Base();
        $count = 0;

        foreach ($items as $item) {
            $text = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : $item;
            $embedding = $kb->create_embeddings($text);

            if ($embedding) {
                $result = $this->upsert($item['id'] ?? uniqid('vec_'), $embedding, $item);
                if ($result['success']) {
                    $count++;
                }
            }
        }

        return array('success' => true, 'count' => $count);
    }

    public function get_stats() {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $response = wp_remote_get(
            "https://{$this->project_id}.supabase.co/rest/v1/ai_agent_knowledge?select=id",
            array(
                'headers' => array(
                    'apikey' => $this->config['api_key'],
                    'Authorization' => 'Bearer ' . $this->config['api_key']
                ),
                'timeout' => 10
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return array('total_vectors' => is_array($body) ? count($body) : 0);
    }

    public static function test_connection($config) {
        $adapter = new self($config);
        $result = $adapter->connect();
        $adapter->disconnect();
        return $result;
    }
}

class AI_Agent_KB_Custom_API extends AI_Agent_KB_Adapter {
    protected function do_connect() {
        if (empty($this->config['host'])) {
            $this->log_error('Custom API', 'Missing host URL');
            return false;
        }

        $response = wp_remote_get($this->config['host'], array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['api_key']
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $this->connected = ($code >= 200 && $code < 300);
        return $this->connected;
    }

    public function search($query, $top_k = 5) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $kb = new AI_Agent_Knowledge_Base();
        $embedding = $kb->create_embeddings($query);

        if (!$embedding) {
            return array('error' => 'Failed to create embedding');
        }

        $payload = apply_filters('ai_agent_custom_kb_search_payload', array(
            'query' => $query,
            'embedding' => $embedding,
            'top_k' => $top_k
        ), $this->config);

        $response = wp_remote_post($this->config['host'] . '/search', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return apply_filters('ai_agent_custom_kb_search_response', $body, $this->config);
    }

    public function upsert($id, $embedding, $metadata) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $payload = apply_filters('ai_agent_custom_kb_upsert_payload', array(
            'id' => $id,
            'embedding' => $embedding,
            'metadata' => $metadata
        ), $this->config);

        $response = wp_remote_post($this->config['host'] . '/upsert', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        return array('success' => !is_wp_error($response));
    }

    public function delete($id) {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $response = wp_remote_request($this->config['host'] . '/delete/' . urlencode($id), array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['api_key']
            ),
            'timeout' => 30
        ));

        return array('success' => !is_wp_error($response));
    }

    public function sync_content($items) {
        $count = 0;
        foreach ($items as $item) {
            $text = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : $item;
            $kb = new AI_Agent_Knowledge_Base();
            $embedding = $kb->create_embeddings($text);

            if ($embedding) {
                $result = $this->upsert($item['id'] ?? uniqid('vec_'), $embedding, $item);
                if ($result['success']) {
                    $count++;
                }
            }
        }

        return array('success' => true, 'count' => $count);
    }

    public function get_stats() {
        if (!$this->is_connected()) {
            $this->connect();
        }

        $response = wp_remote_get($this->config['host'] . '/stats', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->config['api_key']
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array();
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: array();
    }

    public static function test_connection($config) {
        $adapter = new self($config);
        $result = $adapter->connect();
        $adapter->disconnect();
        return $result;
    }
}

class AI_Agent_KB_Factory {
    public static function create($type, $config = array()) {
        switch ($type) {
            case 'pinecone':
                return new AI_Agent_KB_Pinecone($config);
            case 'postgresql':
                return new AI_Agent_KB_PostgreSQL($config);
            case 'supabase':
                return new AI_Agent_KB_Supabase($config);
            case 'custom':
                return new AI_Agent_KB_Custom_API($config);
            default:
                return null;
        }
    }

    public static function create_from_settings() {
        $type = get_option('ai_agent_kb_type', 'local');

        if ($type === 'local') {
            return null;
        }

        $config = array(
            'host' => get_option('ai_agent_kb_host', ''),
            'api_key' => get_option('ai_agent_kb_api_key', ''),
            'index_name' => get_option('ai_agent_kb_index_name', 'ai-agent-kb'),
            'project_id' => get_option('ai_agent_kb_supabase_project', ''),
            'database' => get_option('ai_agent_kb_postgres_db', ''),
            'username' => get_option('ai_agent_kb_postgres_user', ''),
            'password' => get_option('ai_agent_kb_postgres_password', ''),
            'port' => get_option('ai_agent_kb_postgres_port', '5432'),
            'environment' => get_option('ai_agent_kb_pinecone_env', 'us-east-1')
        );

        return self::create($type, $config);
    }

    public static function get_available_types() {
        return array(
            'local' => 'Local (MySQL del WordPress)',
            'pinecone' => 'Pinecone (Vector Database)',
            'postgresql' => 'PostgreSQL + pgvector',
            'supabase' => 'Supabase (PostgreSQL + API)',
            'custom' => 'Custom API (Otro servicio)'
        );
    }
}