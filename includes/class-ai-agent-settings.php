<?php

class AI_Agent_Settings {
    private $options_group = 'ai_agent_options';
    private $options_page = 'ai_agent_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
    }

    public function enqueue_color_picker($hook) {
        if (strpos($hook, 'ai-agent') === false) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    public function add_admin_menu() {
        add_menu_page(
            __('AI Agent Chatbot', 'ai-agent-chatbot'),
            __('AI Agent', 'ai-agent-chatbot'),
            'manage_options',
            'ai-agent-chatbot',
            array($this, 'render_settings_page'),
            'dashicons-admin-generic',
            30
        );
    }

    public function register_settings() {
        register_setting($this->options_group, 'ai_agent_llm_provider', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_openai_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_anthropic_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_ollama_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting($this->options_group, 'ai_agent_ollama_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_selected_pages', array('sanitize_callback' => array($this, 'sanitize_array')));
        register_setting($this->options_group, 'ai_agent_webhook_secret', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_embedding_provider', array('sanitize_callback' => 'sanitize_text_field'));

        register_setting($this->options_group, 'ai_agent_widget_enabled', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_widget_position', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_widget_width', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_widget_height', array('sanitize_callback' => 'sanitize_text_field'));

        register_setting($this->options_group, 'ai_agent_logo_type', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_custom_logo', array('sanitize_callback' => 'esc_url_raw'));

        register_setting($this->options_group, 'ai_agent_primary_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_secondary_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_button_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_button_icon_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_user_bubble_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_bot_bubble_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_user_text_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->options_group, 'ai_agent_bot_text_color', array('sanitize_callback' => 'sanitize_hex_color'));

        register_setting($this->options_group, 'ai_agent_font_family', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_font_size', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_header_title', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_welcome_message', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting($this->options_group, 'ai_agent_input_placeholder', array('sanitize_callback' => 'sanitize_text_field'));

        register_setting($this->options_group, 'ai_agent_chat_border_radius', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_button_size', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_message_spacing', array('sanitize_callback' => 'sanitize_text_field'));

        register_setting($this->options_group, 'ai_agent_default_role', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_allow_checkout', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_greeting_primary', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting($this->options_group, 'ai_agent_offline_message', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting($this->options_group, 'ai_agent_business_hours_enabled', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_business_hours', array('sanitize_callback' => array($this, 'sanitize_business_hours')));
        register_setting($this->options_group, 'ai_agent_business_hours_display', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_campaign_messages', array('sanitize_callback' => array($this, 'sanitize_campaigns')));

        register_setting($this->options_group, 'ai_agent_token_limit_warning', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_max_context_tokens', array('sanitize_callback' => 'sanitize_text_field'));

        register_setting($this->options_group, 'ai_agent_kb_type', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_kb_host', array('sanitize_callback' => 'esc_url_raw'));
        register_setting($this->options_group, 'ai_agent_kb_api_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_kb_index_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_kb_sync_enabled', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_kb_sync_interval', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->options_group, 'ai_agent_kb_fallback_local', array('sanitize_callback' => 'sanitize_text_field'));

        add_settings_section('llm_settings', __('Configuración de LLM', 'ai-agent-chatbot'), array($this, 'llm_section_callback'), $this->options_page);
        add_settings_section('knowledge_settings', __('Base de Conocimiento', 'ai-agent-chatbot'), array($this, 'knowledge_section_callback'), $this->options_page);
        add_settings_section('webhook_settings', __('Configuración de Webhook', 'ai-agent-chatbot'), array($this, 'webhook_section_callback'), $this->options_page);
        add_settings_section('agent_behavior', __('Comportamiento del Agente', 'ai-agent-chatbot'), array($this, 'agent_behavior_section_callback'), $this->options_page);
        add_settings_section('business_hours_settings', __('Horarios de Atención', 'ai-agent-chatbot'), array($this, 'business_hours_section_callback'), $this->options_page);
        add_settings_section('campaigns_settings', __('Campañas y Promociones', 'ai-agent-chatbot'), array($this, 'campaigns_section_callback'), $this->options_page);
        add_settings_section('widget_appearance', __('Apariencia del Widget', 'ai-agent-chatbot'), array($this, 'widget_appearance_section_callback'), $this->options_page);
        add_settings_section('widget_colors', __('Colores', 'ai-agent-chatbot'), array($this, 'widget_colors_section_callback'), $this->options_page);
        add_settings_section('widget_typography', __('Tipografía', 'ai-agent-chatbot'), array($this, 'widget_typography_section_callback'), $this->options_page);
        add_settings_section('widget_dimensions', __('Dimensiones', 'ai-agent-chatbot'), array($this, 'widget_dimensions_section_callback'), $this->options_page);
        add_settings_section('kb_external_settings', __('Base de Conocimiento Externa', 'ai-agent-chatbot'), array($this, 'kb_external_section_callback'), $this->options_page);

        add_settings_field('ai_agent_llm_provider', __('Proveedor de LLM', 'ai-agent-chatbot'), array($this, 'render_llm_provider_field'), $this->options_page, 'llm_settings');
        add_settings_field('ai_agent_openai_key', __('OpenAI API Key', 'ai-agent-chatbot'), array($this, 'render_openai_key_field'), $this->options_page, 'llm_settings');
        add_settings_field('ai_agent_anthropic_key', __('Anthropic API Key', 'ai-agent-chatbot'), array($this, 'render_anthropic_key_field'), $this->options_page, 'llm_settings');
        add_settings_field('ai_agent_ollama_url', __('URL de Ollama', 'ai-agent-chatbot'), array($this, 'render_ollama_url_field'), $this->options_page, 'llm_settings');
        add_settings_field('ai_agent_ollama_model', __('Modelo de Ollama', 'ai-agent-chatbot'), array($this, 'render_ollama_model_field'), $this->options_page, 'llm_settings');

        add_settings_field('ai_agent_selected_pages', __('Páginas para Base de Conocimiento', 'ai-agent-chatbot'), array($this, 'render_selected_pages_field'), $this->options_page, 'knowledge_settings');
        add_settings_field('ai_agent_embedding_provider', __('Proveedor de Embeddings', 'ai-agent-chatbot'), array($this, 'render_embedding_provider_field'), $this->options_page, 'knowledge_settings');
        add_settings_field('ai_agent_token_limit_warning', __('Límite de Tokens para Warning', 'ai-agent-chatbot'), array($this, 'render_token_limit_warning_field'), $this->options_page, 'knowledge_settings');
        add_settings_field('ai_agent_max_context_tokens', __('Máximo Tokens en Contexto', 'ai-agent-chatbot'), array($this, 'render_max_context_tokens_field'), $this->options_page, 'knowledge_settings');

        add_settings_field('ai_agent_webhook_secret', __('Secret para Webhook', 'ai-agent-chatbot'), array($this, 'render_webhook_secret_field'), $this->options_page, 'webhook_settings');

        add_settings_field('ai_agent_default_role', __('Rol del Agente', 'ai-agent-chatbot'), array($this, 'render_default_role_field'), $this->options_page, 'agent_behavior');
        add_settings_field('ai_agent_allow_checkout', __('Permitir Checkout', 'ai-agent-chatbot'), array($this, 'render_allow_checkout_field'), $this->options_page, 'agent_behavior');
        add_settings_field('ai_agent_greeting_primary', __('Mensaje de Saludo', 'ai-agent-chatbot'), array($this, 'render_greeting_primary_field'), $this->options_page, 'agent_behavior');
        add_settings_field('ai_agent_offline_message', __('Mensaje Fuera de Horario', 'ai-agent-chatbot'), array($this, 'render_offline_message_field'), $this->options_page, 'agent_behavior');

        add_settings_field('ai_agent_business_hours_enabled', __('Activar Horarios', 'ai-agent-chatbot'), array($this, 'render_business_hours_enabled_field'), $this->options_page, 'business_hours_settings');
        add_settings_field('ai_agent_business_hours', __('Configuración de Horarios', 'ai-agent-chatbot'), array($this, 'render_business_hours_field'), $this->options_page, 'business_hours_settings');
        add_settings_field('ai_agent_business_hours_display', __('Texto para Mostrar Horario', 'ai-agent-chatbot'), array($this, 'render_business_hours_display_field'), $this->options_page, 'business_hours_settings');

        add_settings_field('ai_agent_campaign_messages', __('Campañas Activas', 'ai-agent-chatbot'), array($this, 'render_campaign_messages_field'), $this->options_page, 'campaigns_settings');

        add_settings_field('ai_agent_widget_enabled', __('Activar Widget', 'ai-agent-chatbot'), array($this, 'render_widget_enabled_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_widget_position', __('Posición', 'ai-agent-chatbot'), array($this, 'render_widget_position_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_logo_type', __('Tipo de Logo', 'ai-agent-chatbot'), array($this, 'render_logo_type_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_custom_logo', __('Logo Personalizado', 'ai-agent-chatbot'), array($this, 'render_custom_logo_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_header_title', __('Título del Header', 'ai-agent-chatbot'), array($this, 'render_header_title_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_welcome_message', __('Mensaje de Bienvenida', 'ai-agent-chatbot'), array($this, 'render_welcome_message_field'), $this->options_page, 'widget_appearance');
        add_settings_field('ai_agent_input_placeholder', __('Placeholder del Input', 'ai-agent-chatbot'), array($this, 'render_input_placeholder_field'), $this->options_page, 'widget_appearance');

        add_settings_field('ai_agent_primary_color', __('Color Primario', 'ai-agent-chatbot'), array($this, 'render_primary_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_secondary_color', __('Color Secundario', 'ai-agent-chatbot'), array($this, 'render_secondary_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_button_color', __('Color del Botón', 'ai-agent-chatbot'), array($this, 'render_button_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_button_icon_color', __('Color del Icono', 'ai-agent-chatbot'), array($this, 'render_button_icon_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_user_bubble_color', __('Burbuja Usuario', 'ai-agent-chatbot'), array($this, 'render_user_bubble_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_bot_bubble_color', __('Burbuja Bot', 'ai-agent-chatbot'), array($this, 'render_bot_bubble_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_user_text_color', __('Texto Usuario', 'ai-agent-chatbot'), array($this, 'render_user_text_color_field'), $this->options_page, 'widget_colors');
        add_settings_field('ai_agent_bot_text_color', __('Texto Bot', 'ai-agent-chatbot'), array($this, 'render_bot_text_color_field'), $this->options_page, 'widget_colors');

        add_settings_field('ai_agent_font_family', __('Familia de Fuente', 'ai-agent-chatbot'), array($this, 'render_font_family_field'), $this->options_page, 'widget_typography');
        add_settings_field('ai_agent_font_size', __('Tamaño de Fuente', 'ai-agent-chatbot'), array($this, 'render_font_size_field'), $this->options_page, 'widget_typography');

        add_settings_field('ai_agent_widget_width', __('Ancho', 'ai-agent-chatbot'), array($this, 'render_width_field'), $this->options_page, 'widget_dimensions');
        add_settings_field('ai_agent_widget_height', __('Alto', 'ai-agent-chatbot'), array($this, 'render_height_field'), $this->options_page, 'widget_dimensions');
        add_settings_field('ai_agent_chat_border_radius', __('Border Radius', 'ai-agent-chatbot'), array($this, 'render_chat_border_radius_field'), $this->options_page, 'widget_dimensions');
        add_settings_field('ai_agent_button_size', __('Tamaño Botón', 'ai-agent-chatbot'), array($this, 'render_button_size_field'), $this->options_page, 'widget_dimensions');
        add_settings_field('ai_agent_message_spacing', __('Espaciado Mensajes', 'ai-agent-chatbot'), array($this, 'render_message_spacing_field'), $this->options_page, 'widget_dimensions');

        add_settings_field('ai_agent_kb_type', __('Tipo de Servicio', 'ai-agent-chatbot'), array($this, 'render_kb_type_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_host', __('Endpoint/API URL', 'ai-agent-chatbot'), array($this, 'render_kb_host_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_api_key', __('API Key', 'ai-agent-chatbot'), array($this, 'render_kb_api_key_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_index_name', __('Nombre del Índice/Tabla', 'ai-agent-chatbot'), array($this, 'render_kb_index_name_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_sync_enabled', __('Sincronización Automática', 'ai-agent-chatbot'), array($this, 'render_kb_sync_enabled_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_sync_interval', __('Intervalo de Sync', 'ai-agent-chatbot'), array($this, 'render_kb_sync_interval_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_fallback_local', __('Fallback a Local', 'ai-agent-chatbot'), array($this, 'render_kb_fallback_local_field'), $this->options_page, 'kb_external_settings');
        add_settings_field('ai_agent_kb_test_connection', __('Test Conexión', 'ai-agent-chatbot'), array($this, 'render_kb_test_connection_field'), $this->options_page, 'kb_external_settings');
    }

    public function llm_section_callback() {
        echo '<p>' . __('Configura tu proveedor de LLM preferido', 'ai-agent-chatbot') . '</p>';
    }

    public function knowledge_section_callback() {
        echo '<p>' . __('Configuración de la base de conocimiento y límites de tokens', 'ai-agent-chatbot') . '</p>';
    }

    public function webhook_section_callback() {
        echo '<p>' . __('Endpoint para integraciones con WhatsApp, Twilio y Meta', 'ai-agent-chatbot') . '</p>';
    }

    public function agent_behavior_section_callback() {
        echo '<p>' . __('Configura cómo se comporta el agente con los usuarios', 'ai-agent-chatbot') . '</p>';
    }

    public function business_hours_section_callback() {
        echo '<p>' . __('Define los horarios en que el agente atiende y responde activamente', 'ai-agent-chatbot') . '</p>';
    }

    public function campaigns_section_callback() {
        echo '<p>' . __('Gestiona campañas activas y códigos de descuento', 'ai-agent-chatbot') . '</p>';
    }

    public function widget_appearance_section_callback() {
        echo '<p>' . __('Configura la apariencia general del widget de chat', 'ai-agent-chatbot') . '</p>';
    }

    public function widget_colors_section_callback() {
        echo '<p>' . __('Personaliza los colores del widget', 'ai-agent-chatbot') . '</p>';
    }

    public function widget_typography_section_callback() {
        echo '<p>' . __('Configura la tipografía del widget', 'ai-agent-chatbot') . '</p>';
    }

    public function widget_dimensions_section_callback() {
        echo '<p>' . __('Ajusta las dimensiones del widget', 'ai-agent-chatbot') . '</p>';
    }

    public function render_llm_provider_field() {
        $value = get_option('ai_agent_llm_provider', 'openai');
        ?>
        <select name="ai_agent_llm_provider" id="ai_agent_llm_provider">
            <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI (GPT-4)</option>
            <option value="anthropic" <?php selected($value, 'anthropic'); ?>>Anthropic (Claude)</option>
            <option value="ollama" <?php selected($value, 'ollama'); ?>>Ollama (Local)</option>
        </select>
        <?php
    }

    public function render_openai_key_field() {
        $value = get_option('ai_agent_openai_key', '');
        echo '<input type="password" name="ai_agent_openai_key" value="' . esc_attr($value) . '" class="regular-text" placeholder="sk-..." />';
    }

    public function render_anthropic_key_field() {
        $value = get_option('ai_agent_anthropic_key', '');
        echo '<input type="password" name="ai_agent_anthropic_key" value="' . esc_attr($value) . '" class="regular-text" placeholder="sk-ant-..." />';
    }

    public function render_ollama_url_field() {
        $value = get_option('ai_agent_ollama_url', 'http://localhost:11434');
        echo '<input type="url" name="ai_agent_ollama_url" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function render_ollama_model_field() {
        $value = get_option('ai_agent_ollama_model', 'llama3');
        echo '<input type="text" name="ai_agent_ollama_model" value="' . esc_attr($value) . '" class="regular-text" placeholder="llama3" />';
    }

    public function render_selected_pages_field() {
        $selected = get_option('ai_agent_selected_pages', array());
        $pages = get_pages();
        ?>
        <select name="ai_agent_selected_pages[]" id="ai_agent_selected_pages" multiple="multiple" style="min-width: 300px; height: 200px;">
            <?php foreach ($pages as $page) : ?>
                <option value="<?php echo esc_attr($page->ID); ?>" <?php echo in_array($page->ID, $selected) ? 'selected="selected"' : ''; ?>><?php echo esc_html($page->post_title); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Mantén Ctrl/Cmd para selección múltiple</p>
        <?php
    }

    public function render_embedding_provider_field() {
        $value = get_option('ai_agent_embedding_provider', 'openai');
        ?>
        <select name="ai_agent_embedding_provider" id="ai_agent_embedding_provider">
            <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI (text-embedding-3-small)</option>
            <option value="local" <?php selected($value, 'local'); ?>>Local (TF-IDF - no requiere API)</option>
        </select>
        <?php
    }

    public function render_token_limit_warning_field() {
        $value = get_option('ai_agent_token_limit_warning', '6000');
        echo '<input type="number" name="ai_agent_token_limit_warning" value="' . esc_attr($value) . '" class="small-text" min="1000" max="100000" />';
        echo '<p class="description">Avisar cuando el contexto se acerque a este límite de tokens</p>';
    }

    public function render_max_context_tokens_field() {
        $value = get_option('ai_agent_max_context_tokens', '8000');
        ?>
        <select name="ai_agent_max_context_tokens" id="ai_agent_max_context_tokens">
            <option value="4000" <?php selected($value, '4000'); ?>>4,000 tokens (GPT-3.5)</option>
            <option value="8000" <?php selected($value, '8000'); ?>>8,000 tokens (GPT-4 base)</option>
            <option value="16000" <?php selected($value, '16000'); ?>>16,000 tokens (GPT-4 Turbo)</option>
            <option value="32000" <?php selected($value, '32000'); ?>>32,000 tokens (Claude)</option>
            <option value="128000" <?php selected($value, '128000'); ?>>128,000 tokens (GPT-4o)</option>
        </select>
        <?php
    }

    public function render_webhook_secret_field() {
        $value = get_option('ai_agent_webhook_secret', wp_generate_password(24, false));
        echo '<input type="text" name="ai_agent_webhook_secret" value="' . esc_attr($value) . '" class="regular-text" readonly style="width: 300px;" />';
    }

    public function render_default_role_field() {
        $value = get_option('ai_agent_default_role', 'advisor');
        ?>
        <select name="ai_agent_default_role" id="ai_agent_default_role">
            <option value="advisor" <?php selected($value, 'advisor'); ?>>Asesor (responde preguntas, da información)</option>
            <option value="vendor" <?php selected($value, 'vendor'); ?>>Vendedor (puede agregar al carrito, generar links de pago)</option>
            <option value="both" <?php selected($value, 'both'); ?>>Ambos (combina asesoría y venta)</option>
        </select>
        <p class="description">El rol determina las capacidades del agente con WooCommerce</p>
        <?php
    }

    public function render_allow_checkout_field() {
        $value = get_option('ai_agent_allow_checkout', 'yes');
        ?>
        <label><input type="checkbox" name="ai_agent_allow_checkout" value="yes" <?php checked($value, 'yes'); ?> /> Permitir generar links de pago y checkout</label>
        <p class="description">Si está desactivado, el agente solo hará asesoría pero no podrá generar links de compra</p>
        <?php
    }

    public function render_greeting_primary_field() {
        $value = get_option('ai_agent_greeting_primary', '');
        echo '<textarea name="ai_agent_greeting_primary" rows="3" class="regular-text" style="width: 400px;" placeholder="¡Hola! ¿En qué puedo ayudarte hoy?">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Mensaje de saludo inicial. Usa {hora} para saludo dinámico (Buenos días/tardes/noches)</p>';
    }

    public function render_offline_message_field() {
        $value = get_option('ai_agent_offline_message', 'Gracias por contactarnos. Actualmente estamos fuera de horario. Horario: {horario}');
        echo '<textarea name="ai_agent_offline_message" rows="3" class="regular-text" style="width: 400px;">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">Usa {horario} para mostrar el horario configurado</p>';
    }

    public function render_business_hours_enabled_field() {
        $value = get_option('ai_agent_business_hours_enabled', 'no');
        ?>
        <label><input type="checkbox" name="ai_agent_business_hours_enabled" value="yes" <?php checked($value, 'yes'); ?> /> Activar horarios de atención</label>
        <?php
    }

    public function render_business_hours_field() {
        $hours = get_option('ai_agent_business_hours', array());
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $day_names = array(
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo'
        );
        ?>
        <table class="ai-agent-business-hours" style="width: auto;">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Activo</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day) : ?>
                    <tr>
                        <td><?php echo $day_names[$day]; ?></td>
                        <td><input type="checkbox" name="ai_agent_business_hours[<?php echo $day; ?>][enabled]" value="yes" <?php checked(isset($hours[$day]['enabled']) && $hours[$day]['enabled'] === 'yes', true); ?> /></td>
                        <td><input type="time" name="ai_agent_business_hours[<?php echo $day; ?>][start]" value="<?php echo esc_attr($hours[$day]['start'] ?? '09:00'); ?>" /></td>
                        <td><input type="time" name="ai_agent_business_hours[<?php echo $day; ?>][end]" value="<?php echo esc_attr($hours[$day]['end'] ?? '18:00'); ?>" /></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_business_hours_display_field() {
        $value = get_option('ai_agent_business_hours_display', 'Lun-Vie 9:00-18:00');
        echo '<input type="text" name="ai_agent_business_hours_display" value="' . esc_attr($value) . '" class="regular-text" style="width: 300px;" />';
        echo '<p class="description">Texto que se muestra al usuario indicando el horario de atención</p>';
    }

    public function render_campaign_messages_field() {
        $campaigns = get_option('ai_agent_campaign_messages', array());
        $campaigns_json = !empty($campaigns) ? json_encode($campaigns, JSON_UNESCAPED_UNICODE) : '[]';
        ?>
        <div id="ai-agent-campaigns-container">
            <textarea id="ai_agent_campaigns_json" name="ai_agent_campaigns" rows="8" style="width: 100%;"><?php echo esc_textarea($campaigns_json); ?></textarea>
        </div>
        <p class="description">Agrega campañas en formato JSON. Ejemplo:<br>
        <code>[{"code": "VERANO20", "message": "¡20% de descuento en ropa de verano!", "discount_type": "percentage", "discount_value": 20, "active": true}]</code></p>
        <button type="button" class="button" id="ai_agent_add_campaign">Agregar Campaña</button>

        <script>
        jQuery(document).ready(function($) {
            $('#ai_agent_add_campaign').on('click', function() {
                var current = JSON.parse($('#ai_agent_campaigns_json').val() || '[]');
                current.push({
                    code: 'NUEVO_CODIGO',
                    message: 'Mensaje de la campaña',
                    discount_type: 'percentage',
                    discount_value: 10,
                    start_date: '<?php echo date('Y-m-d'); ?>',
                    end_date: '<?php echo date('Y-m-d', strtotime('+30 days')); ?>',
                    active: true
                });
                $('#ai_agent_campaigns_json').val(JSON.stringify(current, null, 2));
            });
        });
        </script>
        <?php
    }

    public function render_widget_enabled_field() {
        $value = get_option('ai_agent_widget_enabled', 'yes');
        ?>
        <label><input type="checkbox" name="ai_agent_widget_enabled" value="yes" <?php checked($value, 'yes'); ?> /> Activar widget de chat</label>
        <?php
    }

    public function render_widget_position_field() {
        $value = get_option('ai_agent_widget_position', 'bottom-right');
        ?>
        <select name="ai_agent_widget_position" id="ai_agent_widget_position">
            <option value="bottom-right" <?php selected($value, 'bottom-right'); ?>>Abajo a la derecha</option>
            <option value="bottom-left" <?php selected($value, 'bottom-left'); ?>>Abajo a la izquierda</option>
            <option value="top-right" <?php selected($value, 'top-right'); ?>>Arriba a la derecha</option>
            <option value="top-left" <?php selected($value, 'top-left'); ?>>Arriba a la izquierda</option>
        </select>
        <?php
    }

    public function render_logo_type_field() {
        $value = get_option('ai_agent_logo_type', 'default');
        ?>
        <select name="ai_agent_logo_type" id="ai_agent_logo_type">
            <option value="default" <?php selected($value, 'default'); ?>>Icono chat (predeterminado)</option>
            <option value="site_icon" <?php selected($value, 'site_icon'); ?>>Icono del sitio</option>
            <option value="custom" <?php selected($value, 'custom'); ?>>Logo personalizado</option>
        </select>
        <?php
    }

    public function render_custom_logo_field() {
        $value = get_option('ai_agent_custom_logo', '');
        ?>
        <input type="url" name="ai_agent_custom_logo" value="<?php echo esc_attr($value); ?>" class="regular-text" style="width: 350px;" placeholder="https://..." />
        <button type="button" class="button" id="ai_agent_upload_logo">Subir</button>
        <p class="description">Recomendado: imagen cuadrada 64x64px mínimo (PNG, JPG, SVG)</p>
        <script>
        jQuery(document).ready(function($) {
            $('#ai_agent_upload_logo').click(function() {
                var image = wp.media({
                    title: 'Seleccionar Logo',
                    multiple: false
                }).open().on('select', function(e) {
                    var uploaded_image = image.state().get('selection').first().toJSON();
                    $('#ai_agent_custom_logo').val(uploaded_image.url);
                });
            });
        });
        </script>
        <?php
    }

    public function render_header_title_field() {
        $value = get_option('ai_agent_header_title', __('Asistente AI', 'ai-agent-chatbot'));
        echo '<input type="text" name="ai_agent_header_title" value="' . esc_attr($value) . '" class="regular-text" style="width: 250px;" />';
    }

    public function render_welcome_message_field() {
        $value = get_option('ai_agent_welcome_message', __('¡Hola! ¿En qué puedo ayudarte hoy?', 'ai-agent-chatbot'));
        echo '<textarea name="ai_agent_welcome_message" rows="2" class="regular-text" style="width: 350px;">' . esc_textarea($value) . '</textarea>';
    }

    public function render_input_placeholder_field() {
        $value = get_option('ai_agent_input_placeholder', __('Escribe tu mensaje...', 'ai-agent-chatbot'));
        echo '<input type="text" name="ai_agent_input_placeholder" value="' . esc_attr($value) . '" class="regular-text" style="width: 250px;" />';
    }

    public function render_primary_color_field() {
        $value = get_option('ai_agent_primary_color', '#667eea');
        echo '<input type="text" name="ai_agent_primary_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#667eea" />';
    }

    public function render_secondary_color_field() {
        $value = get_option('ai_agent_secondary_color', '#764ba2');
        echo '<input type="text" name="ai_agent_secondary_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#764ba2" />';
    }

    public function render_button_color_field() {
        $value = get_option('ai_agent_button_color', '#667eea');
        echo '<input type="text" name="ai_agent_button_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#667eea" />';
    }

    public function render_button_icon_color_field() {
        $value = get_option('ai_agent_button_icon_color', '#ffffff');
        echo '<input type="text" name="ai_agent_button_icon_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#ffffff" />';
    }

    public function render_user_bubble_color_field() {
        $value = get_option('ai_agent_user_bubble_color', '#667eea');
        echo '<input type="text" name="ai_agent_user_bubble_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#667eea" />';
    }

    public function render_bot_bubble_color_field() {
        $value = get_option('ai_agent_bot_bubble_color', '#f0f2f5');
        echo '<input type="text" name="ai_agent_bot_bubble_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#f0f2f5" />';
    }

    public function render_user_text_color_field() {
        $value = get_option('ai_agent_user_text_color', '#ffffff');
        echo '<input type="text" name="ai_agent_user_text_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#ffffff" />';
    }

    public function render_bot_text_color_field() {
        $value = get_option('ai_agent_bot_text_color', '#1a1a1a');
        echo '<input type="text" name="ai_agent_bot_text_color" value="' . esc_attr($value) . '" class="ai-agent-color-picker" data-default-color="#1a1a1a" />';
    }

    public function render_font_family_field() {
        $value = get_option('ai_agent_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif');
        echo '<input type="text" name="ai_agent_font_family" value="' . esc_attr($value) . '" class="regular-text" style="width: 350px;" />';
    }

    public function render_font_size_field() {
        $value = get_option('ai_agent_font_size', '14');
        ?>
        <select name="ai_agent_font_size" id="ai_agent_font_size">
            <?php for ($i = 11; $i <= 20; $i++) : ?>
                <option value="<?php echo $i; ?>" <?php selected($value, (string)$i); ?>><?php echo $i; ?>px</option>
            <?php endfor; ?>
        </select>
        <?php
    }

    public function render_width_field() {
        $value = get_option('ai_agent_widget_width', '380');
        echo '<input type="number" name="ai_agent_widget_width" value="' . esc_attr($value) . '" class="small-text" min="280" max="600" /> px';
        echo '<span class="description"> (280-600px,推荐的: 350-420px)</span>';
    }

    public function render_height_field() {
        $value = get_option('ai_agent_widget_height', '500');
        echo '<input type="number" name="ai_agent_widget_height" value="' . esc_attr($value) . '" class="small-text" min="300" max="800" /> px';
        echo '<span class="description"> (300-800px,推荐的: 450-550px)</span>';
    }

    public function render_chat_border_radius_field() {
        $value = get_option('ai_agent_chat_border_radius', '16');
        echo '<input type="number" name="ai_agent_chat_border_radius" value="' . esc_attr($value) . '" class="small-text" min="0" max="30" /> px';
    }

    public function render_button_size_field() {
        $value = get_option('ai_agent_button_size', '60');
        echo '<input type="number" name="ai_agent_button_size" value="' . esc_attr($value) . '" class="small-text" min="40" max="100" /> px';
    }

    public function render_message_spacing_field() {
        $value = get_option('ai_agent_message_spacing', '16');
        echo '<input type="number" name="ai_agent_message_spacing" value="' . esc_attr($value) . '" class="small-text" min="8" max="32" /> px';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap ai-agent-admin-wrap">
            <h1><?php _e('AI Agent Chatbot - Configuración', 'ai-agent-chatbot'); ?></h1>

            <form action="options.php" method="post" enctype="multipart/form-data">
                <?php settings_fields($this->options_group); ?>

                <div class="ai-agent-settings-grid">
                    <div class="ai-agent-settings-section">
                        <h2><?php _e('LLM', 'ai-agent-chatbot'); ?></h2>
                        <?php do_settings_fields($this->options_page, 'llm_settings'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h2><?php _e('Base de Conocimiento', 'ai-agent-chatbot'); ?></h2>
                        <?php do_settings_fields($this->options_page, 'knowledge_settings'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h2><?php _e('Webhook', 'ai-agent-chatbot'); ?></h2>
                        <p><strong>URL:</strong></p>
                        <code style="display:block; padding: 10px; background: #f8f9fa; margin-bottom: 10px; word-break: break-all;">
                            <?php echo esc_url(AI_Agent_Settings::get_webhook_url()); ?>
                        </code>
                        <?php do_settings_fields($this->options_page, 'webhook_settings'); ?>
                    </div>
                </div>

                <hr style="margin: 30px 0;">
                <h2><?php _e('Comportamiento y Horarios', 'ai-agent-chatbot'); ?></h2>

                <div class="ai-agent-settings-grid">
                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Rol del Agente', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'agent_behavior'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Horarios de Atención', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'business_hours_settings'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Campañas', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'campaigns_settings'); ?>
                    </div>
                </div>

                <hr style="margin: 30px 0;">
                <h2><?php _e('Personalización del Widget', 'ai-agent-chatbot'); ?></h2>

                <div class="ai-agent-settings-grid">
                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Apariencia', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'widget_appearance'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Colores', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'widget_colors'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Tipografía', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'widget_typography'); ?>
                    </div>

                    <div class="ai-agent-settings-section">
                        <h3><?php _e('Dimensiones', 'ai-agent-chatbot'); ?></h3>
                        <?php do_settings_fields($this->options_page, 'widget_dimensions'); ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <style>
        .ai-agent-admin-wrap { max-width: 1400px; }
        .ai-agent-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .ai-agent-settings-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .ai-agent-settings-section h2,
        .ai-agent-settings-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaeaea;
        }
        .ai-agent-business-hours td,
        .ai-agent-business-hours th {
            padding: 8px;
            text-align: left;
        }
        .ai-agent-business-hours input[type="time"] {
            width: 100px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.ai-agent-color-picker').wpColorPicker();
        });
        </script>
        <?php
    }

    public function sanitize_array($input) {
        return is_array($input) ? array_map('sanitize_text_field', $input) : array();
    }

    public function sanitize_business_hours($input) {
        if (!is_array($input)) {
            return array();
        }

        $sanitized = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        foreach ($days as $day) {
            if (isset($input[$day])) {
                $sanitized[$day] = array(
                    'enabled' => isset($input[$day]['enabled']) && $input[$day]['enabled'] === 'yes' ? 'yes' : 'no',
                    'start' => sanitize_text_field($input[$day]['start'] ?? '09:00'),
                    'end' => sanitize_text_field($input[$day]['end'] ?? '18:00')
                );
            }
        }

        return $sanitized;
    }

    public function sanitize_campaigns($input) {
        if (empty($input)) {
            return array();
        }

        $decoded = json_decode($input, true);
        if (!is_array($decoded)) {
            return array();
        }

        $sanitized = array();
        foreach ($decoded as $campaign) {
            $sanitized[] = array(
                'code' => sanitize_text_field($campaign['code'] ?? ''),
                'message' => sanitize_textarea_field($campaign['message'] ?? ''),
                'discount_type' => sanitize_text_field($campaign['discount_type'] ?? 'percentage'),
                'discount_value' => floatval($campaign['discount_value'] ?? 0),
                'start_date' => sanitize_text_field($campaign['start_date'] ?? ''),
                'end_date' => sanitize_text_field($campaign['end_date'] ?? ''),
                'active' => !empty($campaign['active'])
            );
        }

        return $sanitized;
    }

    public function kb_external_section_callback() {
        echo '<p>' . __('Configura la conexión a servicios externos de base de conocimiento vectorial', 'ai-agent-chatbot') . '</p>';
    }

    public function render_kb_type_field() {
        $value = get_option('ai_agent_kb_type', 'local');
        $types = AI_Agent_KB_Factory::get_available_types();
        ?>
        <select name="ai_agent_kb_type" id="ai_agent_kb_type">
            <?php foreach ($types as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Selecciona el servicio de base de conocimiento vectorial que usarás</p>
        <?php
    }

    public function render_kb_host_field() {
        $value = get_option('ai_agent_kb_host', '');
        echo '<input type="text" name="ai_agent_kb_host" value="' . esc_attr($value) . '" class="regular-text" style="width: 400px;" placeholder="https://your-pinecone-project.env.io" />';
        echo '<p class="description">URL del endpoint de tu servicio (Pinecone, PostgreSQL, Supabase, etc.)</p>';
    }

    public function render_kb_api_key_field() {
        $value = get_option('ai_agent_kb_api_key', '');
        echo '<input type="password" name="ai_agent_kb_api_key" value="' . esc_attr($value) . '" class="regular-text" style="width: 400px;" placeholder="API Key de tu servicio" />';
    }

    public function render_kb_index_name_field() {
        $value = get_option('ai_agent_kb_index_name', 'ai-agent-kb');
        echo '<input type="text" name="ai_agent_kb_index_name" value="' . esc_attr($value) . '" class="regular-text" style="width: 250px;" />';
        echo '<p class="description">Nombre del índice en Pinecone, tabla en PostgreSQL/Supabase</p>';
    }

    public function render_kb_sync_enabled_field() {
        $value = get_option('ai_agent_kb_sync_enabled', 'yes');
        ?>
        <label><input type="checkbox" name="ai_agent_kb_sync_enabled" value="yes" <?php checked($value, 'yes'); ?> /> Sincronizar automáticamente</label>
        <p class="description">Sincroniza el contenido de WordPress al servicio externo periódicamente</p>
        <?php
    }

    public function render_kb_sync_interval_field() {
        $value = get_option('ai_agent_kb_sync_interval', '60');
        ?>
        <select name="ai_agent_kb_sync_interval" id="ai_agent_kb_sync_interval">
            <option value="15" <?php selected($value, '15'); ?>>Cada 15 minutos</option>
            <option value="30" <?php selected($value, '30'); ?>>Cada 30 minutos</option>
            <option value="60" <?php selected($value, '60'); ?>>Cada hora</option>
            <option value="180" <?php selected($value, '180'); ?>>Cada 3 horas</option>
            <option value="360" <?php selected($value, '360'); ?>>Cada 6 horas</option>
            <option value="720" <?php selected($value, '720'); ?>>Cada 12 horas</option>
            <option value="1440" <?php selected($value, '1440'); ?>>Diario</option>
        </select>
        <?php
    }

    public function render_kb_fallback_local_field() {
        $value = get_option('ai_agent_kb_fallback_local', 'yes');
        ?>
        <label><input type="checkbox" name="ai_agent_kb_fallback_local" value="yes" <?php checked($value, 'yes'); ?> /> Usar base de conocimiento local si el servicio externo falla</label>
        <?php
    }

    public function render_kb_test_connection_field() {
        echo '<button type="button" class="button" id="ai_agent_test_kb_connection">' . __('Probar Conexión', 'ai-agent-chatbot') . '</button>';
        echo '<span id="ai_agent_kb_test_result" style="margin-left: 10px;"></span>';
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ai_agent_kb_type').on('change', function() {
                var type = $(this).val();
                var $host = $('#ai_agent_kb_host');
                var $key = $('#ai_agent_kb_api_key');

                if (type === 'postgresql') {
                    $host.attr('placeholder', 'postgres://user:pass@host:5432/database');
                } else if (type === 'pinecone') {
                    $host.attr('placeholder', 'https://your-project.svc.your-region.pinecone.io');
                } else if (type === 'supabase') {
                    $host.attr('placeholder', 'No requerido para Supabase');
                } else {
                    $host.attr('placeholder', 'https://your-api.com');
                }
            });

            $('#ai_agent_test_kb_connection').on('click', function() {
                var $btn = $(this);
                var $result = $('#ai_agent_kb_test_result');

                $btn.prop('disabled', true).text('Probando...');
                $result.text('');

                $.post(ajaxurl, {
                    action: 'ai_agent_test_kb_connection',
                    nonce: '<?php echo wp_create_nonce('ai_agent_admin'); ?>',
                    type: $('#ai_agent_kb_type').val(),
                    host: $('#ai_agent_kb_host').val(),
                    api_key: $('#ai_agent_kb_api_key').val(),
                    index_name: $('#ai_agent_kb_index_name').val()
                }, function(response) {
                    $btn.prop('disabled', false).text('Probar Conexión');

                    if (response.success) {
                        $result.html('<span style="color: green;">✓ Conexión exitosa (' + response.data.vectors + ' vectores)</span>');
                    } else {
                        $result.html('<span style="color: red;">✗ Error: ' + response.data + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Probar Conexión');
                    $result.html('<span style="color: red;">✗ Error de conexión</span>');
                });
            });
        });
        </script>
        <?php
    }

    public static function get_kb_config() {
        return array(
            'type' => get_option('ai_agent_kb_type', 'local'),
            'host' => get_option('ai_agent_kb_host', ''),
            'api_key' => get_option('ai_agent_kb_api_key', ''),
            'index_name' => get_option('ai_agent_kb_index_name', 'ai-agent-kb'),
            'sync_enabled' => get_option('ai_agent_kb_sync_enabled', 'yes') === 'yes',
            'sync_interval' => intval(get_option('ai_agent_kb_sync_interval', '60')),
            'fallback_local' => get_option('ai_agent_kb_fallback_local', 'yes') === 'yes'
        );
    }

    public static function get_llm_config() {
        return array(
            'provider' => get_option('ai_agent_llm_provider', 'openai'),
            'openai_key' => get_option('ai_agent_openai_key', ''),
            'anthropic_key' => get_option('ai_agent_anthropic_key', ''),
            'ollama_url' => get_option('ai_agent_ollama_url', 'http://localhost:11434'),
            'ollama_model' => get_option('ai_agent_ollama_model', 'llama3'),
        );
    }

    public static function get_webhook_url() {
        return rest_url('ai-agent/v1/webhook');
    }

    public static function get_webhook_secret() {
        return get_option('ai_agent_webhook_secret', '');
    }

    public static function get_widget_config() {
        return array(
            'enabled' => get_option('ai_agent_widget_enabled', 'yes') === 'yes',
            'position' => get_option('ai_agent_widget_position', 'bottom-right'),
            'width' => get_option('ai_agent_widget_width', '380'),
            'height' => get_option('ai_agent_widget_height', '500'),
            'logo_type' => get_option('ai_agent_logo_type', 'default'),
            'custom_logo' => get_option('ai_agent_custom_logo', ''),
            'primary_color' => get_option('ai_agent_primary_color', '#667eea'),
            'secondary_color' => get_option('ai_agent_secondary_color', '#764ba2'),
            'button_color' => get_option('ai_agent_button_color', '#667eea'),
            'button_icon_color' => get_option('ai_agent_button_icon_color', '#ffffff'),
            'user_bubble_color' => get_option('ai_agent_user_bubble_color', '#667eea'),
            'bot_bubble_color' => get_option('ai_agent_bot_bubble_color', '#f0f2f5'),
            'user_text_color' => get_option('ai_agent_user_text_color', '#ffffff'),
            'bot_text_color' => get_option('ai_agent_bot_text_color', '#1a1a1a'),
            'font_family' => get_option('ai_agent_font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif'),
            'font_size' => get_option('ai_agent_font_size', '14'),
            'header_title' => get_option('ai_agent_header_title', __('Asistente AI', 'ai-agent-chatbot')),
            'welcome_message' => get_option('ai_agent_welcome_message', __('¡Hola! ¿En qué puedo ayudarte hoy?', 'ai-agent-chatbot')),
            'input_placeholder' => get_option('ai_agent_input_placeholder', __('Escribe tu mensaje...', 'ai-agent-chatbot')),
            'chat_border_radius' => get_option('ai_agent_chat_border_radius', '16'),
            'button_size' => get_option('ai_agent_button_size', '60'),
            'message_spacing' => get_option('ai_agent_message_spacing', '16'),
        );
    }

    public static function get_agent_config() {
        return array(
            'default_role' => get_option('ai_agent_default_role', 'advisor'),
            'allow_checkout' => get_option('ai_agent_allow_checkout', 'yes') === 'yes',
            'greeting_primary' => get_option('ai_agent_greeting_primary', ''),
            'offline_message' => get_option('ai_agent_offline_message', ''),
            'business_hours_enabled' => get_option('ai_agent_business_hours_enabled', 'no') === 'yes',
            'business_hours' => get_option('ai_agent_business_hours', array()),
            'business_hours_display' => get_option('ai_agent_business_hours_display', 'Lun-Vie 9:00-18:00'),
            'campaigns' => get_option('ai_agent_campaign_messages', array()),
            'token_limit_warning' => intval(get_option('ai_agent_token_limit_warning', '6000')),
            'max_context_tokens' => intval(get_option('ai_agent_max_context_tokens', '8000'))
        );
    }
}