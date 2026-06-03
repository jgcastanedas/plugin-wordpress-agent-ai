# AI Agent Chatbot Widget

Plugin de WordPress para crear un widget de chatbot con IA, totalmente personalizable, con base de conocimiento vectorial, integración WooCommerce y webhooks para WhatsApp, Twilio y Meta.

## Descripción

AI Agent Chatbot es un plugin de WordPress que permite crear un asistente virtual inteligente basado en IA. El chatbot puede responder preguntas basándose en el contenido de tu sitio web, productos de WooCommerce, y documentos personalizados que cargues.

El agente tiene comportamiento inteligente con roles configurables (asesor/vendedor), horarios de atención personalizados, campañas de descuento y carrito de compras integrado.

---

## Características

### 🤖 Inteligencia Artificial y Agente Inteligente

- **Selección de LLM**: Soporta OpenAI (GPT-4o, GPT-4 Turbo, GPT-3.5), Anthropic (Claude 3.5 Sonnet, Claude 3 Opus), y Ollama (local)
- **Base de conocimiento vectorial**: Selecciona páginas específicas de tu sitio para entrenar al agente
- **Base de conocimiento externa**: Conexión a Pinecone, PostgreSQL + pgvector, o Supabase
- **Session Cache**: Respuestas rápidas con caché de contexto por sesión
- **Límites de tokens configurables**: Control del contexto para optimizar costos

### 🎭 Comportamiento del Agente

- **Roles configurables**: Asesor, Vendedor, o Ambos
- **Horarios de atención**: Define días y horas de atención automática
- **Campañas y promociones**: Mensajes personalizados con códigos de descuento
- **Detección de intención**: greeting, purchase, price_inquiry, discount, etc.

### 🛒 WooCommerce Integration

- **Fichas de productos optimizadas**: Descripciones comprimidas para reducir tokens
- **Generación de links de pago**: Carrito y checkout integrados en la conversación
- **Carrito persistente por sesión**: El carrito se mantiene durante la conversación

### 📱 Integraciones de Mensajería

- **Webhook REST**: Endpoint para integraciones con WhatsApp, Twilio y Meta
- **Identificación por sesión y teléfono**: Rastreo de conversaciones por session_id y phone

### 📊 Dashboard y Métricas

- **Dashboard administrativo**: Estadísticas en tiempo real
- **Métricas diarias**: Conversaciones, mensajes, tokens y costos por día
- **Consumo por modelo LLM**: Desglose de uso por modelo
- **Costo en dólares**: Estimación de costos basada en pricing

---

## Guía de Instalación y Configuración

### Paso 1: Instalación

1. Descarga el plugin desde GitHub o clona el repositorio
2. Copia la carpeta a `/wp-content/plugins/` de tu WordPress
3. Activa el plugin desde **Plugins > Plugins instalados**
4. Verás un nuevo menú **"AI Agent"** en el sidebar de WordPress

### Paso 2: Configurar LLM

1. Ve a **AI Agent > Configuración**
2. En la sección **"LLM"**:
   - Selecciona tu proveedor: **OpenAI**, **Anthropic**, u **Ollama**
   - Ingresa tu API Key correspondiente
   - Para Ollama, ingresa la URL (por defecto `http://localhost:11434`) y el modelo

### Paso 3: Configurar Base de Conocimiento

#### 3.1. Selección de Páginas

1. En **AI Agent > Configuración**, sección **"Base de Conocimiento"**
2. Mantén presionado **Ctrl/Cmd** y selecciona las páginas que quieres incluir
3. Las páginas seleccionadas se usarán como contexto para el agente

#### 3.2. Documentos Personalizados

1. Ve a **AI Agent > Documentos** (en el menú lateral)
2. Clic en **"Agregar nuevo"**
3. Escribe el título y contenido del documento
4. Publica - el contenido se indexará automáticamente

#### 3.3. Límites de Tokens

En la misma sección de Base de Conocimiento:
- **Límite para Warning**: Tokens antes de mostrar advertencia
- **Máximo Tokens en Contexto**: Control del contexto máximo enviado al LLM

### Paso 4: Configurar Base de Conocimiento Externa (Opcional)

Si quieres usar un servicio externo de búsqueda vectorial:

#### 4.1. Pinecone

