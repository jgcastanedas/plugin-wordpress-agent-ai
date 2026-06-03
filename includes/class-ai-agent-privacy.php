<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cumplimiento GDPR / LOPDGDD.
 * Engancha las herramientas de privacidad nativas de WordPress
 * (Tools → Export/Erase Personal Data) para exportar y borrar
 * conversaciones, mensajes y carrito asociados a un email o teléfono.
 */
class AI_Agent_Privacy {

    public function __construct() {
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
    }

    public function register_exporter($exporters) {
        $exporters['ai-agent-chatbot'] = array(
            'exporter_friendly_name' => __('AI Agent Chatbot', 'ai-agent-chatbot'),
            'callback'               => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    public function register_eraser($erasers) {
        $erasers['ai-agent-chatbot'] = array(
            'eraser_friendly_name' => __('AI Agent Chatbot', 'ai-agent-chatbot'),
            'callback'             => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    private function find_conversations($email_or_phone) {
        global $wpdb;

        $conv_table = $wpdb->prefix . 'ai_agent_conversations';
        $user = get_user_by('email', $email_or_phone);

        if ($user) {
            $phones = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key IN ('billing_phone', 'phone')",
                $user->ID
            ));

            $ids = array();
            foreach ($phones as $phone) {
                if (empty($phone)) continue;
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$conv_table} WHERE phone = %s",
                    $phone
                ));
                foreach ($rows as $r) {
                    $ids[$r->id] = $r;
                }
            }
            return array_values($ids);
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$conv_table} WHERE phone = %s",
            $email_or_phone
        ));
    }

    public function export_personal_data($email_address, $page = 1) {
        global $wpdb;

        $conversations = $this->find_conversations($email_address);
        $export_items = array();

        foreach ($conversations as $conv) {
            $data = array(
                array('name' => __('Session ID', 'ai-agent-chatbot'), 'value' => $conv->session_id),
                array('name' => __('Teléfono', 'ai-agent-chatbot'), 'value' => $conv->phone),
                array('name' => __('IP', 'ai-agent-chatbot'), 'value' => $conv->ip_address),
                array('name' => __('User Agent', 'ai-agent-chatbot'), 'value' => $conv->user_agent),
                array('name' => __('Creada', 'ai-agent-chatbot'), 'value' => $conv->created_at),
                array('name' => __('Última actividad', 'ai-agent-chatbot'), 'value' => $conv->last_activity_at),
                array('name' => __('Mensajes totales', 'ai-agent-chatbot'), 'value' => $conv->total_messages),
            );

            $export_items[] = array(
                'group_id'    => 'ai_agent_conversations',
                'group_label' => __('Conversaciones AI Agent', 'ai-agent-chatbot'),
                'item_id'     => 'conversation-' . $conv->id,
                'data'        => $data,
            );

            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT role, content, created_at FROM {$wpdb->prefix}ai_agent_messages
                 WHERE conversation_id = %d ORDER BY created_at ASC",
                $conv->id
            ));

            foreach ($messages as $msg) {
                $export_items[] = array(
                    'group_id'    => 'ai_agent_messages',
                    'group_label' => __('Mensajes AI Agent', 'ai-agent-chatbot'),
                    'item_id'     => 'message-' . $conv->id . '-' . md5($msg->created_at . $msg->role),
                    'data'        => array(
                        array('name' => __('Rol', 'ai-agent-chatbot'), 'value' => $msg->role),
                        array('name' => __('Contenido', 'ai-agent-chatbot'), 'value' => $msg->content),
                        array('name' => __('Fecha', 'ai-agent-chatbot'), 'value' => $msg->created_at),
                    ),
                );
            }
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    public function erase_personal_data($email_address, $page = 1) {
        global $wpdb;

        $conversations = $this->find_conversations($email_address);

        $items_removed = 0;
        $items_retained = 0;
        $messages = array();

        $conv_table = $wpdb->prefix . 'ai_agent_conversations';
        $msg_table  = $wpdb->prefix . 'ai_agent_messages';
        $cart_table = $wpdb->prefix . 'ai_agent_cart';

        foreach ($conversations as $conv) {
            $wpdb->delete($cart_table, array('conversation_id' => $conv->id), array('%d'));
            $wpdb->delete($msg_table,  array('conversation_id' => $conv->id), array('%d'));
            $deleted = $wpdb->delete($conv_table, array('id' => $conv->id), array('%d'));

            if ($deleted) {
                $items_removed++;
            } else {
                $items_retained++;
                $messages[] = sprintf(__('No se pudo eliminar la conversación %d', 'ai-agent-chatbot'), $conv->id);
            }
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
