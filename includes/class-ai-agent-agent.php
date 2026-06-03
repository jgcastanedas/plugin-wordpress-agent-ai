<?php

class AI_Agent_Agent {
    private $conversation;
    private $message;
    private $cart;
    private $campaign;
    private $knowledge_base;

    public function __construct() {
        $this->conversation = new AI_Agent_Conversation();
        $this->message = new AI_Agent_Message();
        $this->cart = new AI_Agent_Cart();
        $this->campaign = new AI_Agent_Campaign();
        $this->knowledge_base = new AI_Agent_Knowledge_Base();
    }

    public function handle_message($input, $session_id = null, $phone = null) {
        $source = $this->detect_source($input);

        $conversation = $this->get_or_create_conversation($session_id, $phone, $source);

        $intent = $this->detect_intent($input);
        $input['intent'] = $intent;

        if ($intent === 'greeting' && $this->should_show_greeting($conversation->id)) {
            $greeting = $this->get_greeting_message();
            $this->save_message($conversation->id, 'assistant', $greeting);
            return $this->format_response($greeting, $conversation, 'greeting');
        }

        if ($intent === 'out_of_hours') {
            $offline_message = $this->get_offline_message();
            $this->save_message($conversation->id, 'assistant', $offline_message);
            return $this->format_response($offline_message, $conversation, 'offline');
        }

        $context = $this->build_context($input['text'] ?? '', $conversation);

        $system_prompt = $this->build_system_prompt($conversation);

        $messages = $this->get_conversation_history($conversation->id, 5);
        $messages[] = array('role' => 'user', 'content' => $input['text'] ?? $input['message']);

        $llm = new AI_Agent_LLM_Provider();
        $response = $llm->chat($messages, $system_prompt . "\n\nContext:\n" . $context);

        if (is_wp_error($response)) {
            $error_msg = 'Lo siento, ocurrió un error. Por favor intenta de nuevo.';
            $this->save_message($conversation->id, 'assistant', $error_msg, 0, '', $intent);
            return $this->format_response($error_msg, $conversation, 'error');
        }

        $response = $this->process_response($response, $conversation, $intent);

        $tokens_used = $this->estimate_tokens($messages, $response);
        $cost = AI_Agent_Token_Pricing::calculate_cost('gpt-4o', $tokens_used * 0.75, $tokens_used * 0.25);

        $this->save_message($conversation->id, 'user', $input['text'] ?? $input['message'], 0, '', $intent);
        $this->save_message($conversation->id, 'assistant', $response, $tokens_used, 'gpt-4o', $intent, $context);

        $this->conversation->increment_stats($conversation->id, $tokens_used);

        return $this->format_response($response, $conversation, 'success', array(
            'intent' => $intent,
            'cart_items' => $this->cart->count_items($conversation->id),
            'role' => $conversation->agent_role
        ));
    }

    private function detect_source($input) {
        if (isset($input['source'])) {
            return sanitize_text_field($input['source']);
        }

        if (isset($input['From'])) {
            return 'twilio';
        }

        if (isset($input['entry'])) {
            return 'meta';
        }

        return 'widget';
    }

    private function get_or_create_conversation($session_id, $phone, $source) {
        if ($session_id) {
            $existing = $this->conversation->get_by_session($session_id);
            if ($existing && $existing->status === 'active') {
                $this->conversation->update_activity($existing->id);
                return $existing;
            }
        }

        if ($phone) {
            $conversations = $this->conversation->get_by_phone($phone);
            foreach ($conversations as $conv) {
                if ($conv->status === 'active') {
                    $this->conversation->update_activity($conv->id);
                    return $conv;
                }
            }
        }

        $role = get_option('ai_agent_default_role', 'advisor');

        $new_session_id = $session_id ?? uniqid('conv_', true);

        $id = $this->conversation->create(array(
            'session_id' => $new_session_id,
            'phone' => $phone,
            'agent_role' => $role
        ));

        return $this->conversation->get_by_session($new_session_id);
    }

