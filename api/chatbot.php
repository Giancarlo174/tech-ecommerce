<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/gemini.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting - máximo 5 solicitudes por minuto por usuario
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? 'anonymous';
$rate_key = "chatbot_rate_$user_id";

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = [];
}

// Limpiar requests antiguos (más de 1 minuto)
$current_time = time();
$_SESSION[$rate_key] = array_filter($_SESSION[$rate_key], function($timestamp) use ($current_time) {
    return ($current_time - $timestamp) < 60;
});

// Verificar límite de rate
if (count($_SESSION[$rate_key]) >= 5) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Has enviado muchos mensajes. Espera un momento antes de continuar.'
    ]);
    exit;
}

// Agregar timestamp actual
$_SESSION[$rate_key][] = $current_time;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';
$conversation_id = isset($input['conversation_id']) ? sanitizeInput($input['conversation_id']) : uniqid();

if (empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Mensaje no proporcionado'
    ]);
    exit;
}

// Validación de seguridad usando la clase helper
if (WestitoHelpers::containsDangerousKeywords($message)) {
    echo json_encode([
        'success' => true,
        'message' => 'Solo puedo ayudarte con información de productos y tus pedidos.',
        'conversation_id' => $conversation_id
    ]);
    exit;
}

// Sanitizar entrada usando helper
$message = WestitoHelpers::sanitizeUserMessage($message);

try {
    // Verificar si Gemini API está configurada usando la clase helper
    if (!GeminiConfig::isConfigured()) {
        echo json_encode([
            'success' => true,
            'message' => 'Lo siento, el servicio de chat no está disponible en este momento.',
            'conversation_id' => $conversation_id
        ]);
        exit;
    }

    // Saludo inicial usando helper
    if (WestitoHelpers::isGreeting($message)) {
        echo json_encode([
            'success' => true,
            'message' => '¡Hola! Soy Westito, tu asistente de Westech Ecommerce. ¿En qué puedo ayudarte hoy? Puedo ayudarte con información sobre productos, categorías y marcas.',
            'conversation_id' => $conversation_id
        ]);
        exit;
    }

    // Obtener datos de la base de datos
    global $db;
    
    // Obtener productos con stock disponible
    $stmt = $db->prepare("
        SELECT p.id, p.nombre, p.descripcion, p.precio, p.stock, p.imagen_url,
               c.nombre as categoria, m.nombre as marca
        FROM productos p
        LEFT JOIN categorias c ON p.id_categoria = c.id
        LEFT JOIN marcas m ON p.id_marca = m.id
        WHERE p.stock > 0
        ORDER BY p.nombre
        LIMIT 20
    ");
    $stmt->execute();
    $productos = $stmt->fetchAll();

    // Obtener categorías
    $stmt = $db->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
    $stmt->execute();
    $categorias = $stmt->fetchAll();

    // Obtener marcas
    $stmt = $db->prepare("SELECT id, nombre FROM marcas ORDER BY nombre");
    $stmt->execute();
    $marcas = $stmt->fetchAll();

    // Datos del historial solo si el usuario está loggeado
    $historial_info = '';
    $is_logged_in = isset($_SESSION['user_id']);
    
    if ($is_logged_in) {
        $stmt = $db->prepare("
            SELECT p.id, p.nombre_cliente, p.total, p.creado_en
            FROM pedidos p
            WHERE p.id_usuario = ?
            ORDER BY p.creado_en DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $pedidos = $stmt->fetchAll();
        
        if (!empty($pedidos)) {
            $historial_info = "\n\nHistorial de pedidos del usuario:\n";
            foreach ($pedidos as $pedido) {
                $fecha = date('d/m/Y', strtotime($pedido['creado_en']));
                $total = WestitoHelpers::formatPrice($pedido['total']);
                $historial_info .= "- Pedido #{$pedido['id']}: {$pedido['nombre_cliente']}, Total: $total, Fecha: $fecha\n";
            }
        }
    }

    // Construir contexto completo para Gemini usando la clase helper
    $products_context = "PRODUCTOS DISPONIBLES:\n";
    foreach ($productos as $producto) {
        $precio = WestitoHelpers::formatPrice($producto['precio']);
        $descripcion = WestitoHelpers::truncateDescription($producto['descripcion']);
        $products_context .= "- {$producto['nombre']}: $precio, Stock: {$producto['stock']}, Categoría: {$producto['categoria']}, Marca: {$producto['marca']}\n";
        if (!empty($descripcion)) {
            $products_context .= "  Descripción: $descripcion\n";
        }
    }

    $products_context .= "\nCATEGORÍAS:\n";
    foreach ($categorias as $categoria) {
        $products_context .= "- {$categoria['nombre']}\n";
    }

    $products_context .= "\nMARCAS:\n";
    foreach ($marcas as $marca) {
        $products_context .= "- {$marca['nombre']}\n";
    }

    $user_context = '';
    if ($is_logged_in) {
        $user_context = $historial_info;
    } else {
        $user_context = "\nNOTA: El usuario no está loggeado, no puede acceder a información de pedidos.\n";
    }

    // Usar la clase GeminiConfig para construir el prompt y hacer la llamada
    $contexto = GeminiConfig::buildWestitoPrompt($message, $products_context, $user_context);
    $bot_message = GeminiConfig::makeApiCall($contexto);

    // Sanitizar respuesta del bot
    $bot_message = sanitizeInput($bot_message);

    echo json_encode([
        'success' => true,
        'message' => $bot_message,
        'conversation_id' => $conversation_id
    ]);

} catch (Exception $e) {
    error_log("Chatbot Error: " . $e->getMessage());
    
    // Respuesta de fallback
    $fallback_responses = [
        'Lo siento, estoy teniendo dificultades técnicas. ¿Podrías intentar de nuevo?',
        'Disculpa, no pude procesar tu consulta en este momento. Intenta reformular tu pregunta.',
        'Estoy experimentando algunos problemas. Por favor, inténtalo nuevamente en un momento.'
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $fallback_responses[array_rand($fallback_responses)],
        'conversation_id' => $conversation_id
    ]);
}
?>
