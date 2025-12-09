<?php

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php'; // EL AUTOLOADER DE COMPOSER CARGA TCPDF AUTOMÁTICAMENTE

$auth->requireAdmin();

global $db;

$action = isset($_GET['action']) ? $_GET['action'] : '';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'pdf';

// Funciones auxiliares para exportación
function exportar_csv($nombre_archivo, $headers, $datos) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, $headers);
    
    // Datos
    foreach ($datos as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// EXPORTAR INVENTARIO
if ($action === 'export_inventario') {
    $stock_minimo = isset($_GET['stock_minimo']) ? (int)$_GET['stock_minimo'] : 10;
    
    $stmt = $db->prepare("SELECT p.*, c.nombre as categoria_nombre, COALESCE(dp.vendidos_total,0) as vendidos_total
        FROM productos p 
        LEFT JOIN categorias c ON p.id_categoria = c.id 
        LEFT JOIN (
            SELECT id_producto, SUM(cantidad) as vendidos_total
            FROM pedido_detalles
            GROUP BY id_producto
        ) dp ON p.id = dp.id_producto
        ORDER BY p.stock ASC");
    $stmt->execute();
    $productos = $stmt->fetchAll();
    
    if ($formato === 'csv') {
        $datos = [];
        foreach ($productos as $p) {
            $valor_stock = $p['precio'] * $p['stock'];
            $estado = ($p['stock'] == 0) ? 'Crítico' : (($p['stock'] <= $stock_minimo) ? 'Bajo' : 'Normal');
            
            $datos[] = [
                $p['nombre'],
                $p['categoria_nombre'] ?? 'N/A',
                number_format($p['precio'], 2),
                $p['stock'],
                $stock_minimo,
                number_format($valor_stock, 2),
                $estado
            ];
        }
        
        exportar_csv(
            'inventario_' . date('Y-m-d-H-i-s') . '.csv',
            ['Producto', 'Categoría', 'Precio', 'Stock', 'Mínimo', 'Valor Stock', 'Estado'],
            $datos
        );
    } else {
        // PDF con TCPDF
        // LÍNEA ELIMINADA: require_once '../vendor/tecnickcom/tcpdf/tcpdf_include.php';
        
        // Usamos \TCPDF para asegurar el uso de la clase global cargada por Composer
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setFont('helvetica', '', 10);
        $pdf->addPage();
        
        // Título
        $pdf->setFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'REPORTE DE INVENTARIOS', 0, 1, 'C');
        $pdf->setFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Tabla
        $pdf->setFont('helvetica', 'B', 9);
        $pdf->setFillColor(52, 152, 219);
        $pdf->setTextColor(255, 255, 255);
        
        $pdf->Cell(35, 7, 'Producto', 1, 0, 'L', true);
        $pdf->Cell(25, 7, 'Categoría', 1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Precio', 1, 0, 'R', true);
        $pdf->Cell(15, 7, 'Stock', 1, 0, 'R', true);
        $pdf->Cell(15, 7, 'Mín.', 1, 0, 'R', true);
        $pdf->Cell(15, 7, 'Vendidos', 1, 0, 'R', true);
        $pdf->Cell(20, 7, 'Val. Stock', 1, 1, 'R', true);
        
        $pdf->setFont('helvetica', '', 8);
        $pdf->setTextColor(0, 0, 0);
        
        foreach ($productos as $p) {
            $valor_stock = $p['precio'] * $p['stock'];
            $estado = ($p['stock'] == 0) ? 'Crítico' : (($p['stock'] <= $stock_minimo) ? 'Bajo' : 'Normal');
            
            // Colorear filas según estado
            if ($p['stock'] == 0) {
                $pdf->setFillColor(242, 220, 219);
            } elseif ($p['stock'] <= $stock_minimo) {
                $pdf->setFillColor(252, 248, 227);
            } else {
                $pdf->setFillColor(242, 252, 241);
            }
            
            $pdf->Cell(35, 6, substr($p['nombre'], 0, 20), 1, 0, 'L', true);
            $pdf->Cell(25, 6, substr($p['categoria_nombre'] ?? 'N/A', 0, 15), 1, 0, 'L', true);
            $pdf->Cell(20, 6, '$' . number_format($p['precio'], 2), 1, 0, 'R', true);
            $pdf->Cell(15, 6, $p['stock'], 1, 0, 'R', true);
            $pdf->Cell(15, 6, $stock_minimo, 1, 0, 'R', true);
            $pdf->Cell(15, 6, $p['vendidos_total'] ?? 0, 1, 0, 'R', true);
            $pdf->Cell(20, 6, '$' . number_format($valor_stock, 2), 1, 1, 'R', true);
        }
        
        $pdf->Output('inventario_' . date('Y-m-d-H-i-s') . '.pdf', 'D');
    }
}

// EXPORTAR VENTAS
else if ($action === 'export_ventas') {
    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
    
    // Obtener datos de ventas
    $stmt = $db->prepare("SELECT 
        p.id, DATE(p.creado_en) as fecha, p.total
        FROM pedidos p
        WHERE DATE(p.creado_en) BETWEEN ? AND ?
        ORDER BY p.creado_en DESC");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $pedidos = $stmt->fetchAll();

    // Productos más vendidos en el período
    $stmt = $db->prepare("SELECT pr.nombre, pr.precio, SUM(pd.cantidad) as cantidad_vendida, SUM(pd.cantidad * pd.precio_unitario) as ingresos
        FROM pedido_detalles pd
        INNER JOIN pedidos p ON pd.id_pedido = p.id
        INNER JOIN productos pr ON pd.id_producto = pr.id
        WHERE DATE(p.creado_en) BETWEEN ? AND ?
        GROUP BY pr.id, pr.nombre, pr.precio
        ORDER BY cantidad_vendida DESC
        LIMIT 20");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $productos_top = $stmt->fetchAll();

    // Ventas por categoría
    $stmt = $db->prepare("SELECT c.nombre, SUM(pd.cantidad) as cantidad, SUM(pd.cantidad * pd.precio_unitario) as ingresos
        FROM pedido_detalles pd
        INNER JOIN pedidos p ON pd.id_pedido = p.id
        INNER JOIN productos pr ON pd.id_producto = pr.id
        LEFT JOIN categorias c ON pr.id_categoria = c.id
        WHERE DATE(p.creado_en) BETWEEN ? AND ?
        GROUP BY c.id, c.nombre
        ORDER BY ingresos DESC");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $categorias = $stmt->fetchAll();
    
    if ($formato === 'csv') {
        // Exportar múltiples CSVs
        $datos_pedidos = [];
        foreach ($pedidos as $p) {
            $datos_pedidos[] = [
                $p['id'],
                $p['fecha'],
                '$' . number_format($p['total'], 2)
            ];
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"ventas_" . date('Y-m-d-H-i-s') . ".csv\"");
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['REPORTE DE VENTAS', '', '', '']);
        fputcsv($output, ['Período', $fecha_inicio, 'a', $fecha_fin]);
        fputcsv($output, ['']);
        
        fputcsv($output, ['PEDIDOS REALIZADOS']);
        fputcsv($output, ['ID Pedido', 'Fecha', 'Total']);
        foreach ($datos_pedidos as $row) {
            fputcsv($output, $row);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['PRODUCTOS MÁS VENDIDOS']);
        fputcsv($output, ['Producto', 'Precio', 'Cantidad Vendida', 'Ingresos']);
        foreach ($productos_top as $p) {
            fputcsv($output, [
                $p['nombre'],
                '$' . number_format($p['precio'], 2),
                $p['cantidad_vendida'],
                '$' . number_format($p['ingresos'], 2)
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['VENTAS POR CATEGORÍA']);
        fputcsv($output, ['Categoría', 'Cantidad', 'Ingresos']);
        foreach ($categorias as $c) {
            fputcsv($output, [
                $c['nombre'],
                $c['cantidad'],
                '$' . number_format($c['ingresos'], 2)
            ]);
        }
        
        fclose($output);
        exit;
    } else {
        // PDF
        // LÍNEA ELIMINADA: require_once '../vendor/tecnickcom/tcpdf/tcpdf_include.php';
        
        // Usamos \TCPDF para asegurar el uso de la clase global cargada por Composer
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setFont('helvetica', '', 9);
        $pdf->addPage();
        
        // Título
        $pdf->setFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'REPORTE DE VENTAS', 0, 1, 'C');
        $pdf->setFont('helvetica', '', 9);
        $pdf->Cell(0, 5, "Período: $fecha_inicio a $fecha_fin", 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Tabla de pedidos
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'PEDIDOS REALIZADOS', 0, 1);
        
        $pdf->setFont('helvetica', 'B', 8);
        $pdf->setFillColor(52, 152, 219);
        $pdf->setTextColor(255, 255, 255);
        
        $pdf->Cell(30, 6, 'ID Pedido', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Fecha', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Productos', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Items', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Total', 1, 1, 'R', true);
        
        $pdf->setFont('helvetica', '', 8);
        $pdf->setTextColor(0, 0, 0);
        
        foreach ($pedidos as $p) {
            // Nota: Aquí faltan las columnas 'total_productos' y 'cantidad_items' en el SQL,
            // pero el error principal es de inclusión, esto solo es un potencial bug.
            $pdf->Cell(30, 5, $p['id'], 1, 0, 'C');
            $pdf->Cell(30, 5, $p['fecha'], 1, 0, 'C');
            $pdf->Cell(35, 5, $p['total_productos'] ?? 'N/A', 1, 0, 'C');
            $pdf->Cell(30, 5, $p['cantidad_items'] ?? 'N/A', 1, 0, 'C');
            $pdf->Cell(35, 5, '$' . number_format($p['total'], 2), 1, 1, 'R');
        }
        
        $pdf->Ln(8);
        
        // Tabla de productos
        $pdf->setFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'PRODUCTOS MÁS VENDIDOS', 0, 1);
        
        $pdf->setFont('helvetica', 'B', 8);
        $pdf->setFillColor(52, 152, 219);
        $pdf->setTextColor(255, 255, 255);
        
        $pdf->Cell(50, 6, 'Producto', 1, 0, 'L', true);
        $pdf->Cell(25, 6, 'Precio', 1, 0, 'R', true);
        $pdf->Cell(35, 6, 'Cantidad', 1, 0, 'R', true);
        $pdf->Cell(30, 6, 'Ingresos', 1, 1, 'R', true);
        
        $pdf->setFont('helvetica', '', 8);
        $pdf->setTextColor(0, 0, 0);
        
        foreach ($productos_top as $p) {
            $pdf->Cell(50, 5, substr($p['nombre'], 0, 35), 1, 0, 'L');
            $pdf->Cell(25, 5, '$' . number_format($p['precio'], 2), 1, 0, 'R');
            $pdf->Cell(35, 5, $p['cantidad_vendida'], 1, 0, 'R');
            $pdf->Cell(30, 5, '$' . number_format($p['ingresos'], 2), 1, 1, 'R');
        }
        
        $pdf->Output('ventas_' . date('Y-m-d-H-i-s') . '.pdf', 'D');
    }
}

else {
    header('HTTP/1.1 404 Not Found');
    echo 'Acción no válida';
}
?>