<?php
// Control estricto de salida para PDFs
while (ob_get_level()) {
    ob_end_clean();
}

// Iniciar sesión solo si es necesario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../config.php';
    require_once '../includes/auth.php';
    require_once '../includes/functions.php';
    require_once '../vendor/autoload.php';

    $auth->requireAdmin();

    // Verificar que se haya proporcionado el ID del pedido
    if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
        throw new Exception('ID de pedido no válido.');
    }

    $orderId = intval($_GET['order_id']);

    // Obtener los detalles del pedido
    global $db;
    
    // Obtener información del pedido
    $stmtOrder = $db->prepare("
        SELECT p.*, u.correo AS correo_usuario
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        WHERE p.id = :orderId
    ");
    $stmtOrder->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    $stmtOrder->execute();
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pedido no encontrado.');
    }

    // Obtener los ítems del pedido
    $stmtItems = $db->prepare("
        SELECT pd.*, pr.nombre AS nombre_producto
        FROM pedido_detalles pd
        JOIN productos pr ON pd.id_producto = pr.id
        WHERE pd.id_pedido = :orderId
    ");
    $stmtItems->bindParam(':orderId', $orderId, PDO::PARAM_INT);
    $stmtItems->execute();
    $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Crear instancia de TCPDF con configuración específica
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Deshabilitar header y footer automáticos
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Información del documento
    $pdf->SetCreator('Sistema de Facturación');
    $pdf->SetAuthor(SITE_NAME);
    $pdf->SetTitle('Factura #' . $order['id'] . ' - ' . SITE_NAME);
    $pdf->SetSubject('Factura del pedido #' . $order['id']);

    // Configurar margenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Configurar fuente
    $pdf->SetFont('helvetica', '', 10);

    // Agregar página
    $pdf->AddPage();

    // Encabezado de la factura
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 15, SITE_NAME, 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'FACTURA', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Fecha de emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);

    // Información del pedido y cliente
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(95, 8, 'Datos del Pedido', 1, 0, 'L');
    $pdf->Cell(95, 8, 'Datos del Cliente', 1, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);

    // Columna izquierda - Datos del pedido
    $pdf->Cell(95, 6, 'Número de Pedido: #' . $order['id'], 1, 0, 'L');
    // Columna derecha - Datos del cliente
    $pdf->Cell(95, 6, 'Nombre: ' . $order['nombre_cliente'], 1, 1, 'L');

    $pdf->Cell(95, 6, 'Fecha del Pedido: ' . date('d/m/Y H:i', strtotime($order['creado_en'])), 1, 0, 'L');
    $pdf->Cell(95, 6, 'Teléfono: ' . $order['celular'], 1, 1, 'L');

    $pdf->Cell(95, 6, 'Estado: Completado', 1, 0, 'L');
    $pdf->Cell(95, 6, 'Correo: ' . ($order['correo_usuario'] ?? 'N/A'), 1, 1, 'L');

    $pdf->Cell(95, 6, '', 1, 0, 'L');
    $pdf->Cell(95, 6, 'Dirección: ' . ($order['direccion'] ?? 'N/A'), 1, 1, 'L');

    $pdf->Ln(10);

    // Tabla de productos
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Detalle de Productos', 0, 1, 'L');

    // Cabecera de la tabla de productos
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(80, 8, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Precio Unit.', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Subtotal', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Total', 1, 1, 'C', true);

    // Contenido de la tabla de productos
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    $subtotal_general = 0;
    $fill = false;

    foreach ($orderItems as $item) {
        $fillColor = $fill ? array(245, 245, 245) : array(255, 255, 255);
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        
        $precio_unitario = floatval($item['precio_unitario']);
        $cantidad = intval($item['cantidad']);
        $subtotal_item = $precio_unitario * $cantidad;
        $subtotal_general += $subtotal_item;
        
        $pdf->Cell(80, 6, substr($item['nombre_producto'], 0, 40), 1, 0, 'L', true);
        $pdf->Cell(20, 6, $cantidad, 1, 0, 'C', true);
        $pdf->Cell(30, 6, formatPrice($precio_unitario), 1, 0, 'R', true);
        $pdf->Cell(30, 6, formatPrice($subtotal_item), 1, 0, 'R', true);
        $pdf->Cell(30, 6, formatPrice($subtotal_item), 1, 1, 'R', true);
        
        $fill = !$fill;
    }

    // Línea de separación
    $pdf->Cell(190, 1, '', 0, 1, 'C');

    // Totales
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(130, 8, 'SUBTOTAL:', 1, 0, 'R');
    $pdf->Cell(30, 8, formatPrice($subtotal_general), 1, 0, 'R');
    $pdf->Cell(30, 8, '', 0, 1, 'C');

    // Impuestos (si aplica)
    $impuestos = 0; // Puedes calcular impuestos aquí si es necesario
    if ($impuestos > 0) {
        $pdf->Cell(130, 8, 'IMPUESTOS:', 1, 0, 'R');
        $pdf->Cell(30, 8, formatPrice($impuestos), 1, 0, 'R');
        $pdf->Cell(30, 8, '', 0, 1, 'C');
    }

    // Total final
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(130, 10, 'TOTAL A PAGAR:', 1, 0, 'R', true);
    $pdf->Cell(30, 10, formatPrice($order['total']), 1, 0, 'R', true);
    $pdf->Cell(30, 10, '', 0, 1, 'C');

    // Información adicional
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Gracias por su compra en ' . SITE_NAME, 0, 1, 'C');
    $pdf->Cell(0, 6, 'Para cualquier consulta, contáctenos a través de nuestros canales de atención.', 0, 1, 'C');

    // Pie de página
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 4, 'Este documento fue generado automáticamente el ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 4, 'Factura válida como comprobante de compra', 0, 1, 'C');

    // Limpiar cualquier buffer de salida antes de generar el PDF
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Salida del PDF
    $filename = 'factura_pedido_' . $order['id'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    // En caso de error, mostrar mensaje
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <meta charset="utf-8">
    </head>
    <body>
        <h1>Error al generar la factura PDF</h1>
        <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="pedidos.php">Volver a pedidos</a></p>
    </body>
    </html>';
}
?>