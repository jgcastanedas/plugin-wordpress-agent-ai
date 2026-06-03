<?php

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent_Widget {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_footer', array($this, 'render_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        if (get_option('ai_agent_widget_enabled', 'yes') !== 'yes') {
            return;
        }

        $config = AI_Agent_Settings::get_widget_config();

        $custom_css = $this->generate_custom_css($config);

        wp_enqueue_style(
            'ai-agent-widget',
            AI_AGENT_PLUGIN_URL . 'assets/css/widget-base.css',
            array(),
            AI_AGENT_VERSION
        );

        wp_add_inline_style('ai-agent-widget', $custom_css);

        wp_enqueue_script(
            'ai-agent-widget',
            AI_AGENT_PLUGIN_URL . 'assets/js/widget.js',
            array('jquery'),
            AI_AGENT_VERSION,
            true
        );

        $streaming_enabled = get_option('ai_agent_streaming_enabled', 'no') === 'yes';
        $provider = get_option('ai_agent_llm_provider', 'openai');
        $streaming_supported = in_array($provider, array('openai', 'anthropic', 'deepseek', 'kimi', 'minimax'), true);

        wp_localize_script('ai-agent-widget', 'aiAgentWidget', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_agent_nonce'),
            'webhookUrl' => AI_Agent_Settings::get_webhook_url(),
            'streaming' => $streaming_enabled && $streaming_supported,
            'config' => $config,
            'i18n' => array(
                'welcome' => $config['welcome_message'],
                'placeholder' => $config['input_placeholder'],
                'send' => __('Enviar', 'ai-agent-chatbot'),
                'typing' => __('Escribiendo...', 'ai-agent-chatbot'),
            ),
        ));
    }

    private function generate_custom_css($config) {
        $position = $config['position'];
        $pos_css = '';

        switch ($position) {
            case 'bottom-right':
                $pos_css = 'bottom: 20px; right: 20px;';
                break;
            case 'bottom-left':
                $pos_css = 'bottom: 20px; left: 20px;';
                break;
            case 'top-right':
                $pos_css = 'top: 20px; right: 20px;';
                break;
            case 'top-left':
                $pos_css = 'top: 20px; left: 20px;';
                break;
        }

        $offset = intval($config['button_size']) + 30;
        if (strpos($position, 'top') !== false) {
            $window_pos = "top: {$offset}px;";
        } else {
            $window_pos = "bottom: {$offset}px;";
        }

        if (strpos($position, 'right') !== false) {
            $window_pos .= ' right: 0;';
        } else {
            $window_pos .= ' left: 0;';
        }

        $logo_css = '';
        if ($config['logo_type'] === 'custom' && !empty($config['custom_logo'])) {
            $logo_css = '
            .ai-agent-chat-button svg { display: none; }
            .ai-agent-chat-button {
                background-image: url("' . esc_url($config['custom_logo']) . '");
                background-size: 70% 70%;
                background-repeat: no-repeat;
                background-position: center;
            }';
        } elseif ($config['logo_type'] === 'site_icon') {
            $site_icon = get_site_icon_url(64);
            if ($site_icon) {
                $logo_css = '
                .ai-agent-chat-button svg { display: none; }
                .ai-agent-chat-button {
                    background-image: url("' . esc_url($site_icon) . '");
                    background-size: 70% 70%;
                    background-repeat: no-repeat;
                    background-position: center;
                }';
            }
        }

        return "
        #ai-agent-chat-container {
            position: fixed;
            z-index: 99999;
            font-family: {$config['font_family']};
            {$pos_css}
        }

        .ai-agent-chat-button {
            width: {$config['button_size']}px;
            height: {$config['button_size']}px;
            border-radius: 50%;
            background: {$config['button_color']};
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            color: {$config['button_icon_color']};
        }

        .ai-agent-chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2);
        }

        {$logo_css}

        .ai-agent-chat-window {
            position: absolute;
            {$window_pos}
            width: {$config['width']}px;
            height: {$config['height']}px;
            background: white;
            border-radius: {$config['chat_border_radius']}px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        @media (max-width: 480px) {
            .ai-agent-chat-window {
                width: calc(100vw - 40px);
                height: calc(100vh - 140px);
                max-width: {$config['width']}px;
            }
        }

        .ai-agent-chat-header {
            background: linear-gradient(135deg, {$config['primary_color']} 0%, {$config['secondary_color']} 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-agent-chat-header h3 {
            margin: 0;
            font-size: " . ($config['font_size'] + 4) . "px;
            font-weight: 600;
        }

        .ai-agent-close-chat {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .ai-agent-close-chat:hover {
            opacity: 1;
        }

        .ai-agent-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: {$config['message_spacing']}px;
        }

        .ai-agent-message {
            display: flex;
            max-width: 85%;
        }

        .ai-agent-message-user {
            align-self: flex-end;
        }

        .ai-agent-message-bot {
            align-self: flex-start;
        }

        .ai-agent-message-content {
            padding: 12px 16px;
            border-radius: 16px;
            font-size: {$config['font_size']}px;
            line-height: 1.5;
        }

        .ai-agent-message-user .ai-agent-message-content {
            background: {$config['user_bubble_color']};
            color: {$config['user_text_color']};
            border-bottom-right-radius: 4px;
        }

        .ai-agent-message-bot .ai-agent-message-content {
            background: {$config['bot_bubble_color']};
            color: {$config['bot_text_color']};
            border-bottom-left-radius: 4px;
        }

        .ai-agent-typing-indicator {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: {$config['bot_bubble_color']};
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            width: fit-content;
        }

        .ai-agent-typing-indicator span {
            width: 8px;
            height: 8px;
            background: {$config['primary_color']};
            border-radius: 50%;
            animation: ai-agent-typing 1.4s infinite ease-in-out;
        }

        .ai-agent-typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .ai-agent-typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes ai-agent-typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-8px);
                opacity: 1;
            }
        }

        .ai-agent-chat-input-container {
            padding: 16px;
            border-top: 1px solid #eaeaea;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        #ai-agent-chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #eaeaea;
            border-radius: 24px;
            font-size: {$config['font_size']}px;
            outline: none;
            transition: border-color 0.2s;
        }

        #ai-agent-chat-input:focus {
            border-color: {$config['primary_color']};
        }

        #ai-agent-send-message {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: {$config['button_color']};
            border: none;
            color: {$config['button_icon_color']};
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        #ai-agent-send-message:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        #ai-agent-send-message:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        ";
    }

    public function render_widget() {
        if (get_option('ai_agent_widget_enabled', 'yes') !== 'yes') {
            return;
        }

        $config = AI_Agent_Settings::get_widget_config();
        $position_class = 'ai-agent-' . $config['position'];
        ?>
        <div id="ai-agent-chat-container" class="<?php echo esc_attr($position_class); ?>">
            <div id="ai-agent-chat-button" class="ai-agent-chat-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <div id="ai-agent-chat-window" class="ai-agent-chat-window" style="display: none;">
                <div class="ai-agent-chat-header">
                    <h3><?php echo esc_html($config['header_title']); ?></h3>
                    <button id="ai-agent-close-chat" class="ai-agent-close-chat">&times;</button>
                </div>
                <div id="ai-agent-chat-messages" class="ai-agent-chat-messages">
                    <div class="ai-agent-message ai-agent-message-bot">
                        <div class="ai-agent-message-content">
                            <?php echo esc_html($config['welcome_message']); ?>
                        </div>
                    </div>
                </div>
                <div class="ai-agent-chat-input-container">
                    <input type="text" id="ai-agent-chat-input" placeholder="<?php echo esc_attr($config['input_placeholder']); ?>" />
                    <button id="ai-agent-send-message">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

}

function ai_agent_widget() {
    return AI_Agent_Widget::get_instance();
}

add_action('init', array('AI_Agent_Widget', 'get_instance'));