    public function detect_intent($text) {
        $text_lower = strtolower($text);

        $greeting_patterns = array(
            'hola', 'hello', 'buenos dias', 'buenas tardes', 'buenas noches',
            'que tal', 'como estas', 'hey', 'oi', 'hi', 'buen día'
        );

        foreach ($greeting_patterns as $greeting) {
            if (strpos($text_lower, $greeting) === 0) {
                return 'greeting';
            }
        }

        if (preg_match('/\b(comprar|carrito|agregar|cart|buy|add)\b/i', $text)) {
            return 'purchase';
        }

        if (preg_match('/\b(precio|costo|cuant[ao]|cuánto)\b/i', $text)) {
            return 'price_inquiry';
        }

        if (preg_match('/\b(código|codigo|descuento|coupon|promo)\b/i', $text)) {
            return 'discount';
        }

        if (preg_match('/\b(producto|artículo|item|opciones)\b/i', $text)) {
            return 'product_browse';
        }

        if (preg_match('/\b(ver.carrito|ver carrito|mi carrito)\b/i', $text)) {
            return 'view_cart';
        }

        if (preg_match('/\b(checkout|pagar|payment)\b/i', $text)) {
            return 'checkout';
        }

        if (preg_match('/\b(horario|atienden|abren|cierran|disponible)\b/i', $text)) {
            return 'business_hours';
        }

        return 'general';
    }

    private function should_show_greeting($conversation_id) {
        $messages = $this->message->get_by_conversation($conversation_id);

        if (count($messages) > 0) {
            return false;
        }

        return true;
    }

    private function get_greeting_message() {
        $hour = (int) date('H');

        $greeting_by_time = array(
            'morning' => array('Buenos días', 'Good morning', 'Buenos días! ¿En qué puedo ayudarte hoy?'),
            'afternoon' => array('Buenas tardes', 'Good afternoon', 'Buenas tardes! ¿En qué te puedo ayudar?'),
            'evening' => array('Buenas noches', 'Good evening', 'Buenas noches! ¿En qué puedo asistirte?')
        );

        if ($hour >= 6 && $hour < 12) {
            $time_key = 'morning';
        } elseif ($hour >= 12 && $hour < 20) {
            $time_key = 'afternoon';
        } else {
            $time_key = 'evening';
        }

        $greeting = $greeting_by_time[$time_key][2];

        $custom_greeting = get_option('ai_agent_greeting_primary', '');
        if (!empty($custom_greeting)) {
            $greeting = $custom_greeting;
        }

        $campaign = $this->check_active_campaign();
        if ($campaign) {
            $greeting .= "\n\n🎉 " . $campaign->message;
        }

        return $greeting;
    }

    private function is_within_business_hours() {
        $enabled = get_option('ai_agent_business_hours_enabled', 'no');
        if ($enabled !== 'yes') {
            return true;
        }

        $schedule = get_option('ai_agent_business_hours', array());
        if (empty($schedule)) {
            return true;
        }

        $day_of_week = strtolower(date('l'));
        $current_time = date('H:i');

        if (!isset($schedule[$day_of_week])) {
            return false;
        }

        $day_schedule = $schedule[$day_of_week];

        if ($day_schedule['enabled'] !== 'yes') {
            return false;
        }

        $start = isset($day_schedule['start']) ? $day_schedule['start'] : '09:00';
        $end = isset($day_schedule['end']) ? $day_schedule['end'] : '18:00';

        return ($current_time >= $start && $current_time <= $end);
    }

    private function get_offline_message() {
        $default = "Gracias por contactingarnos. Actualmente estamos fuera de nuestro horario de atención. Nuestro horario es de Lun-Vie 9:00-18:00. ¿Deseas dejarnos un mensaje?";

        $message = get_option('ai_agent_offline_message', $default);

        $business_hours = get_option('ai_agent_business_hours_display', 'Lun-Vie 9:00-18:00');
        $message = str_replace('{horario}', $business_hours, $message);

        return $message;
    }

    private function check_active_campaign() {
        $campaigns = $this->campaign->get_active();

        if (empty($campaigns)) {
            return null;
        }

        return $campaigns[0];
    }

    private function validate_discount_code($code) {
        $campaign = $this->campaign->get_by_code($code);

        if (!$campaign) {
            return array(
                'valid' => false,
                'message' => 'El código ingresado no es válido.'
            );
        }

        $this->campaign->increment_conversion($campaign->id);

        $discount_text = '';
        if ($campaign->discount_type === 'percentage') {
            $discount_text = $campaign->discount_value . '% de descuento';
        } elseif ($campaign->discount_type === 'fixed') {
            $discount_text = '$' . number_format($campaign->discount_value, 2) . ' de descuento';
        }

        return array(
            'valid' => true,
            'message' => "¡Código aplicado! {$discount_text}",
            'campaign' => $campaign
        );
    }

