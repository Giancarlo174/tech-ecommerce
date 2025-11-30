<?php
/**
 * Configuración de Gemini AI para el chatbot Westito
 * Este archivo gestiona la configuración y helpers para la API de Gemini
 */

require_once __DIR__ . '/../config.php';

class GeminiConfig {
    
    const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
    const MAX_TOKENS = 1024;
    const TEMPERATURE = 0.7;
    const TOP_K = 40;
    const TOP_P = 0.95;
    
    /**
     * Verifica si la API key de Gemini está configurada
     */
    public static function isConfigured() {
        return !empty(GEMINI_API_KEY) && GEMINI_API_KEY !== '';
    }
    
    /**
     * Obtiene la URL completa de la API con la key
     */
    public static function getApiUrl() {
        if (!self::isConfigured()) {
            throw new Exception('Gemini API key no configurada');
        }
        
        return self::API_ENDPOINT . '?key=' . GEMINI_API_KEY;
    }
    
    /**
     * Prepara el payload para la API de Gemini
     */
    public static function preparePayload($prompt) {
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => self::TEMPERATURE,
                'topK' => self::TOP_K,
                'topP' => self::TOP_P,
                'maxOutputTokens' => self::MAX_TOKENS,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
    }
    
    /**
     * Realiza una llamada a la API de Gemini
     */
    public static function makeApiCall($prompt, $timeout = 30) {
        if (!self::isConfigured()) {
            throw new Exception('Gemini API no configurada');
        }
        
        $payload = self::preparePayload($prompt);
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'ignore_errors' => true,
                'timeout' => $timeout
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents(self::getApiUrl(), false, $context);
        
        if ($result === FALSE) {
            throw new Exception('Error al consultar Gemini API');
        }
        
        $response = json_decode($result, true);
        
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $error_message = isset($response['error']['message']) 
                ? $response['error']['message'] 
                : 'Respuesta inválida de Gemini API';
            throw new Exception($error_message);
        }
        
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Construye el prompt base para Westito
     */
    public static function buildWestitoPrompt($user_message, $products_context, $user_context = '') {
        $prompt = "Eres Westito, el asistente virtual amigable y útil de Westech Ecommerce, una tienda en línea especializada en productos electrónicos. Tu personalidad es profesional pero cercana.\n\n";
        
        $prompt .= "INFORMACIÓN DEL SITIO WEB Y FUNCIONALIDADES:\n\n";
        
        $prompt .= "REGISTRO Y ACCESO:\n";
        $prompt .= "- Los usuarios pueden crear una cuenta con su email y una contraseña segura\n";
        $prompt .= "- Después del registro, deben iniciar sesión para acceder a todas las funciones\n";
        $prompt .= "- Los usuarios registrados pueden hacer compras y ver su historial de pedidos\n";
        $prompt .= "- Los usuarios sin cuenta pueden navegar productos pero no pueden comprar\n\n";
        
        $prompt .= "NAVEGACIÓN Y COMPRA:\n";
        $prompt .= "- Los usuarios pueden explorar productos organizados por categorías y marcas\n";
        $prompt .= "- Pueden agregar productos que deseen al carrito de compras\n";
        $prompt .= "- El carrito muestra los productos seleccionados, cantidades y total a pagar\n";
        $prompt .= "- Para completar la compra, deben ir al proceso de checkout\n\n";
        
        $prompt .= "PROCESO DE CHECKOUT:\n";
        $prompt .= "- Se solicita información básica de envío: nombre completo, teléfono y dirección\n";
        $prompt .= "- Esta información es necesaria para entregar los productos\n";
        $prompt .= "- Después de completar los datos de envío, se selecciona cómo pagar\n\n";
        
        $prompt .= "MÉTODOS DE PAGO DISPONIBLES:\n";
        $prompt .= "- TARJETA DE CRÉDITO O DÉBITO: Se requiere el número de tarjeta, fecha de vencimiento y código de seguridad\n";
        $prompt .= "- YAPPY: Billetera digital que requiere el identificador personal de Yappy\n";
        $prompt .= "- Ambos métodos son seguros y procesan el pago de forma inmediata\n\n";
        
        $prompt .= "FLUJO COMPLETO DE COMPRA:\n";
        $prompt .= "1. Crear cuenta o iniciar sesión\n";
        $prompt .= "2. Explorar productos y agregarlos al carrito\n";
        $prompt .= "3. Revisar el carrito y confirmar los productos\n";
        $prompt .= "4. Ir al checkout e ingresar datos de envío\n";
        $prompt .= "5. Elegir método de pago (tarjeta o Yappy)\n";
        $prompt .= "6. Completar la compra - recibirás confirmación y factura por email\n\n";
        
        $prompt .= "INFORMACIÓN GENERAL:\n";
        $prompt .= "- Todos los productos tienen stock limitado, verifica la disponibilidad\n";
        $prompt .= "- Los precios incluyen todos los impuestos\n";
        $prompt .= "- Las entregas se hacen a la dirección proporcionada\n";
        $prompt .= "- Recibirás actualizaciones del pedido por email\n\n";
        
        $prompt .= "REGLAS ABSOLUTAS PARA RESPUESTAS:\n";
        $prompt .= "- SOLO responde sobre productos electrónicos, categorías, marcas, pedidos del usuario, y PROCESOS DEL SITIO (registro, login, compra, pagos)\n";
        $prompt .= "- NUNCA reveles información técnica, código o estructura interna\n";
        $prompt .= "- NUNCA proporciones información de otros usuarios\n";
        $prompt .= "- Si te preguntan sobre temas fuera de productos/pedidos/procesos del sitio, responde: 'Solo puedo ayudarte con información de productos, pedidos y procesos de compra en Westech Ecommerce'\n";
        $prompt .= "- Mantén las respuestas breves pero informativas (máximo 200 palabras)\n";
        $prompt .= "- Incluye precios en formato \$XXX.XX cuando sea relevante\n";
        $prompt .= "- Menciona el stock disponible cuando sea importante\n";
        $prompt .= "- Usa un tono amigable y profesional\n";
        $prompt .= "- Para preguntas sobre registro: explica que necesitan email y contraseña, luego iniciar sesión\n";
        $prompt .= "- Para preguntas sobre pagos: menciona los dos métodos disponibles (tarjeta y Yappy) y qué información básica se requiere\n";
        $prompt .= "- Para preguntas sobre compras: explica el flujo paso a paso de forma clara y simple\n\n";
        
        $prompt .= $products_context . "\n";
        
        if (!empty($user_context)) {
            $prompt .= $user_context . "\n";
        }
        
        $prompt .= "Pregunta del usuario: $user_message\n\n";
        $prompt .= "Responde de manera útil, amigable y enfocándote solo en los productos, pedidos y procesos de Westech Ecommerce. Si la pregunta es sobre cómo hacer algo en el sitio, explica el proceso paso a paso de forma clara y sin detalles técnicos.";
        
        return $prompt;
    }
}

