<?php

class AI_Agent_Public {
    public function __construct() {
        add_action('init', array($this, 'register_ajax_handlers'));
    }

    public function register_ajax_handlers() {
        add_action('wp_ajax_ai_agent_chat', array($this, 'handle_chat_ajax'));
        add_action('wp_ajax_nopriv_ai_agent_chat', array($this, 'handle_chat_ajax'));
    }

    public function handle_chat_ajax() {
        check_ajax_referer('ai_agent_nonce', 'nonce');

        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (empty($message)) {
            wp_send_json_error(array('error' => 'No message provided'));
        }

        $knowledge_base = new AI_Agent_Knowledge_Base();
        $llm = new AI_Agent_LLM_Provider();

        $relevant_context = $knowledge_base->find_relevant_context($message, 5);
        $context_text = $knowledge_base->format_context_for_llm($relevant_context);

        $messages = array(
            array('role' => 'user', 'content' => $message)
        );

        $response = $llm->chat($messages, $context_text);

        if (is_wp_error($response)) {
            wp_send_json_error(array('error' => $response->get_error_message()));
        }

        $woocommerce = ai_agent_woocommerce();
        $response = $woocommerce->replace_payment_links($response);

        wp_send_json_success(array(
            'response' => $response,
            'context_used' => count($relevant_context) > 0
        ));
    }
}

function ai_agent_public_init() {
    return new AI_Agent_Public();
}

add_action('init', 'ai_agent_public_init');