1. Crea una cuenta en [pinecone.io](https://www.pinecone.io/)
2. Crea un proyecto nuevo
3. Copia el **API URL** y **API Key**
4. En el plugin, ve a **AI Agent > Configuración > Base de Conocimiento Externa**
5. Selecciona **"Pinecone"** como tipo de servicio
6. Ingresa el endpoint y API Key
7. Clic en **"Probar Conexión"**

#### 4.2. PostgreSQL + pgvector

1. Instala PostgreSQL con la extensión pgvector en tu servidor
2. Asegúrate de que PHP tenga la extensión `pg` habilitada
3. En el plugin, selecciona **"PostgreSQL + pgvector"**
4. Ingresa la conexión: `host:port/database`
5. Ejemplo: `postgres.example.com:5432/mydb`
6. Ingresa usuario y contraseña
7. Prueba la conexión

#### 4.3. Supabase

1. Crea un proyecto en [supabase.com](https://supabase.com/)
2. Ve a **Settings > API** y copia:
   - **Project ID**
   - **API Key** (anon/public)
3. En el plugin, selecciona **"Supabase"**
4. Ingresa el Project ID y API Key
5. Crea una tabla en Supabase llamada `ai_agent_knowledge` con este SQL:

```sql
CREATE TABLE ai_agent_knowledge (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    url TEXT,
    embedding TEXT,
    source_type TEXT DEFAULT 'wordpress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Función para buscar vectores (necesitarás crear una función RPC en Supabase)
CREATE FUNCTION match_knowledge(query_embedding TEXT, match_threshold FLOAT, match_count INT)
RETURNS TABLE(id INT, title TEXT, content TEXT, url TEXT, similarity FLOAT) AS $$
BEGIN
  RETURN QUERY
  SELECT
    a.id::INT,
    a.title,
    a.content,
    a.url,
    1 - (a.embedding <=> query_embedding::vector) as similarity
  FROM ai_agent_knowledge a
  WHERE 1 - (a.embedding <=> query_embedding::vector) > match_threshold
  ORDER BY a.embedding <=> query_embedding::vector
  LIMIT match_count;
END;
$$ LANGUAGE plpgsql;
```

#### 4.4. Configurar Sincronización

- **Sincronización automática**: Activa para sincronizar contenido periódicamente
- **Intervalo**: Cada 15 min, 30 min, 1 hora, 3 horas, 6 horas, 12 horas, o diario
- **Fallback a local**: Si el servicio externo falla, usa la base de conocimiento local de WordPress

### Paso 5: Configurar Comportamiento del Agente

En **AI Agent > Configuración > Comportamiento del Agente**:

#### Rol del Agente
- **Asesor**: Responde preguntas, da información
- **Vendedor**: Agrega productos al carrito y genera links de pago
- **Ambos**: Combina ambas capacidades

#### Mensajes Personalizados
- **Mensaje de Saludo**: Configura el saludo inicial del bot
- **Mensaje Fuera de Horario**: Lo que recibirá el usuario fuera de horarios de atención

### Paso 6: Configurar Horarios de Atención

1. En **AI Agent > Configuración > Horarios de Atención**
2. Activa la opción **"Activar Horarios"**
3. Configura cada día:
   - Activa el día
   - Define hora de inicio y fin
4. En **"Texto para Mostrar Horario"**, escribe cómo quieres que se muestre el horario al usuario
   - Ejemplo: `Lunes a Viernes 9:00-18:00`

### Paso 7: Configurar Campañas

1. En **AI Agent > Configuración > Campañas y Promociones**
2. Agrega campañas en formato JSON:

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

### Paso 8: Personalizar Widget

En **AI Agent > Configuración > Apariencia del Widget**:

- **Activar Widget**: Habilita el chat en el frontend
- **Posición**: Elige dónde aparece el botón (esquinas)
- **Logo**: Predeterminado, icono del sitio, o personalizado
- **Título del Header**: El nombre del asistente
- **Mensaje de Bienvenida**: Lo que aparece primero
- **Placeholder**: Texto en el campo de input

#### Colores
- **Color Primario**: Header y elementos principales
- **Color Secundario**: Gradiente del header
- **Botón e Icono**: Colores del botón flotante
- **Burbujas**: Colores de los mensajes (usuario y bot)
- **Textos**: Colores del texto en cada burbuja

#### Tipografía
- **Familia de Fuente**: Google Fonts o fuentes del sistema
- **Tamaño de Fuente**: 11px a 20px

#### Dimensiones
- **Ancho**: 280px a 600px (recomendado: 350-420px)
- **Alto**: 300px a 800px (recomendado: 450-550px)
- **Border Radius**: Para bordes redondeados
- **Tamaño del Botón**: 40px a 100px

### Paso 9: Configurar Webhook para WhatsApp

1. Ve a **AI Agent > Configuración > Webhook**
2. Copia la URL del webhook:

```
https://tu-sitio.com/wp-json/ai-agent/v1/webhook
```

#### Configurar en Twilio
1. En Twilio, ve a tu número de WhatsApp
2. En **Webhook**, ingresa la URL anterior
3. Selecciona **"POST"** como método

#### Configurar en Meta WhatsApp Business
1. En tu app de Meta, configura el webhook
2. Usa la misma URL del webhook
3. Verifica el webhook desde Meta

### Paso 10: Ver Dashboard

Ve a **AI Agent > Dashboard** para ver:
- Conversaciones totales y de hoy
- Mensajes y tokens usados
- Costo en dólares
- Estado de la base de conocimiento
- Uso por modelo LLM
- Conversaciones recientes

---

## Session Cache - Cómo Funciona

El plugin usa un sistema de caché por sesión para responder rápidamente:

```
┌─────────────────────────────────────────────────────┐
│                  USER MESSAGE                       │
└─────────────────────┬───────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────┐
│         ¿Session tiene contexto válido?              │
│         (TTL: 15-30 min configurable)               │
└─────────────────────┬───────────────────────────────┘
          ┌──────────┴──────────┐
          ▼                     ▼
       SÍ                      NO
   Respuesta               Buscar en Vector DB
   rápida (<100ms)        (500-2000ms)
                              ▼
                       Cargar top 5 resultados
                              ▼
                       Guardar en Session Cache
                              ▼
                       Responder al usuario
```

**Beneficios**:
- Primera consulta: Busca en la base vectorial y cachea resultados
- Consultas siguientes: Usa el caché (respuesta en <100ms)
- Auto-refresh: Cuando los tokens se acercan al límite, recarga contexto

---

## Requisitos

- WordPress 6.0 o superior
- PHP 8.1 o superior
- Extensión PHP `pg` (para PostgreSQL)
- WooCommerce (opcional, para integración de productos)
- API Key de OpenAI o Anthropic (opcional)

---

## Tablas de Base de Datos

El plugin crea las siguientes tablas en WordPress:

| Tabla | Descripción |
|-------|-------------|
| `ai_agent_conversations` | Registro de conversaciones |
| `ai_agent_messages` | Mensajes con tokens y costos |
| `ai_agent_product_fiches` | Fichas de productos |
| `ai_agent_knowledge_index` | Índice de conocimiento |
| `ai_agent_campaigns` | Campañas activas |
| `ai_agent_cart` | Carrito por conversación |
| `ai_agent_metrics_daily` | Métricas diarias |
| `ai_agent_token_usage` | Uso de tokens |
| `ai_agent_session_cache` | Caché de sesión |

---

## Hooks y Filtros

### `ai_agent_system_prompt`
Modifica el prompt del sistema:

```php
add_filter('ai_agent_system_prompt', function($prompt, $context) {
    return $prompt . "\n\nEres un asistente especializado.";
}, 10, 2);
```

### `ai_agent_llm_response`
Modifica la respuesta del LLM:

```php
add_filter('ai_agent_llm_response', function($response) {
    return $response;
});
```

---

## FAQs

### ¿Puedo usar el plugin sin API key de OpenAI?
Sí, puedes usar Ollama para ejecutar modelos localmente.

### ¿Cómo funciona la búsqueda semántica?
El plugin convierte contenido a vectores usando embeddings. Cuando un usuario pregunta, se comparan vectores para encontrar el contenido más relevante.

### ¿Puedo personalizar los mensajes del bot?
Sí, desde AI Agent > Configuración puedes cambiar saludo, mensaje fuera de horario, y configurar campañas.

### ¿El plugin funciona con WhatsApp Business?
Sí, a través del webhook REST configurando Twilio o Meta.

### ¿Puedo conectar a Pinecone, PostgreSQL o Supabase?
Sí, el plugin soporta los tres servicios externos. Consulta la sección de configuración para cada uno.

---

## Changelog

### 1.0.2
- Session Cache integrado en el Agente para respuestas rápidas
- Mejoras en el flujo de contexto

### 1.0.1
- Soporte para base de conocimiento externa (Pinecone, PostgreSQL, Supabase, Custom API)

### 1.0.0
- Versión inicial completa

---

## Créditos

Desarrollado por Julian Castaneda.

## Licencia

GPL v2 or later.