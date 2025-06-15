<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

//  Asegurar que el usuario esté autenticado
$auth->requireLogin();

//  Inicializar la respuesta
$response = [
    'success' => false,
    'message' => '',
    'cartCount' => 0
];

//  Si se hace una petición GET, devolver los productos en el carrito
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_cart'])) {
    $response['success'] = true;
    $response['cart'] = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $response['cartCount'] = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

//  Manejar las acciones del carrito (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {case 'add':
                //  Obtener y sanitizar el ID del producto
                $productId = intval($_POST['product_id']);
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                
                //  Verificar si el producto ya está en el carrito
                if (isset($_SESSION['cart'][$productId])) {
                    $response['success'] = false;
                    $response['message'] = 'Este producto ya está en tu carrito';
                    break; // Salir del case, ya que el producto ya está en el carrito
                }
                
                //  Verificar que el producto exista y tenga stock disponible
                global $db;
                $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ? AND stock > 0");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if ($product) {
                    //  Verificar si hay suficiente stock (siempre es 1 unidad ahora)
                    if ($product['stock'] > 0) {
                        //  Añadir exactamente 1 unidad al carrito (cantidad fija)
                        // Usar la clave del producto pero siempre con valor 1
                        if (!isset($_SESSION['cart'])) {
                            $_SESSION['cart'] = [];
                        }
                        $_SESSION['cart'][$productId] = 1; // Siempre 1 unidad
                        
                        $response['success'] = true;
                        $response['message'] = 'Producto agregado al carrito';
                    } else {
                        $response['message'] = 'No hay stock disponible';
                    }
                } else {
                    $response['message'] = 'Producto no disponible';
                }
                break;
                
            default:
                $response['message'] = 'Acción no válida';
        }
    } else {
        $response['message'] = 'Parámetros insuficientes';
    }
} else {
    $response['message'] = 'Método no permitido';
}

//  Calcular el número total de productos en el carrito
$response['cartCount'] = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

//  Enviar respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);