    private function build_system_prompt($conversation) {
        $role = $conversation->agent_role;

        $base_prompt = "Eres un asistente virtual inteligente. ";

        if ($role === 'vendor') {
            $base_prompt = "Eres un vendedor experto. Tu objetivo es ayudar al cliente a encontrar productos, agregar items al carrito y completar la compra. ";
            $base_prompt .= "Cuando el cliente muestre interés en un producto, ofrece agregar al carrito. ";
            $base_prompt .= "Cuando haya productos en el carrito, pregunta si desea proceder al checkout. ";
            $base_prompt .= "Puedes generar links de pago directos. ";
        } elseif ($role === 'advisor') {
            $base_prompt = "Eres un asesor experto. Tu objetivo es responder preguntas, dar información y ayudar al cliente. ";
            $base_prompt .= "Brinda información útil y detallada. ";
        }

        $base_prompt .= "Responde de manera amigable, concisa y en el mismo idioma del usuario. ";

        $has_woocommerce = class_exists('WooCommerce');
        if ($has_woocommerce) {
            $base_prompt .= "\n\nHay WooCommerce activo. Tienes acceso a información de productos, precios y puedes generar links de compra.";
        }

        $hour = (int) date('H');
        $within_hours = $this->is_within_business_hours();

        if (!$within_hours) {
            $base_prompt .= "\n\nIMPORTANTE: Actualmente el negocio está cerrado. Si el usuario pregunta sobre atención o horarios, indícalo claramente.";
        }

        return $base_prompt;
    }

    private function build_context($query, $conversation) {
        $context_parts = array();

        $relevant = $this->knowledge_base->find_relevant_context($query, 5);
        $context_text = $this->knowledge_base->format_context_for_llm($relevant);

        if (!empty($context_text)) {
            $context_parts[] = "Información del sitio:\n" . $context_text;
        }

        if ($conversation->agent_role === 'vendor' && class_exists('WooCommerce')) {
            $fiche = new AI_Agent_Product_Fiche();
            $products = $fiche->search($query, 5);

            if (!empty($products)) {
                $product_context = "Productos disponibles:\n";
                foreach ($products as $p) {
                    $price_text = $p->sale_price ? " (OFERTA: {$p->sale_price})" : " ({$p->price})";
                    $product_context .= "- {$p->name}{$price_text}\n";
                    if ($p->short_description) {
                        $product_context .= "  {$p->short_description}\n";
                    }
                    $product_context .= "  SKU: {$p->sku} | ID: {$p->product_id}\n\n";
                }
                $context_parts[] = $product_context;
            }
        }

        return implode("\n\n", $context_parts);
    }

    private function process_response($response, $conversation, $intent) {
        $response = $this->handle_cart_commands($response, $conversation, $intent);

        $response = $this->handle_discount_codes($response);

        return $response;
    }

    private function handle_cart_commands($response, $conversation, $intent) {
        if (preg_match_all('/\[ADD_TO_CART:(\d+):(\d+)\]/', $response, $matches)) {
            foreach ($matches[1] as $index => $product_id) {
                $quantity = isset($matches[2][$index]) ? $matches[2][$index] : 1;

                if ($conversation->agent_role !== 'vendor') {
                    continue;
                }

                $this->cart->add_item($conversation->id, $product_id, $quantity);

                $product = wc_get_product($product_id);
                if ($product) {
                    $response = str_replace(
                        "[ADD_TO_CART:{$product_id}:{$quantity}]",
                        "",
                        $response
                    );
                    $response .= "\n\n✅ *Producto agregado al carrito:* {$product->get_name()}";
                }
            }
        }

        if (preg_match_all('/\[BUY_NOW:(\d+)\]/', $response, $matches)) {
            foreach ($matches[1] as $product_id) {
                if ($conversation->agent_role !== 'vendor') {
                    continue;
                }

                $this->cart->add_item($conversation->id, $product_id, 1);

                $checkout_url = $this->cart->get_checkout_url($conversation->id);

                $response = str_replace(
                    "[BUY_NOW:{$product_id}]",
                    "\n\n👉 [完成购买]({$checkout_url})",
                    $response
                );
            }
        }

        if ($intent === 'view_cart' || strpos(strtolower($response), 'ver carrito') !== false) {
            $items = $this->cart->get_by_conversation($conversation->id);

            if (!empty($items)) {
                $cart_text = "\n\n🛒 *Tu Carrito:*\n";
                $total = 0;

                foreach ($items as $item) {
                    $price = floatval($item->price_at_add);
                    $subtotal = $price * $item->quantity;
                    $total += $subtotal;

                    $cart_text .= "- {$item->product_name} (x{$item->quantity}) - $" . number_format($subtotal, 2) . "\n";
                }

                $cart_text .= "\n*Total: $" . number_format($total, 2) . "*\n\n";

                $checkout_url = $this->cart->get_checkout_url($conversation->id);
                $cart_text .= "👉 [Proceder al pago]({$checkout_url})";

                $response .= $cart_text;
            }
        }

        if ($intent === 'checkout' || preg_match('/\b(pagar|checkout|terminar compra)\b/i', $response)) {
            $items = $this->cart->get_by_conversation($conversation->id);

            if (!empty($items) && $conversation->agent_role === 'vendor') {
                $checkout_url = $this->cart->get_checkout_url($conversation->id);
                $response = preg_replace(
                    '/(pagar|checkout|terminar compra)/i',
                    "[Proceder al pago]({$checkout_url})",
                    $response
                );
            }
        }

        return $response;
    }

