<?php

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAdmin();

global $db;

// Parámetros de filtro
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Obtener datos generales del período
$stmt = $db->prepare("SELECT 
    COUNT(*) as total_pedidos,
    SUM(total) as ingresos_totales,
    AVG(total) as ticket_promedio
    FROM pedidos 
    WHERE DATE(creado_en) BETWEEN ? AND ?");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$datos_generales = $stmt->fetch();

// Obtener ventas por día (sin detalles_pedido)
$stmt = $db->prepare("SELECT 
    DATE(creado_en) as fecha,
    COUNT(*) as total_pedidos,
    SUM(total) as ingresos
    FROM pedidos 
    WHERE DATE(creado_en) BETWEEN ? AND ?
    GROUP BY DATE(creado_en)
    ORDER BY fecha ASC");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas_diarias = $stmt->fetchAll();

// Productos más vendidos (usar tabla pedido_detalles)
$stmt = $db->prepare("SELECT pr.id, pr.nombre, pr.precio, SUM(pd.cantidad) as cantidad_vendida, SUM(pd.cantidad * pd.precio_unitario) as ingresos
    FROM pedido_detalles pd
    INNER JOIN pedidos pe ON pd.id_pedido = pe.id
    INNER JOIN productos pr ON pd.id_producto = pr.id
    WHERE DATE(pe.creado_en) BETWEEN ? AND ?
    GROUP BY pr.id, pr.nombre, pr.precio
    ORDER BY cantidad_vendida DESC
    LIMIT 10");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$productos_top = $stmt->fetchAll();

// Ingresos por categoría
$stmt = $db->prepare("SELECT c.id, c.nombre, SUM(pd.cantidad) as cantidad, SUM(pd.cantidad * pd.precio_unitario) as ingresos
    FROM pedido_detalles pd
    INNER JOIN pedidos pe ON pd.id_pedido = pe.id
    INNER JOIN productos pr ON pd.id_producto = pr.id
    LEFT JOIN categorias c ON pr.id_categoria = c.id
    WHERE DATE(pe.creado_en) BETWEEN ? AND ?
    GROUP BY c.id, c.nombre
    ORDER BY ingresos DESC");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$categorias_top = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Ventas - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/chatbot.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 1.5rem;
        }
        
        .header h1 a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .header h1 a:hover {
            color: #3b82f6;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Estilos del menú de navegación (navbar) copiados de index.php */
        .nav-menu {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .nav-menu a {
            padding: 0.75rem 1.5rem;
            background: white;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: #3b82f6;
            color: white;
        }
        /* Fin de estilos del navbar */

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .metric-card h3 {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #334155;
        }
        
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .chart-container h3 {
            margin-bottom: 1rem;
            color: #1e293b;
            font-size: 1.125rem;
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
            margin-bottom: 1rem;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-container thead {
            background: #f3f4f6;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #6b7280;
        }
        
        .table-container th,
        .table-container td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table-container tbody tr:hover {
            background: #f9fafb;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .chart-wrapper {
                height: 250px;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
        }
        /* Se eliminó el CSS inservible (pagination, nav-tabs, sidebar, main-content) */
    </style>
</head>
<body>
    <div class="header">
        <h1><a href="<?php echo BASE_URL; ?>admin/"><?php echo SITE_NAME; ?></a></h1>
        <div class="header-actions">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
            <a href="<?php echo BASE_URL; ?>admin/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="nav-menu">
            <a href="<?php echo BASE_URL; ?>admin/">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>admin/productos.php">Productos</a>
            <a href="<?php echo BASE_URL; ?>admin/categorias.php">Categorías</a>
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php">Pedidos</a>
            <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php">Inventarios</a>
            <a href="<?php echo BASE_URL; ?>admin/reportes_ventas.php" class="active">Reportes</a>
        </div>

        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?php echo BASE_URL; ?>admin/api_reportes.php?action=export_ventas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&formato=pdf" class="btn btn-success" target="_blank">Exportar PDF</a>
                <a href="<?php echo BASE_URL; ?>admin/api_reportes.php?action=export_ventas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&formato=csv" class="btn btn-success">Exportar CSV</a>
            </form>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <h3>Total Pedidos</h3>
                <div class="metric-value"><?php echo $datos_generales['total_pedidos'] ?? 0; ?></div>
            </div>
            <div class="metric-card">
                <h3>Ingresos Totales</h3>
                <div class="metric-value">$<?php echo number_format($datos_generales['ingresos_totales'] ?? 0, 2); ?></div>
            </div>
            <div class="metric-card">
                <h3>Ticket Promedio</h3>
                <div class="metric-value">$<?php echo number_format($datos_generales['ticket_promedio'] ?? 0, 2); ?></div>
            </div>
        </div>

        <div class="grid-2">
            <div class="chart-container">
                <h3>Ventas Diarias</h3>
                <div class="chart-wrapper">
                    <canvas id="ventasChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3>Ingresos por Categoría</h3>
                <div class="chart-wrapper">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-container">
            <h3 style="padding: 1.5rem 1.5rem 0; margin: 0;">Productos Más Vendidos</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio Unitario</th>
                        <th>Cantidad Vendida</th>
                        <th>Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos_top)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem; color: #9ca3af;">
                            No hay ventas en el período seleccionado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos_top as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                            <td>$<?php echo number_format($p['precio'], 2); ?></td>
                            <td><?php echo $p['cantidad_vendida']; ?> unidades</td>
                            <td><strong>$<?php echo number_format($p['ingresos'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h3 style="padding: 1.5rem 1.5rem 0; margin: 0;">Ingresos por Categoría</h3>
            <table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Cantidad Items</th>
                        <th>Ingresos Totales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias_top)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem; color: #9ca3af;">
                            No hay ventas en el período seleccionado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($categorias_top as $c): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['nombre'] ?? 'Sin Categoría'); ?></strong></td>
                            <td><?php echo $c['cantidad']; ?> items</td>
                            <td><strong>$<?php echo number_format($c['ingresos'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>js/chatbot.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar chatbot
            if (typeof WestitoChatbot !== 'undefined') {
                window.westito = new WestitoChatbot();
            }

            // Datos para gráficos
            const ventasData = <?php echo json_encode($ventas_diarias); ?>;
            const categoriasData = <?php echo json_encode($categorias_top); ?>;

            // Gráfico de ventas diarias
            if (ventasData.length > 0) {
                const ctx = document.getElementById('ventasChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ventasData.map(d => d.fecha),
                        datasets: [{
                            label: 'Ingresos ($)',
                            data: ventasData.map(d => parseFloat(d.ingresos) || 0),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#3b82f6'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Gráfico de categorías
            if (categoriasData.length > 0) {
                const ctx = document.getElementById('categoriasChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: categoriasData.map(c => c.nombre),
                        datasets: [{
                            data: categoriasData.map(c => parseFloat(c.ingresos) || 0),
                            backgroundColor: [
                                '#3b82f6',
                                '#10b981',
                                '#f59e0b',
                                '#ef4444',
                                '#8b5cf6',
                                '#ec4899'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>