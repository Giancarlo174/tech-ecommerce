<?php
require_once 'db.php';

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

//  Función original renombrada a uploadToImgurAnonymous para mantenerla como fallback
function uploadToImgurAnonymous($tmpFilePath) {
    //  Verifica si IMGUR_CLIENT_ID está definido y no es el valor por defecto.
    // Esto asegura que se use un ID de cliente real configurado en .env.
    if (!defined('IMGUR_CLIENT_ID') || 
        IMGUR_CLIENT_ID === DEFAULT_IMGUR_CLIENT_ID) { //  Condición simplificada para chequear solo contra el DEFAULT_IMGUR_CLIENT_ID.
        // Return placeholder if no Imgur client ID is configured or if it's a default/old value
        error_log('Imgur Client ID no configurado o es un valor por defecto. Usando placeholder.'); // Log para depuración
        return '/placeholder.svg?height=300&width=300';
    }
    
    $image = base64_encode(file_get_contents($tmpFilePath));
    
    $ch = curl_init("https://api.imgur.com/3/image");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Client-ID " . IMGUR_CLIENT_ID],
        CURLOPT_POSTFIELDS => ['image' => $image]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['data']['link'])) {
        return $data['data']['link'];
    }
    
    return '/placeholder.svg?height=300&width=300';
}

//  Nueva función que mantiene compatibilidad con código antiguo
function uploadToImgur($tmpFilePath) {
    // Intentar subir usando OAuth primero, y si falla usar el método anónimo
    $result = uploadToImgurOAuth($tmpFilePath);
    
    if (is_array($result) && isset($result['link'])) {
        return $result['link']; // Para mantener compatibilidad con el código existente
    }
    
    // Fallback al método anónimo
    return uploadToImgurAnonymous($tmpFilePath);
}

//  Nueva función que implementa la subida de imágenes usando OAuth
function uploadToImgurOAuth($tmpFilePath) {
    //  Verificamos si las credenciales de OAuth están definidas
    if (!defined('IMGUR_ACCESS_TOKEN') || empty(IMGUR_ACCESS_TOKEN)) {
        error_log('Imgur Access Token no configurado. Usando método anónimo.'); 
        // Fallback al método anónimo pero retornar estructura completa
        $link = uploadToImgurAnonymous($tmpFilePath);
        if ($link !== '/placeholder.svg?height=300&width=300') {
            return ['link' => $link, 'deletehash' => null];
        }
        return null;
    }
    
    //  Codificar la imagen en base64
    $image = base64_encode(file_get_contents($tmpFilePath));
    
    $ch = curl_init("https://api.imgur.com/3/image");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . IMGUR_ACCESS_TOKEN],
        CURLOPT_POSTFIELDS => ['image' => $image]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    //  Verificamos si se obtuvo una respuesta exitosa
    if ($httpCode === 200 && isset($data['data']['link']) && isset($data['data']['deletehash'])) {
        return [
            'link' => $data['data']['link'],
            'deletehash' => $data['data']['deletehash']
        ];
    } else {
        //  En caso de error, intentar refrescar el token (opcional)
        // TODO: Implementar refresh token si es necesario
        error_log('Error en uploadToImgurOAuth: ' . json_encode($data));
        
        // Fallback al método anónimo
        $link = uploadToImgurAnonymous($tmpFilePath);
        if ($link !== '/placeholder.svg?height=300&width=300') {
            return ['link' => $link, 'deletehash' => null];
        }
        return null;
    }
}

/**
 * Elimina una imagen de Imgur utilizando el deletehash
 * @param string $deletehash El deletehash de la imagen a eliminar
 * @return bool True si se eliminó correctamente, False en caso contrario
 */
function deleteFromImgur($deletehash) {
    //  Verificamos si tenemos el token de acceso configurado
    if (!defined('IMGUR_ACCESS_TOKEN') || empty(IMGUR_ACCESS_TOKEN) || empty($deletehash)) {
        error_log('No se puede eliminar la imagen: Token de acceso no configurado o deletehash vacío.');
        return false;
    }
    
    //  Preparar la solicitud DELETE a la API de Imgur
    $ch = curl_init("https://api.imgur.com/3/image/{$deletehash}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . IMGUR_ACCESS_TOKEN]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    //  Verificar si la eliminación fue exitosa (código 200 y success=true en la respuesta)
    if ($httpCode === 200 && isset($data['success']) && $data['success'] === true) {
        return true;
    } else {
        //  Registrar el error pero no fallar de forma catastrófica
        error_log('Error al eliminar imagen de Imgur. Deletehash: ' . $deletehash . ', Respuesta: ' . json_encode($data));
        return false;
    }
}

function getProducts($categoryId = null, $limit = null) {
    global $db;
    
    $sql = "SELECT p.*, c.nombre as categoria, m.nombre as marca 
            FROM productos p 
            LEFT JOIN categorias c ON p.id_categoria = c.id 
            LEFT JOIN marcas m ON p.id_marca = m.id 
            WHERE p.stock > 0";
    
    // Corregimos la condición para verificar si categoryId es un valor específico (no null y no 0)
    // El valor 0 se usa para "Todas las categorías" y no debe filtrar por categoría
    if ($categoryId !== null && $categoryId > 0) {
        $sql .= " AND p.id_categoria = :categoryId";
    }
    
    $sql .= " ORDER BY p.creado_en DESC";
    
    // Añadimos LIMIT si es necesario
    if ($limit !== null) {
        $sql .= " LIMIT :limitValue";
    }
    
    // Preparamos la consulta
    $stmt = $db->prepare($sql);
    
    // Vinculamos el parámetro categoryId solo si se va a usar en la consulta
    if ($categoryId !== null && $categoryId > 0) {
        $stmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
    }
    
    // Vinculamos el parámetro limit si es necesario
    if ($limit !== null) {
        $stmt->bindValue(':limitValue', (int)$limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getCategories() {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM categorias ORDER BY nombre");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getBrands() {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM marcas ORDER BY nombre");
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function addToCart($productId, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }
}

function removeFromCart($productId) {
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

function getCartItems() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return [];
    }
    
    global $db;
    
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $db->prepare("SELECT * FROM productos WHERE id IN ($placeholders) AND stock > 0");
    $stmt->execute($productIds);
    
    $products = $stmt->fetchAll();
    $cartItems = [];
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $maxQuantity = min($quantity, $product['stock']);
        
        $cartItems[] = [
            'product' => $product,
            'quantity' => $maxQuantity,
            'subtotal' => $product['precio'] * $maxQuantity
        ];
        
        // Actualizar la cantidad en el carrito para reflejar el stock disponible
        $_SESSION['cart'][$product['id']] = $maxQuantity;
    }
    
    return $cartItems;
}

function getCartTotal() {
    $items = getCartItems();
    $total = 0;
    
    foreach ($items as $item) {
        $total += $item['subtotal'];
    }
    
    return $total;
}

function cleanExpiredHistory() {
    global $db;
    
    $stmt = $db->prepare("DELETE FROM historial_compras WHERE fecha_compra < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    $stmt->execute();
}
?>