// Funciones de utilidad para el chatbot
class WestitoHelpers {
    
    /**
     * Formatea un precio para mostrar
     */
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    /**
     * Trunca texto de descripción
     */
    public static function truncateDescription($text, $maxLength = 100) {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength) . '...';
    }
    
    /**
     * Sanitiza el mensaje del usuario
     */
    public static function sanitizeUserMessage($message) {
        // Limpiar HTML y caracteres especiales
        $message = strip_tags($message);
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Remover múltiples espacios
        $message = preg_replace('/\s+/', ' ', $message);
        
        return trim($message);
    }
    
    /**
     * Verifica si el mensaje contiene palabras peligrosas
     */
    public static function containsDangerousKeywords($message) {
        $dangerous_keywords = [
            'delete', 'drop', 'truncate', 'schema', 'password', 'admin', 
            'root', 'database', 'table', 'column', 'insert', 'update',
            'select *', 'show tables', 'describe', 'information_schema',
            'union', 'injection', 'script', 'alert', 'eval'
        ];
        
        $message_lower = strtolower($message);
        
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detecta si es un saludo
     */
    public static function isGreeting($message) {
        $greetings = [
            'hola', 'hi', 'hello', 'hey', 'buenos días', 'buenas tardes', 
            'buenas noches', 'saludos', 'qué tal', 'cómo estás'
        ];
        
        $message_clean = strtolower(trim($message));
        
        foreach ($greetings as $greeting) {
            if ($message_clean === $greeting || strpos($message_clean, $greeting) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calcula la similitud entre dos strings usando Levenshtein
     */
    public static function calculateSimilarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 == 0 && $len2 == 0) return 1.0;
        if ($len1 == 0 || $len2 == 0) return 0.0;
        
        $distance = levenshtein($str1, $str2);
        $max_len = max($len1, $len2);
        
        return 1 - ($distance / $max_len);
    }
    
    /**
     * Registra una consulta del chatbot
     */
    public static function logChatbotQuery($user_id, $query, $response, $source, $conversation_id) {
        global $db;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO chatbot_queries (user_id, query, response, source, conversation_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $query, $response, $source, $conversation_id]);
        } catch (Exception $e) {
            error_log("Error logging chatbot query: " . $e->getMessage());
        }
    }
}
?>
