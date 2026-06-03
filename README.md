# AI Agent Chatbot Widget

Plugin de WordPress para crear un widget de chatbot con IA, totalmente personalizable, con base de conocimiento vectorial, integración WooCommerce y webhooks para WhatsApp, Twilio y Meta.

## Descripción

AI Agent Chatbot es un plugin de WordPress que permite crear un asistente virtual inteligente basado en IA. El chatbot puede responder preguntas basándose en el contenido de tu sitio web, productos de WooCommerce, y documentos personalizados que cargues.

El agente tiene comportamiento inteligente con roles configurables (asesor/vendedor), horarios de atención personalizados, campañas de descuento y carrito de compras integrado.

## Características

### 🤖 Inteligencia Artificial y Agente Inteligente

- **Selección de LLM**: Soporta OpenAI (GPT-4o, GPT-4 Turbo, GPT-3.5), Anthropic (Claude 3.5 Sonnet, Claude 3 Opus), y Ollama (local)
- **Base de conocimiento vectorial**: Selecciona páginas específicas de tu sitio para entrenar al agente
- **Embeddings optimizados**: Conversión de contenido a vectores usando OpenAI embeddings o TF-IDF local
- **Documentos personalizados**: Crea documentos de conocimiento adicionales desde el admin
- **Scheduler automático**: Indexación horaria automática para mantener la base de conocimiento actualizada
- **Límites de tokens configurables**: Control del contexto para optimizar costos

### 🎭 Comportamiento del Agente

- **Roles configurables**:
  - **Asesor**: Responde preguntas, da información, ayuda con dudas
  - **Vendedor**: Puede agregar productos al carrito y generar links de pago
  - **Ambos**: Combina asesoría y venta
- **Saludos personalizados**: Configura mensajes de saludo según hora del día
- **Horarios de atención**: Define días y horas de atención automática
- **Mensaje fuera de horario**: Respuesta automática cuando el negocio está cerrado
- **Campañas y promociones**: Mensajes personalizados con códigos de descuento
- **Validación de códigos**: Detecta y valida códigos de descuento en la conversación
- **Detección de intención**: greeting, purchase, price_inquiry, discount, product_browse, view_cart, checkout, business_hours, general

### 🛒 WooCommerce Integration

- **Fichas de productos optimizadas**: Descripciones comprimidas para reducir tokens
- **Información automática**: Nombre, precio, SKU, categorías, descripción corta
- **Generación de links de pago**: Carrito y checkout integrados en la conversación
- **Carrito persistente por sesión**: El carrito se mantiene durante la conversación
- **Búsqueda semántica de productos**: Encuentra productos por nombre o descripción

### 📱 Integraciones de Mensajería

- **Webhook REST**: Endpoint `/wp-json/ai-agent/v1/webhook` para integraciones externas
- **Twilio WhatsApp**: Recibir y responder mensajes de WhatsApp vía Twilio
- **Meta WhatsApp Business**: Integración con la API de WhatsApp Business de Meta
- **Generic Webhook**: Compatible con cualquier servicio que envíe webhooks POST
- **Identificación por sesión y teléfono**: Rastreo de conversaciones por session_id y phone number

### 📊 Dashboard y Métricas

- **Dashboard administrativo**: Vista general con estadísticas en tiempo real
- **Métricas diarias**: Conversaciones, mensajes, tokens y costos por día
- **Consumo por modelo LLM**: Desglose de uso por modelo (GPT-4, Claude, Ollama)
- **Gráficos de comportamiento**: Visualización de tendencias de uso (Chart.js)
- **Historial de conversaciones**: Registro completo de mensajes con contexto
- **Costo en dólares**: Estimación de costos basada en pricing de OpenAI/Anthropic

### 🎨 Personalización del Widget

- **Logo**: Icono predeterminado, icono del sitio, o logo personalizado
- **Colores**: Personalización completa de todos los elementos (header, botones, burbujas, texto)
- **Tipografía**: Familia de fuente y tamaño configurable
- **Dimensiones**: Ancho, alto, border radius, tamaño del botón, espaciado
- **Posición**: 4 posiciones disponibles (esquinas inferiores o superiores)
- **Mensajes**: Título del header, mensaje de bienvenida, placeholder del input

## Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                     AI AGENT PLUGIN                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │   ADMIN     │  │   PUBLIC    │  │     WEBHOOK         │ │
│  │  Settings   │  │   Widget    │  │  Twilio/Meta/Other  │ │
│  │  Dashboard  │  │   Frontend  │  │                     │ │
│  │  Knowledge  │  │             │  │                     │ │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘ │
│         │                │                    │             │
│  ┌──────┴────────────────┴────────────────────┴──────────┐  │
│  │                      AGENT                          │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │  │
│  │  │ Intent   │ │ Context  │ │  Cart    │ │Campaign│ │  │
│  │  │ Detection│ │ Builder  │ │ Manager  │ │ Handler│ │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └────────┘ │  │
│  └─────────────────────────┬──────────────────────────┘  │
│                            │                              │
│  ┌─────────────────────────┴──────────────────────────┐  │
│  │                 KNOWLEDGE BASE                     │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌────────────┐  │  │
│  │  │   Pages     │  │  Products   │  │  Documents │  │  │
│  │  │  (Indexed)  │  │  (Fiches)   │  │  (Custom)  │  │  │
│  │  └─────────────┘  └─────────────┘  └────────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
│                            │                              │
│  ┌─────────────────────────┴──────────────────────────┐  │
│  │                    LLM PROVIDER                    │  │
│  │       OpenAI  /  Anthropic  /  Ollama            │  │
│  └────────────────────────────────────────────────────┘  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Requisitos

- WordPress 6.0 o superior
- PHP 8.1 o superior
- WooCommerce (opcional, para integración de productos)
- API Key de OpenAI o Anthropic (opcional, para uso con sus servicios)

## Instalación

1. Descarga el plugin o clona el repositorio en `/wp-content/plugins/`
2. Activa el plugin desde el menú de Plugins de WordPress
3. Ve a **AI Agent** en el menú lateral de WordPress
4. Configura tu proveedor de LLM y agrega las API keys necesarias
5. Selecciona las páginas para la base de conocimiento
6. Configura el comportamiento del agente (rol, horarios, campañas)
7. Personaliza la apariencia del widget
8. Activa el widget desde la configuración

## Configuración

### Configuración de LLM

En la pestaña "LLM" puedes elegir entre:

| Proveedor | Modelos | Notas |
|-----------|---------|-------|
| OpenAI | GPT-4o, GPT-4 Turbo, GPT-3.5 | Requiere API key de OpenAI |
| Anthropic | Claude 3.5 Sonnet, Claude 3 Opus | Requiere API key de Anthropic |
| Ollama | Llama 3, Mistral, Codellama, etc. | Funciona localmente, no requiere API key |

### Base de Conocimiento

#### Selección de Páginas
1. Ve a **AI Agent > Configuración > Base de Conocimiento**
2. Selecciona las páginas que quieres incluir
3. Usa Ctrl/Cmd + click para selección múltiple

#### Documentos Personalizados
1. Ve a **AI Agent > Documentos**
2. Crea un nuevo documento de tipo `ai_agent_knowledge`
3. Agrega el contenido que quieras usar como contexto adicional

#### Límites de Tokens
- **Límite para Warning**: Avisa cuando el contexto se acerque a este límite
- **Máximo Tokens en Contexto**: Control del contexto máximo enviado al LLM

### Comportamiento del Agente

#### Rol del Agente
- **Asesor**: Responde preguntas, da información
- **Vendedor**: Agrega productos al carrito y genera links de pago
- **Ambos**: Combina ambas capacidades

#### Horarios de Atención
1. Activa "Horarios de Atención"
2. Configura los días y horarios activos
3. Personaliza el mensaje que se muestra fuera de horario

#### Campañas y Promociones
Formato JSON para campañas:
```json
[
  {
    "code": "VERANO20",
    "message": "¡20% de descuento en ropa de verano!",
    "discount_type": "percentage",
    "discount_value": 20,
    "start_date": "2024-06-01",
    "end_date": "2024-08-31",
    "active": true
  }
]
```

### Personalización del Widget

#### Colores Disponibles
- **Color Primario**: Header, indicador de escritura, enfoque en input
- **Color Secundario**: Gradiente del header
- **Botón**: Color de fondo del botón flotante
- **Icono del Botón**: Color del icono svg
- **Burbuja Usuario**: Color de fondo de tus mensajes
- **Burbuja Bot**: Color de fondo de los mensajes del bot
- **Texto Usuario**: Color del texto de tus mensajes
- **Texto Bot**: Color del texto de los mensajes del bot

#### Dimensiones Recomendadas
- **Ancho**: 350-420px (mínimo 280px, máximo 600px)
- **Alto**: 450-550px (mínimo 300px, máximo 800px)
- **Border Radius**: 12-20px para aspecto moderno
- **Tamaño del Botón**: 56-64px para buena visibilidad
- **Espaciado de Mensajes**: 12-20px para legibilidad

### Configuración de Webhook

#### URL del Webhook
```
https://tu-sitio.com/wp-json/ai-agent/v1/webhook
```

#### Integración con Twilio WhatsApp
1. Configura tu Webhook de Twilio pointing a la URL del webhook
2. Twilio enviará mensajes POST con `From` y `Body`

