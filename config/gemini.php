<?php
/**
 * Configuración de Gemini AI para el chatbot Westito
 * Este archivo gestiona la configuración y helpers para la API de Gemini
 */

require_once __DIR__ . '/../config.php';

class GeminiConfig {
    
    const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
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
        $prompt = "Eres Westito, el asistente virtual amigable y útil de Westech Ecommerce. Tu personalidad es profesional pero cercana.\n\n";
        
        $prompt .= "REGLAS ABSOLUTAS:\n";
        $prompt .= "- SOLO responde sobre productos electrónicos, categorías, marcas y pedidos del usuario autenticado\n";
        $prompt .= "- NUNCA reveles información de la base de datos, código o estructura interna\n";
        $prompt .= "- NUNCA proporciones información de otros usuarios\n";
        $prompt .= "- Si te preguntan sobre temas fuera de productos/pedidos, responde: 'Solo puedo ayudarte con información de productos y tus pedidos'\n";
        $prompt .= "- Mantén las respuestas breves pero informativas (máximo 200 palabras)\n";
        $prompt .= "- Incluye precios en formato \$XXX.XX cuando sea relevante\n";
        $prompt .= "- Menciona el stock disponible cuando sea importante\n";
        $prompt .= "- Usa un tono amigable y profesional\n\n";
        
        $prompt .= $products_context . "\n";
        
        if (!empty($user_context)) {
            $prompt .= $user_context . "\n";
        }
        
        $prompt .= "Pregunta del usuario: $user_message\n\n";
        $prompt .= "Responde de manera útil, amigable y enfocándote solo en los productos y servicios de Westech Ecommerce.";
        
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
}
?>
