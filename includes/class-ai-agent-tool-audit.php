<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log de auditoría para llamadas a WC Tools (estado de pedido, crear cliente, etc.).
 * Permite ver qué hizo el agente, con qué argumentos y qué devolvió WooCommerce.
 */
class AI_Agent_Tool_Audit {

    const TABLE = 'ai_agent_tool_audit';

    public static function init_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED DEFAULT NULL,
            session_id VARCHAR(255) DEFAULT NULL,
            tool_name VARCHAR(64) NOT NULL,
            args LONGTEXT DEFAULT NULL,
            success TINYINT(1) DEFAULT 0,
            result_message TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tool (tool_name),
            KEY idx_conversation (conversation_id),
            KEY idx_date (executed_at)
        ) {$wpdb->get_charset_collate()};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function log($tool_name, $args, $result, $conversation = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $sanitized_args = self::sanitize_args($args);

        $wpdb->insert($table, array(
            'conversation_id' => $conversation && isset($conversation->id) ? (int) $conversation->id : null,
            'session_id'      => $conversation && isset($conversation->session_id) ? $conversation->session_id : null,
            'tool_name'       => sanitize_text_field($tool_name),
            'args'            => wp_json_encode($sanitized_args, JSON_UNESCAPED_UNICODE),
            'success'         => !empty($result['success']) ? 1 : 0,
            'result_message'  => isset($result['message']) ? wp_strip_all_tags($result['message']) : '',
            'ip_address'      => isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : null,
        ));
    }

    /**
     * Redacta campos sensibles (passwords, tokens) antes de guardar.
     */
    private static function sanitize_args($args) {
        if (!is_array($args)) {
            return $args;
        }

        $sensitive = array('password', 'api_key', 'token', 'secret', 'auth_token');
        foreach ($args as $k => $v) {
            if (in_array(strtolower($k), $sensitive, true)) {
                $args[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $args[$k] = self::sanitize_args($v);
            }
        }
        return $args;
    }

    public static function get_recent($limit = 100, $tool_filter = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = max(1, min(500, (int) $limit));

        if ($tool_filter) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE tool_name = %s ORDER BY executed_at DESC LIMIT %d",
                $tool_filter,
                $limit
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY executed_at DESC LIMIT %d",
            $limit
        ));
    }

    public static function count_by_tool($days = 7) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT tool_name, COUNT(*) as total, SUM(success) as successful
             FROM {$table}
             WHERE executed_at >= %s
             GROUP BY tool_name
             ORDER BY total DESC",
            $since
        ));
    }

    public static function purge_old($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE executed_at < %s",
            $cutoff
        ));
    }
}

/**
 * Página admin para inspeccionar el log.
 */
class AI_Agent_Tool_Audit_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public function add_menu() {
        add_submenu_page(
            'ai-agent-chatbot',
            __('Auditoría', 'ai-agent-chatbot'),
            __('Auditoría', 'ai-agent-chatbot'),
            'manage_options',
            'ai-agent-audit',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tool_filter = isset($_GET['tool']) ? sanitize_text_field(wp_unslash($_GET['tool'])) : null;
        $rows = AI_Agent_Tool_Audit::get_recent(100, $tool_filter);
        $summary = AI_Agent_Tool_Audit::count_by_tool(7);
        ?>
        <div class="wrap">
            <h1><?php _e('AI Agent - Auditoría de Tools', 'ai-agent-chatbot'); ?></h1>

            <h2><?php _e('Últimos 7 días', 'ai-agent-chatbot'); ?></h2>
            <table class="widefat striped" style="max-width:600px;">
                <thead><tr>
                    <th><?php _e('Tool', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Total', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('OK', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Errores', 'ai-agent-chatbot'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (empty($summary)) : ?>
                    <tr><td colspan="4"><?php _e('Sin actividad reciente.', 'ai-agent-chatbot'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($summary as $row) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url(add_query_arg('tool', $row->tool_name)); ?>"><?php echo esc_html($row->tool_name); ?></a></td>
                            <td><?php echo (int) $row->total; ?></td>
                            <td><?php echo (int) $row->successful; ?></td>
                            <td><?php echo (int) $row->total - (int) $row->successful; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top:30px;">
                <?php _e('Últimas 100 llamadas', 'ai-agent-chatbot'); ?>
                <?php if ($tool_filter) : ?>
                    <span style="font-size:14px; font-weight:normal;">
                        — filtrando por <code><?php echo esc_html($tool_filter); ?></code>
                        <a href="<?php echo esc_url(remove_query_arg('tool')); ?>">[quitar filtro]</a>
                    </span>
                <?php endif; ?>
            </h2>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php _e('Fecha', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Tool', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Args', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('OK', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Resultado', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('Sesión', 'ai-agent-chatbot'); ?></th>
                    <th><?php _e('IP', 'ai-agent-chatbot'); ?></th>
                </tr></thead>
                <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="7"><?php _e('Sin registros.', 'ai-agent-chatbot'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->executed_at); ?></td>
                            <td><code><?php echo esc_html($row->tool_name); ?></code></td>
                            <td style="max-width:300px;"><pre style="white-space:pre-wrap; margin:0; font-size:11px;"><?php echo esc_html($row->args); ?></pre></td>
                            <td><?php echo $row->success ? '✅' : '❌'; ?></td>
                            <td style="max-width:300px;"><?php echo esc_html($row->result_message); ?></td>
                            <td><?php echo esc_html(substr((string) $row->session_id, 0, 20)); ?></td>
                            <td><?php echo esc_html($row->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