#### Integración con Meta WhatsApp Business
1. Configura el webhook de tu app de Meta Business
2. Asegúrate de que el payload contenga `entry[0].changes[0].value.messages`

## API REST

### Endpoint: Webhook

**URL**: `/wp-json/ai-agent/v1/webhook`

**Método**: POST

**Headers**:
- `Content-Type: application/json`
- `X-Webhook-Signature: <secret>` (opcional, para verificación HMAC)

**Payload para Twilio**:
```json
{
  "From": "+1234567890",
  "Body": "Hola, necesito información sobre...",
  "To": "+0987654321"
}
```

**Payload para Meta WhatsApp**:
```json
{
  "entry": [{
    "changes": [{
      "value": {
        "messages": [{
          "from": "1234567890",
          "text": { "body": "Hola" }
        }]
      }
    }]
  }]
}
```

**Respuesta**:
```json
{
  "source": "twilio",
  "success": true,
  "response": "¡Hola! ¿En qué puedo ayudarte?",
  "session_id": "conv_abc123...",
  "conversation_id": 1
}
```

## Base de Datos

El plugin crea las siguientes tablas:

| Tabla | Descripción |
|-------|-------------|
| `ai_agent_conversations` | Registro de conversaciones con session_id, phone, role, stats |
| `ai_agent_messages` | Mensajes individuales con tokens y costos |
| `ai_agent_product_fiches` | Fichas optimizadas de productos para LLM |
| `ai_agent_knowledge_index` | Índice de conocimiento vectorial |
| `ai_agent_campaigns` | Campañas activas con códigos de descuento |
| `ai_agent_cart` | Carrito de compras por conversación |
| `ai_agent_metrics_daily` | Métricas diarias agregadas |
| `ai_agent_token_usage` | Uso de tokens por modelo LLM |

## Scheduler

El plugin ejecuta automáticamente:

- **Indexación hourly**: Verifica cambios en páginas y productos cada hora
- **Métricas daily**: Actualiza métricas diarias a medianoche
- **Cleanup daily**: Limpia datos antiguos según retención configurada (90 días por defecto)

## Hooks y Filtros

### `ai_agent_system_prompt`
Permite modificar el prompt del sistema.

```php
add_filter('ai_agent_system_prompt', function($prompt, $context) {
    return $prompt . "\n\nEres un asistente especializado.";
}, 10, 2);
```

### `ai_agent_llm_response`
Permite modificar la respuesta del LLM.

```php
add_filter('ai_agent_llm_response', function($response) {
    return $response;
});
```

### `ai_agent_cart_url`
Permite modificar la URL del carrito.

```php
add_filter('ai_agent_cart_url', function($url, $conversation_id) {
    return $url;
}, 10, 2);
```

## FAQs

### ¿Puedo usar el plugin sin API key de OpenAI?
Sí, puedes usar Ollama para ejecutar modelos localmente sin costo en API keys.

### ¿Cómo funciona la búsqueda semántica?
El plugin convierte el contenido a vectores numéricos usando embeddings. Cuando un usuario hace una pregunta, se compara el vector de la pregunta con los vectores del contenido para encontrar el más similar.

### ¿Puedo personalizar los mensajes del bot?
Sí, desde AI Agent > Configuración puedes cambiar el mensaje de bienvenida, saludo, mensaje fuera de horario, y configurar campañas.

### ¿El plugin funciona con WhatsApp Business?
Sí, a través del webhook REST. Necesitarás configurar el webhook en tu app de Meta o en Twilio.

### ¿Puedo usar mi propio logo?
Sí, en la configuración del widget puedes subir un logo personalizado o usar el icono de tu sitio.

### ¿Cómo funciona el carrito de compras?
Cuando el agente tiene rol "vendedor" y detecta intención de compra, puede agregar productos al carrito. El carrito se mantiene por session_id y permite generar links de checkout directamente.

### ¿Qué métricas puedo ver?
Puedes ver: conversaciones totales, mensajes, tokens usados, costo en USD, consumo por modelo LLM, métricas diarias con gráficos, y historial de conversaciones.

## Changelog

### 1.0.0
- Versión inicial completa
- Soporte para OpenAI, Anthropic y Ollama
- Base de conocimiento vectorial con embeddings
- Integración con WooCommerce (fichas de productos, carrito, checkout)
- Widget de chat personalizable
- Sistema de webhooks para WhatsApp/Twilio/Meta
- Dashboard con métricas y gráficos
- Roles de agente (asesor/vendedor)
- Horarios de atención configurables
- Sistema de campañas y códigos de descuento
- Scheduler automático para indexación
- Historial de conversaciones completo
- Estimación de costos en dólares

## Créditos

Desarrollado por Julian Castaneda.

## Licencia

GPL v2 or later.