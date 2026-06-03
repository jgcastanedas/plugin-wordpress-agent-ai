<?php

class AI_Agent_Dashboard {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_dashboard_menu'));
        add_action('wp_ajax_ai_agent_get_dashboard_data', array($this, 'get_dashboard_data'));
    }

    public function add_dashboard_menu() {
        add_submenu_page(
            'ai-agent-chatbot',
            __('Dashboard', 'ai-agent-chatbot'),
            __('Dashboard', 'ai-agent-chatbot'),
            'manage_options',
            'ai-agent-dashboard',
            array($this, 'render_dashboard_page')
        );
    }

    public function render_dashboard_page() {
        $metrics = new AI_Agent_Metrics();
        $summary = $metrics->get_summary();

        $sched = new AI_Agent_Scheduler();
        $scheduler_status = $sched->get_status();

        $index = new AI_Agent_Index();
        $index_stats = $index->get_stats();
        ?>
        <div class="wrap ai-agent-dashboard">
            <h1><?php _e('AI Agent - Dashboard', 'ai-agent-chatbot'); ?></h1>

            <div class="ai-agent-dashboard-grid">
                <div class="ai-agent-stats-card">
                    <h2><?php _e('Resumen General', 'ai-agent-chatbot'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($summary['total_conversations']); ?></span>
                            <span class="stat-label"><?php _e('Conversaciones', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($summary['total_messages']); ?></span>
                            <span class="stat-label"><?php _e('Mensajes', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($summary['total_tokens']); ?></span>
                            <span class="stat-label"><?php _e('Tokens Usados', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">$<?php echo number_format($summary['total_cost_usd'], 4); ?></span>
                            <span class="stat-label"><?php _e('Costo Total (USD)', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item highlight">
                            <span class="stat-value"><?php echo number_format($summary['today_conversations']); ?></span>
                            <span class="stat-label"><?php _e('Hoy', 'ai-agent-chatbot'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="ai-agent-stats-card">
                    <h2><?php _e('Base de Conocimiento', 'ai-agent-chatbot'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $index_stats['total_items']; ?></span>
                            <span class="stat-label"><?php _e('Items Indexados', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $index_stats['pages']; ?></span>
                            <span class="stat-label"><?php _e('Páginas', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $index_stats['documents']; ?></span>
                            <span class="stat-label"><?php _e('Documentos', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $index_stats['products']; ?></span>
                            <span class="stat-label"><?php _e('Productos', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($index_stats['total_tokens_estimate']); ?></span>
                            <span class="stat-label"><?php _e('Tokens Estimados', 'ai-agent-chatbot'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="ai-agent-stats-card">
                    <h2><?php _e('Scheduler', 'ai-agent-chatbot'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $scheduler_status['last_index_run'] ? date('H:i', $scheduler_status['last_index_run']) : 'Nunca'; ?></span>
                            <span class="stat-label"><?php _e('Último Index', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $scheduler_status['last_index_complete'] ? date('H:i', $scheduler_status['last_index_complete']) : 'Nunca'; ?></span>
                            <span class="stat-label"><?php _e('Index Completado', 'ai-agent-chatbot'); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $scheduler_status['next_scheduled'] ? date('H:i', $scheduler_status['next_scheduled']) : 'N/A'; ?></span>
                            <span class="stat-label"><?php _e('Próximo', 'ai-agent-chatbot'); ?></span>
                        </div>
                    </div>
                    <button class="button" id="ai_agent_force_reindex"><?php _e('Forzar Re-index', 'ai-agent-chatbot'); ?></button>
                </div>
            </div>

            <div class="ai-agent-dashboard-section">
                <h2><?php _e('Métricas Diarias (Últimos 30 días)', 'ai-agent-chatbot'); ?></h2>
                <div id="ai-agent-chart-container" style="height: 300px; background: #f8f9fa; border-radius: 8px; padding: 20px;">
                    <canvas id="ai-agent-metrics-chart"></canvas>
                </div>
            </div>

            <div class="ai-agent-dashboard-grid">
                <div class="ai-agent-stats-card">
                    <h2><?php _e('Consumo por Modelo', 'ai-agent-chatbot'); ?></h2>
                    <table class="widefat" id="ai-agent-model-usage">
                        <thead>
                            <tr>
                                <th><?php _e('Modelo', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Tokens', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Costo (USD)', 'ai-agent-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ai-agent-model-usage-body">
                            <tr><td colspan="3"><?php _e('Cargando...', 'ai-agent-chatbot'); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="ai-agent-stats-card">
                    <h2><?php _e('Conversaciones Recientes', 'ai-agent-chatbot'); ?></h2>
                    <?php
                    $conv = new AI_Agent_Conversation();
                    $recent = $conv->get_all(array('per_page' => 5, 'orderby' => 'last_activity_at'));
                    ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Teléfono', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Rol', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Mensajes', 'ai-agent-chatbot'); ?></th>
                                <th><?php _e('Última Actividad', 'ai-agent-chatbot'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)) : ?>
                                <tr><td colspan="5"><?php _e('No hay conversaciones aún', 'ai-agent-chatbot'); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ($recent as $r) : ?>
                                    <tr>
                                        <td><?php echo esc_html($r->session_id); ?></td>
                                        <td><?php echo esc_html($r->phone ?: '-'); ?></td>
                                        <td><?php echo esc_html($r->agent_role); ?></td>
                                        <td><?php echo esc_html($r->total_messages); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($r->last_activity_at), current_time('timestamp')) . ' atrás'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p><a href="<?php echo admin_url('admin.php?page=ai-agent-conversations'); ?>" class="button"><?php _e('Ver Todas', 'ai-agent-chatbot'); ?></a></p>
                </div>
            </div>
        </div>

        <style>
        .ai-agent-dashboard {
            max-width: 1400px;
        }
        .ai-agent-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .ai-agent-stats-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .ai-agent-stats-card h2 {
            margin-top: 0;
            font-size: 18px;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
            padding: 15px 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-item.highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-item.highlight .stat-label {
            color: rgba(255,255,255,0.8);
        }
        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .ai-agent-dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .ai-agent-dashboard-section h2 {
            margin-top: 0;
        }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        jQuery(document).ready(function($) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_agent_get_dashboard_data',
                    nonce: '<?php echo wp_create_nonce('ai_agent_admin'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        renderMetricsChart(response.data.daily_metrics);
                        renderModelUsage(response.data.token_usage);
                    }
                }
            });

            function renderMetricsChart(data) {
                if (typeof Chart === 'undefined') {
                    console.log('Chart.js not loaded');
                    return;
                }

                const ctx = document.getElementById('ai-agent-metrics-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.date),
                        datasets: [{
                            label: 'Conversaciones',
                            data: data.map(d => d.total_conversations),
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Costo USD',
                            data: data.map(d => d.total_cost_usd * 100),
                            borderColor: '#764ba2',
                            backgroundColor: 'rgba(118, 75, 162, 0.1)',
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Conversaciones' }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: { display: true, text: 'Costo (cents)' }
                            }
                        }
                    }
                });
            }

            function renderModelUsage(data) {
                const tbody = $('#ai-agent-model-usage-body');
                tbody.empty();

                if (data.length === 0) {
                    tbody.html('<tr><td colspan="3">Sin datos de uso</td></tr>');
                    return;
                }

                data.forEach(function(row) {
                    tbody.append(
                        '<tr>' +
                        '<td>' + row.model + '</td>' +
                        '<td>' + row.total_tokens.toLocaleString() + '</td>' +
                        '<td>$' + parseFloat(row.total_cost).toFixed(4) + '</td>' +
                        '</tr>'
                    );
                });
            }

            $('#ai_agent_force_reindex').on('click', function() {
                if (confirm('¿Forzar re-indexación? Esto procesará todas las páginas y productos seleccionados.')) {
                    $.post(ajaxurl, {
                        action: 'ai_agent_force_reindex',
                        nonce: '<?php echo wp_create_nonce('ai_agent_admin'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('Re-indexación completada: ' + response.data.indexed + ' items');
                            location.reload();
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    public function get_dashboard_data() {
        check_ajax_referer('ai_agent_admin', 'nonce');

        $metrics = new AI_Agent_Metrics();

        $daily_metrics = $metrics->get_daily_metrics(30);
        $token_usage = $metrics->get_token_usage_by_model(30);

        $conv = new AI_Agent_Conversation();
        $recent = $conv->get_all(array('per_page' => 10));

        wp_send_json_success(array(
            'daily_metrics' => $daily_metrics,
            'token_usage' => $token_usage,
            'recent_conversations' => $recent
        ));
    }
}

add_action('wp_ajax_ai_agent_get_dashboard_data', array('AI_Agent_Dashboard', 'get_dashboard_data'));

add_action('wp_ajax_ai_agent_force_reindex', function() {
    check_ajax_referer('ai_agent_admin', 'nonce');

    $scheduler = new AI_Agent_Scheduler();
    $scheduler->force_reindex();

    $index = new AI_Agent_Index();
    $stats = $index->get_stats();

    wp_send_json_success(array(
        'indexed' => $stats['total_items'],
        'message' => 'Re-indexación completada'
    ));
});

add_action('wp_ajax_ai_agent_test_kb_connection', function() {
    check_ajax_referer('ai_agent_admin', 'nonce');

    $type = sanitize_text_field($_POST['type'] ?? 'local');
    $host = esc_url_raw($_POST['host'] ?? '');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    $index_name = sanitize_text_field($_POST['index_name'] ?? 'ai-agent-kb');

    if ($type === 'local') {
        wp_send_json_success(array('vectors' => 0, 'message' => 'Usando base local'));
    }

    $config = array(
        'host' => $host,
        'api_key' => $api_key,
        'index_name' => $index_name
    );

    $adapter = AI_Agent_KB_Factory::create($type, $config);

    if (!$adapter) {
        wp_send_json_error('Adapter no disponible para el tipo: ' . $type);
    }

    $result = $adapter->connect();

    if ($result) {
        $stats = $adapter->get_stats();
        $adapter->disconnect();
        wp_send_json_success(array(
            'vectors' => $stats['total_vectors'] ?? 0,
            'message' => 'Conexión exitosa'
        ));
    } else {
        wp_send_json_error('No se pudo conectar al servicio');
    }
});