    private function handle_discount_codes($response) {
        if (preg_match_all('/\[APPLY_CODE:([A-Z0-9]+)\]/', $response, $matches)) {
            foreach ($matches[1] as $code) {
                $result = $this->validate_discount_code($code);

                $response = str_replace(
                    "[APPLY_CODE:{$code}]",
                    $result['message'],
                    $response
                );
            }
        }

        if (preg_match('/\b(código|codigo|descuento|promo)\b/i', $response)) {
            if (preg_match('/[A-Z0-9]{4,}/i', $response, $code_match)) {
                $result = $this->validate_discount_code($code_match[0]);
                if ($result['valid']) {
                    $response = preg_replace(
                        '/[A-Z0-9]{4,}/i',
                        "*{$code_match[0]}* (aplicado)",
                        $response
                    );
                }
            }
        }

        return $response;
    }

    private function get_conversation_history($conversation_id, $limit = 5) {
        $messages = $this->message->get_by_conversation($conversation_id);

        $messages = array_slice($messages, -$limit);

        $history = array();
        foreach ($messages as $msg) {
            $history[] = array(
                'role' => $msg->role,
                'content' => $msg->content
            );
        }

        return $history;
    }

    private function save_message($conversation_id, $role, $content, $tokens = 0, $model = '', $intent = '', $context = '') {
        $this->message->create(array(
            'conversation_id' => $conversation_id,
            'role' => $role,
            'content' => $content,
            'tokens_used' => $tokens,
            'model_used' => $model,
            'intent_detected' => $intent,
            'context_used' => $context
        ));
    }

    private function format_response($message, $conversation, $status, $extra = array()) {
        $response = array(
            'success' => ($status !== 'error'),
            'message' => $message,
            'session_id' => $conversation->session_id,
            'conversation_id' => $conversation->id,
            'status' => $status,
            'agent_role' => $conversation->agent_role
        );

        if (isset($extra['intent'])) {
            $response['intent'] = $extra['intent'];
        }

        if (isset($extra['cart_items']) && $extra['cart_items'] > 0) {
            $response['cart_items'] = $extra['cart_items'];
            $response['cart_url'] = $this->cart->get_checkout_url($conversation->id);
        }

        return $response;
    }

    private function estimate_tokens($messages, $response) {
        $total_chars = 0;

        foreach ($messages as $msg) {
            $total_chars += strlen($msg['content']);
        }

        $total_chars += strlen($response);

        $tokens = (int) ($total_chars / 4);

        return max($tokens, 100);
    }

    public function set_role($conversation_id, $role) {
        if (!in_array($role, array('vendor', 'advisor', 'both'))) {
            return false;
        }

        $this->conversation->update_role($conversation_id, $role);
        return true;
    }

    public function close_conversation($conversation_id) {
        $this->conversation->close($conversation_id);
    }

    public function get_conversation_details($session_id) {
        $conversation = $this->conversation->get_by_session($session_id);

        if (!$conversation) {
            return null;
        }

        $messages = $this->message->get_by_conversation($conversation->id);
        $cart_items = $this->cart->get_by_conversation($conversation->id);

        return array(
            'conversation' => $conversation,
            'messages' => $messages,
            'cart' => $cart_items,
            'cart_total' => $this->cart->get_total($conversation->id)
        );
    }
}