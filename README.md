# AI Agent Chatbot

> Plugin de WordPress para crear un agente conversacional con IA, integración nativa con WooCommerce (consulta de pedidos, alta de clientes, carrito), base de conocimiento vectorial, soporte multi-LLM (OpenAI, Anthropic, DeepSeek, Kimi, MiniMax, Ollama), webhooks para WhatsApp/Twilio/Meta, streaming SSE y panel de auditoría.

**Versión:** 1.1.0 · **WordPress:** ≥ 6.0 · **PHP:** ≥ 8.1 · **Licencia:** GPL-2.0-or-later

---

## Índice

1. [Características](#características)
2. [Instalación rápida](#instalación-rápida)
3. [Arquitectura](#arquitectura)
4. [Configuración](#configuración)
   - [LLM y modelos](#1-llm-y-modelos)
   - [Base de conocimiento](#2-base-de-conocimiento)
   - [WooCommerce — tools en vivo](#3-woocommerce--tools-en-vivo)
   - [Idiomas](#4-idiomas)
   - [Webhook (WhatsApp / Twilio / Meta)](#5-webhook-whatsapp--twilio--meta)
   - [Comportamiento del agente](#6-comportamiento-del-agente)
   - [Apariencia del widget](#7-apariencia-del-widget)
   - [Streaming SSE](#8-streaming-sse)
5. [Seguridad](#seguridad)
6. [Privacidad / GDPR](#privacidad--gdpr)
7. [Panel de auditoría](#panel-de-auditoría)
8. [Hooks y filtros](#hooks-y-filtros)
9. [Constantes de wp-config](#constantes-de-wp-config)
10. [Comandos del LLM (tags)](#comandos-del-llm-tags)
11. [Esquema de base de datos](#esquema-de-base-de-datos)
12. [Tests](#tests)
13. [Changelog](#changelog)

---

## Características

### 🤖 Multi-LLM
- **Occidentales:** OpenAI (GPT-4o, 4o-mini, 4-Turbo), Anthropic (Claude Sonnet 4.6, Opus 4.7, Haiku 4.5).
- **Chinos (中国):** DeepSeek (V3, R1), Kimi/Moonshot (v1-8k/32k/128k, K2), MiniMax (Text-01, abab6.5).
- **Locales:** Ollama (llama3, mistral, etc.) sobre tu propio servidor.
- Arquitectura **OpenAI-compatible** unificada para 4 de los 5 proveedores remotos: añadir un nuevo provider es declarar URL + modelos.

### 🧠 Base de conocimiento
- **Local (WordPress):** páginas seleccionadas + tipo personalizado `ai_agent_knowledge` + productos WooCommerce.
- **Embeddings persistentes**: se calculan al guardar contenido (`save_post`, `woocommerce_update_product`) y se reutilizan. **Sin recalcular en cada consulta.**
- **Vectorial externa:** Pinecone, PostgreSQL + pgvector, Supabase, o API custom.
- Fallback automático a búsqueda LIKE si los embeddings no están listos.

### 🛒 WooCommerce — datos en vivo
Capa de "tools" que el LLM puede invocar (sin API REST externa, usa funciones internas `wc_get_order`, `WC_Customer`):

| Tag | Descripción |
|---|---|
| `[ORDER_STATUS:123]` | Estado de pedido (con verificación opcional por email). |
| `[MY_ORDERS:email:x@y.com]` | Lista de pedidos por email. |
| `[MY_ORDERS:phone:+34xxx]` | Lista de pedidos por teléfono (útil en WhatsApp). |
| `[CREATE_CUSTOMER:{...json...}]` | Alta de cliente con dirección de envío. |
| `[ADD_TO_CART:id:qty]` | Añadir al carrito (validado, role-checked). |
| `[BUY_NOW:id]` | Comprar ya — añade y devuelve URL de checkout. |

Para **info estática** (precios, descripciones, catálogo) usa la KB vectorial — más eficiente.

### 🌐 Idiomas (8 soportados)
Español, inglés, portugués, francés, alemán, italiano, chino simplificado, japonés.
- Detección automática (palabras-señal + bloques Unicode CJK).
- Selector en Settings para **limitar** los idiomas en que el agente puede responder.
- Si el usuario escribe en un idioma no permitido, el agente lo explica amablemente en el idioma por defecto.

### 📱 Webhooks (multi-canal)
- `POST /wp-json/ai-agent/v1/webhook` — endpoint único con firma HMAC.
- Soporta firmas oficiales de **Twilio** (HMAC-SHA1 sobre URL + params ordenados), **Meta** (`X-Hub-Signature-256`) y **genérica** (`X-Webhook-Signature` SHA-256).
- Detección automática de proveedor por headers/body.

### ⚡ Streaming SSE
- Endpoint dedicado con chunked transfer encoding.
- Headers anti-buffering (`X-Accel-Buffering: no`).
- Compatible con todos los providers OpenAI-compatibles + Anthropic.
- Fallback transparente a AJAX si el navegador no soporta `EventSource`.

### 🎭 Comportamiento del agente
- **Roles:** asesor (solo info), vendedor (carrito + checkout), ambos.
- **Horarios de atención:** por día, con mensaje fuera de horario.
- **Campañas y códigos de descuento:** vigencia, % o $ fijo, tracking de conversiones.
- **Intent detection:** greeting, purchase, price_inquiry, discount, view_cart, checkout, business_hours.
- **Session cache** (TTL configurable, 15-30 min) para reducir llamadas al vector DB.

### 📊 Dashboard y métricas
- Conversaciones, mensajes, tokens, costo USD (estimación por modelo).
- Métricas diarias últimas 30 días.
- Consumo por modelo LLM.
- Conversaciones recientes.
- Estado de indexación y scheduler.

### 🔒 Seguridad nativa
- Verificación HMAC obligatoria en webhooks (con `hash_equals` timing-safe).
- Rate limiting por IP en endpoints del chat (15 req/min, filtrable).
- Validación estricta de comandos del LLM (anti prompt-injection).
- `ABSPATH` guard en todos los archivos.
- Consultas SQL preparadas (incluido pgvector con `pg_query_params`).
- Soporte de API keys vía constantes de `wp-config.php` (no en BD).
- Auditoría completa de invocaciones a WC tools.

### 🛡️ Privacidad
- Hooks nativos `wp_privacy_personal_data_exporters` y `wp_privacy_personal_data_erasers`.
- Retención configurable de conversaciones antiguas vía cron.
- Redacción automática de campos sensibles en el log de auditoría.

---

## Instalación rápida

```bash
cd wp-content/plugins/
git clone https://github.com/jgcastanedas/plugin-wordpress-agent-ai.git
# o copia el zip
```

1. Activa el plugin en **Plugins → Plugins instalados**.
2. Ve a **AI Agent → Configuración** y elige proveedor LLM + API key.
3. (Opcional) Añade en `wp-config.php`:
   ```php
   define('AI_AGENT_OPENAI_KEY',   'sk-...');
   define('AI_AGENT_ANTHROPIC_KEY','sk-ant-...');
   define('AI_AGENT_DEEPSEEK_KEY', 'sk-...');
   ```
4. Marca las páginas a incluir en la KB.
5. **AI Agent → Dashboard → "Forzar Re-index"** para sembrar el índice.
6. Visita el front-end: el widget aparece en la esquina configurada.

> En la primera activación, el plugin auto-genera un **webhook secret** de 32 chars en `wp_options.ai_agent_webhook_secret`.

---

## Arquitectura

```
plugin-wordpress-agent-ai/
├── plugin-wordpress-agent-ai.php          # Entry, activación, env vars
├── composer.json · phpunit.xml.dist        # Dev tooling
│
├── includes/
│   ├── class-ai-agent-utils.php            # Detect lang, cosine sim (puro)
│   ├── class-ai-agent-database.php         # Esquemas + accessor classes
│   ├── class-ai-agent-settings.php         # Admin UI de config
│   ├── class-ai-agent-knowledge-base.php   # KB local + embeddings cacheados
│   ├── class-ai-agent-index.php            # Indexador (knowledge_index table)
│   ├── class-ai-agent-kb-adapter.php       # Pinecone / Postgres / Supabase / Custom
│   ├── class-ai-agent-session-cache.php    # Cache TTL 15-30min
│   ├── class-ai-agent-scheduler.php        # Crons + hooks save_post
│   ├── class-ai-agent-llm-provider.php     # OpenAI-compat + Anthropic + Ollama
│   ├── class-ai-agent-stream.php           # SSE chunked transfer
│   ├── class-ai-agent-agent.php            # Orquestador (intent, role, lang, tools)
│   ├── class-ai-agent-woocommerce.php      # Lectura productos
│   ├── class-ai-agent-wc-tools.php         # Tools transaccionales (pedidos, clientes)
│   ├── class-ai-agent-tool-audit.php       # Log + admin page
│   ├── class-ai-agent-privacy.php          # GDPR exporters/erasers
│   ├── class-ai-agent-webhook.php          # REST endpoint + firma HMAC
│   ├── class-ai-agent-widget.php           # Front-end render + assets
│   └── class-ai-agent-dashboard.php        # Métricas admin
│
├── admin/class-ai-agent-admin.php          # Submenús admin
├── public/class-ai-agent-public.php        # AJAX endpoint + rate limit
├── assets/
│   ├── css/widget-base.css
│   └── js/widget.js                        # EventSource + AJAX fallback
└── tests/unit/                             # PHPUnit 10
    ├── UtilsTest.php
    ├── LlmProviderTest.php
    └── WebhookSignatureTest.php
```

---

## Configuración

### 1. LLM y modelos

**AI Agent → Configuración → LLM**

| Proveedor | Endpoint | Modelos disponibles | Streaming |
|---|---|---|---|
| OpenAI | `api.openai.com/v1` | gpt-4o, gpt-4o-mini, gpt-4-turbo, gpt-3.5-turbo | ✅ |
| Anthropic | `api.anthropic.com/v1` | claude-sonnet-4-6, claude-opus-4-7, claude-haiku-4-5 | ✅ |
| DeepSeek | `api.deepseek.com/v1` | deepseek-chat, deepseek-reasoner | ✅ |
| Kimi (Moonshot) | `api.moonshot.ai/v1` | moonshot-v1-{8k,32k,128k}, kimi-k2-0905-preview | ✅ |
| MiniMax | `api.minimax.chat/v1` | MiniMax-Text-01, abab6.5-chat, abab6.5s-chat | ✅ |
| Ollama | configurable | cualquier modelo local | ❌ |

API keys: en el panel o mejor por constante en `wp-config.php` (ver sección [Constantes](#constantes-de-wp-config)).

### 2. Base de conocimiento

**Local:**
- Marca las páginas en **Configuración → Base de Conocimiento → Páginas para Base de Conocimiento**.
- Añade documentos en **AI Agent → Documentos** (custom post type `ai_agent_knowledge`).
- Los productos de WooCommerce se indexan automáticamente.

**Re-indexación:**
- Automática al guardar/borrar páginas (hook `save_post`) o productos (`woocommerce_update_product`).
- Manual desde **Dashboard → "Forzar Re-index"**.
- Cron horario revisa cambios con check de hash.

**Externa (Pinecone / Postgres / Supabase / Custom):** en **Configuración → Base de Conocimiento Externa**. Si el servicio falla y `Fallback a Local` está activo, se usa la KB local automáticamente.

### 3. WooCommerce — tools en vivo

**Configuración → Comportamiento del Agente → Verificación de pedidos**

- **Desactivado (por defecto):** cualquiera con un nº de pedido puede ver el estado básico (estado, fecha, total). *Inseguro — IDs son secuenciales.*
- **Activado (recomendado):** el agente exige email coincidente con el del pedido antes de mostrar datos.

Tanto en el widget como en WhatsApp/Twilio, si el usuario está autenticado (cookie WP o teléfono firmado por Twilio), la verificación es automática.

### 4. Idiomas

**Configuración → Comportamiento del Agente → Idiomas permitidos**

- **Sin marcar nada:** el agente detecta y responde en el idioma del usuario.
- **Marcar uno solo:** el agente responde siempre en ese idioma.
- **Marcar varios:** responde en el idioma del usuario si está permitido; si no, en el "por defecto".

Idiomas soportados: `es`, `en`, `pt`, `fr`, `de`, `it`, `zh`, `ja`.

### 5. Webhook (WhatsApp / Twilio / Meta)

URL del webhook:
```
https://tu-sitio.com/wp-json/ai-agent/v1/webhook
```

**Twilio:**
1. Twilio Console → Numbers → tu número → Webhooks.
2. URL anterior, método **POST**.
3. Guarda en `wp_options.ai_agent_twilio_auth_token` (o usa el `ai_agent_webhook_secret` como token).

**Meta WhatsApp Business:**
1. App → WhatsApp → Configuration → Callback URL.
2. Verify Token = `ai_agent_webhook_secret`.
3. Las firmas `X-Hub-Signature-256` se validan automáticamente.

**Genérico (cualquier sistema):**
- Header: `X-Webhook-Signature: <sha256-hmac>`.
- Calcular HMAC-SHA256 del body con `ai_agent_webhook_secret`.

### 6. Comportamiento del agente

**Configuración → Comportamiento del Agente:**

- **Rol:** asesor / vendedor / ambos.
- **Permitir checkout:** si lo desactivas, el agente no puede emitir tags de carrito (modo solo asesoría).
- **Mensaje de saludo / fuera de horario.**
- **Horarios de atención:** por día con hora inicio/fin.
- **Campañas y promociones:** JSON con código, descuento, vigencia.

### 7. Apariencia del widget

**Configuración → Personalización del Widget:**

- Posición (4 esquinas), tamaño botón (40-100 px), dimensiones (280-600 × 300-800 px).
- Logo: predeterminado, icono del sitio, o personalizado (URL/upload).
- Colores: primario, secundario, botón, ícono, burbujas usuario/bot, textos.
- Tipografía: familia (sistema o Google Fonts) y tamaño (11-20 px).
- Border radius, espaciado entre mensajes.

### 8. Streaming SSE

**Configuración → LLM → "Activar streaming SSE"**

Requisitos del servidor:
- **Nginx:** `proxy_buffering off;` en el `location` del plugin, o respetar header `X-Accel-Buffering: no`.
- **Apache + mod_deflate:** deshabilitar gzip para `/wp-admin/admin-ajax.php`.
- **PHP-FPM:** `output_buffering=Off` o el plugin fuerza `ob_end_flush()` por defecto.
- **Cloudflare:** considerar desactivar el proxy para esa ruta.

Si no se cumplen, la respuesta llega completa al final (no rompe, solo no es progresiva).

---

## Seguridad

| Mecanismo | Implementación |
|---|---|
| Firma webhook HMAC | SHA-256 + `hash_equals` timing-safe |
| Twilio signature | HMAC-SHA1 sobre URL + params ordenados (algoritmo oficial) |
| Meta signature | `X-Hub-Signature-256` |
| Rate limiting | 15 req/min/IP en `/chat` y `/stream` (filtrable) |
| Prompt injection | Validación de `product_id` con `wc_get_product` + `is_purchasable()` + role check |
| SQL injection | `wpdb::prepare()` siempre + `pg_query_params()` en Postgres |
| Conexión Postgres | `sslmode=require` forzado por defecto |
| `ABSPATH` guard | En todos los archivos PHP |
| Credenciales | Constantes en `wp-config.php` con prioridad sobre BD |
| Capability checks | `manage_options` en endpoints admin y test |

---

## Privacidad / GDPR

El plugin se integra con las herramientas nativas de WordPress:

- **Tools → Export Personal Data** con email del usuario exporta:
  - Conversaciones (session ID, teléfono, IP, user-agent, fechas).
  - Mensajes completos (rol, contenido, fecha).
- **Tools → Erase Personal Data** borra conversaciones, mensajes y carrito.
- La búsqueda se hace por email del usuario WP O por teléfono directo.
- Retención: por defecto 90 días para conversaciones cerradas; filtrable con `ai_agent_retention_days`.

---

## Panel de auditoría

**AI Agent → Auditoría**

Cada invocación del agente a una tool de WooCommerce queda registrada:

- **Resumen últimos 7 días:** total / OK / errores por tipo de tool.
- **Últimas 100 llamadas:** fecha, tool, args (con campos sensibles redactados), resultado, sesión, IP.
- Filtro por tool.

Útil para detectar abuso (mismo IP iterando `ORDER_STATUS`), monitorear éxito de creación de clientes, o diagnosticar respuestas raras del LLM.

---

## Hooks y filtros

```php
// Modificar prompt del sistema antes de enviarlo al LLM
add_filter('ai_agent_system_prompt', function($prompt, $context) {
    return $prompt . "\n\nNota: tu personalidad es formal.";
}, 10, 2);

// Subir / bajar el límite de rate
add_filter('ai_agent_rate_limit_per_minute', fn() => 30);

// Cambiar días de retención de conversaciones
add_filter('ai_agent_retention_days', fn() => 30);

// Personalizar payload del Custom KB adapter
add_filter('ai_agent_custom_kb_search_payload', function($payload, $config) {
    $payload['custom_field'] = 'value';
    return $payload;
}, 10, 2);
```

---

## Constantes de wp-config

Las constantes tienen **prioridad sobre la BD**. Recomendado para producción:

```php
// API keys
define('AI_AGENT_OPENAI_KEY',    'sk-...');
define('AI_AGENT_ANTHROPIC_KEY', 'sk-ant-...');
define('AI_AGENT_DEEPSEEK_KEY',  'sk-...');
define('AI_AGENT_KIMI_KEY',      'sk-...');
define('AI_AGENT_MINIMAX_KEY',   'eyJ...');
```

---

## Comandos del LLM (tags)

El LLM puede emitir estos tags en sus respuestas. El plugin los detecta, ejecuta la acción real y reemplaza el tag por el resultado. **Nunca ejecuta acciones que el role del agente no permita.**

| Tag | Acción | Requiere |
|---|---|---|
| `[LINK_PAGO:url]` | Marca URL como link de pago | — |
| `[ADD_TO_CART:product_id:qty]` | Añade producto al carrito de sesión | Role vendor, producto publish + purchasable |
| `[BUY_NOW:product_id]` | Añade y devuelve URL de checkout | Role vendor |
| `[APPLY_CODE:CODIGO]` | Valida y aplica código de descuento | Campaña activa |
| `[ORDER_STATUS:123]` | Estado de pedido (info básica) | — |
| `[ORDER_STATUS:123:email]` | Estado de pedido con info completa | Email coincide |
| `[MY_ORDERS:email:x@y.com]` | Lista de pedidos por email | WooCommerce activo |
| `[MY_ORDERS:phone:+34xxx]` | Lista de pedidos por teléfono | WooCommerce activo |
| `[CREATE_CUSTOMER:{...json...}]` | Crea cliente con dirección | Email no existente |

---

## Esquema de base de datos

| Tabla | Filas típicas | Propósito |
|---|---|---|
| `ai_agent_conversations` | 1 por sesión | session_id, phone, IP, status, role |
| `ai_agent_messages` | N por conversación | role, content, tokens, cost, intent |
| `ai_agent_cart` | N por conversación | product_id, qty, price snapshot |
| `ai_agent_knowledge_index` | 1 por item indexado | source_type, title, content, **embedding** |
| `ai_agent_product_fiches` | 1 por producto WC | sku, price, embedding |
| `ai_agent_campaigns` | 1 por campaña | code, mensaje, descuento, fechas |
| `ai_agent_metrics_daily` | 1 por día | agregado de conversaciones/tokens/coste |
| `ai_agent_token_usage` | 1 por día y modelo | desglose por modelo LLM |
| `ai_agent_session_cache` | 1 por sesión activa | contexto cacheado, TTL |
| `ai_agent_tool_audit` | 1 por invocación a WC tool | tool_name, args, resultado, IP |

Cleanup automático con cron diario; conversaciones cerradas con más de 90 días se eliminan.

---

## Tests

Suite PHPUnit unitaria (sin necesidad de WP corriendo):

```bash
composer install
vendor/bin/phpunit
```

```
PHPUnit 10.5.63
..............................   30 / 30 (100%)
Time: 00:00.018, Memory: 8.00 MB

OK (30 tests, 56 assertions)
```

Cobertura actual: `AI_Agent_Utils` (detección de 8 idiomas, cosine similarity defensivo), `AI_Agent_LLM_Provider` (catálogo de proveedores, env-var precedence, URL/modelo lookup), `AI_Agent_Webhook` (firma Twilio oficial, tamper detection, timing-safe).

---

## Changelog

### 1.1.0 — Auditoría, seguridad y multi-LLM

**🔴 Críticos resueltos**
- Plugin no arrancaba: `AI_Agent_Index` movida a su propio archivo.
- Menú admin no aparecía: `AI_Agent_Settings/Admin/Dashboard` ahora se instancian correctamente.
- Doble llamada al LLM por mensaje (handler AJAX duplicado).
- `AI_Agent_Agent` ahora se invoca realmente (intent, role, business hours, campaigns).
- Flujo de carrito `?ai_agent_cart=` se intercepta y reconstruye en WooCommerce.

**🔒 Seguridad**
- Reescrita verificación de firma webhook (la lógica estaba invertida — aceptaba sin verificar).
- Endpoint `/webhook/test` ahora requiere `manage_options`.
- SQL injection en adapter Postgres corregido con `pg_query_params`.
- Rate limiting 15 req/min/IP.
- Validación estricta de comandos del LLM (anti prompt-injection).
- `ABSPATH` guards en todos los archivos.
- Conexión Postgres con `sslmode=require`.
- Webhook secret se genera en activación, no en cada render.

**🇨🇳 Nuevos providers chinos**
- DeepSeek (V3, R1).
- Kimi / Moonshot (v1-8k/32k/128k, K2).
- MiniMax (Text-01, abab6.5).
- Arquitectura OpenAI-compatible unificada.

**🛒 WooCommerce tools en vivo**
- `[ORDER_STATUS]`, `[MY_ORDERS]`, `[CREATE_CUSTOMER]` con verificación de cliente.
- Toggle "Exigir email para estado de pedido" en Settings.
- Capa `AI_Agent_WC_Tools` separada de la KB vectorial.

**🌐 Idiomas**
- Detección de 8 idiomas (incluido chino y japonés por bloques Unicode).
- Selector de idiomas permitidos en Settings.
- Fallback al idioma por defecto cuando el del usuario no está permitido.

**⚡ Performance**
- Embeddings persistentes en `knowledge_index` (ya no recalcula en cada query).
- Hooks `save_post` / `woocommerce_update_product` para invalidación incremental.

**📊 Auditoría y privacidad**
- Panel **AI Agent → Auditoría** con log de invocaciones a WC tools.
- Hooks GDPR `wp_privacy_personal_data_exporters` / `_erasers`.
- Retención configurable.

**🎨 UX**
- Streaming SSE (chunked transfer) para los 5 providers remotos.
- Session ID persistido en `sessionStorage` para mantener contexto entre recargas.
- Models 2026: Claude Sonnet 4.6, Opus 4.7, Haiku 4.5; selector OpenAI en UI.
- Anthropic multi-turn arreglado (antes solo enviaba el último mensaje).

**🧪 Tooling**
- `composer.json` + PHPUnit 10.5 + tests unitarios (30 tests).
- Clase utilitaria `AI_Agent_Utils` 100% pura y testeable.

### 1.0.2
- Session Cache integrado al Agente.

### 1.0.1
- Soporte KB externa (Pinecone, PostgreSQL, Supabase, Custom API).

### 1.0.0
- Versión inicial.

---

## Créditos

Desarrollado por **Julian Castañeda** ([jgcastanedas.com](https://jgcastanedas.com)).

## Licencia

GPL-2.0-or-later
