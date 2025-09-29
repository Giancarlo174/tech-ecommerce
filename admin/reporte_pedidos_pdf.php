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

    // Obtener los pedidos de la base de datos
    global $db;
    $stmt = $db->prepare("
        SELECT p.*, u.correo 
        FROM pedidos p 
        LEFT JOIN usuarios u ON p.id_usuario = u.id 
        ORDER BY p.creado_en DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll();

    // Crear instancia de TCPDF con configuración específica
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Deshabilitar header y footer automáticos
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Información del documento
    $pdf->SetCreator('Sistema de Pedidos');
    $pdf->SetAuthor(SITE_NAME);
    $pdf->SetTitle('Reporte de Pedidos - ' . SITE_NAME);
    $pdf->SetSubject('Reporte de todos los pedidos realizados');

    // Configurar margenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Configurar fuente
    $pdf->SetFont('helvetica', '', 10);

    // Agregar página
    $pdf->AddPage();

    // Título del reporte
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Pedidos - ' . SITE_NAME, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);

    // Resumen estadístico
    $total_pedidos = count($orders);
    $total_ventas = 0;
    foreach ($orders as $order) {
        $total_ventas += $order['total'];
    }

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen General', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total de pedidos: ' . $total_pedidos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Total de ventas: ' . formatPrice($total_ventas), 0, 1, 'L');
    $pdf->Ln(8);

    // Tabla de pedidos
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Detalle de Pedidos', 0, 1, 'L');

    // Cabecera de la tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Cliente', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Correo', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Teléfono', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Total', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Fecha', 1, 1, 'C', true);

    // Contenido de la tabla
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetFillColor(255, 255, 255);

    $fill = false;
    foreach ($orders as $order) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            
            // Repetir cabecera de tabla
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Cliente', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'Correo', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Teléfono', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Total', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Fecha', 1, 1, 'C', true);
            
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $fillColor = $fill ? array(245, 245, 245) : array(255, 255, 255);
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        
        $pdf->Cell(15, 6, '#' . $order['id'], 1, 0, 'C', true);
        $pdf->Cell(40, 6, substr($order['nombre_cliente'], 0, 25), 1, 0, 'L', true);
        $pdf->Cell(50, 6, substr($order['correo'] ?? 'N/A', 0, 30), 1, 0, 'L', true);
        $pdf->Cell(25, 6, substr($order['celular'], 0, 15), 1, 0, 'C', true);
        $pdf->Cell(25, 6, formatPrice($order['total']), 1, 0, 'R', true);
        $pdf->Cell(30, 6, date('d/m/Y H:i', strtotime($order['creado_en'])), 1, 1, 'C', true);
        
        $fill = !$fill;
    }

    // Pie de página con totales
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(130, 8, 'TOTAL GENERAL:', 1, 0, 'R');
    $pdf->Cell(25, 8, formatPrice($total_ventas), 1, 0, 'R');
    $pdf->Cell(30, 8, '', 0, 1, 'C');

    // Limpiar cualquier buffer de salida antes de generar el PDF
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Salida del PDF
    $filename = 'reporte_pedidos_' . date('Y-m-d_H-i-s') . '.pdf';
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
        <h1>Error al generar el reporte PDF</h1>
        <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="pedidos.php">Volver a pedidos</a></p>
    </body>
    </html>';
}
?>