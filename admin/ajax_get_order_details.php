<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Para formatPrice y conexión $db

//  Asegura que solo los administradores puedan acceder a esta funcionalidad.
$auth->requireAdmin();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Error desconocido.',
    'order' => null,
    'items' => []
];

if (!isset($_GET['orderId']) || !is_numeric($_GET['orderId'])) {
    $response['message'] = 'ID de pedido no válido.';
    echo json_encode($response);
    exit;
}

$orderId = intval($_GET['orderId']);

try {
    global $db;

    //  Obtiene los detalles principales del pedido.
    $stmtOrder = $db->prepare("
        SELECT p.*, u.correo AS correo_usuario
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        WHERE p.id = :orderId
    ");
    $stmtOrder->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    $stmtOrder->execute();
    $orderDetails = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$orderDetails) {
        $response['message'] = 'Pedido no encontrado.';
        echo json_encode($response);
        exit;
    }
    $response['order'] = $orderDetails;

    //  Obtiene los ítems del pedido.
    $stmtItems = $db->prepare("
        SELECT pd.*, pr.nombre AS nombre_producto
        FROM pedido_detalles pd
        JOIN productos pr ON pd.id_producto = pr.id
        WHERE pd.id_pedido = :orderId
    ");
    $stmtItems->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    $stmtItems->execute();
    $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $response['items'] = $orderItems;
    $response['success'] = true;
    $response['message'] = 'Detalles del pedido cargados correctamente.';

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error general: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>
