<?php

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAdmin();

global $db;

// Parámetros de filtro
$stock_minimo = isset($_GET['stock_minimo']) ? (int)$_GET['stock_minimo'] : 10;
$filtro_categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';

// Obtener categorías para el filtro
$stmt = $db->prepare("SELECT * FROM categorias ORDER BY nombre");
$stmt->execute();
$categorias = $stmt->fetchAll();

$query = "SELECT p.*, c.nombre as categoria_nombre,
          COALESCE(dp.vendidos_total, 0) as vendidos_total
          FROM productos p 
          LEFT JOIN categorias c ON p.id_categoria = c.id 
          LEFT JOIN (
              SELECT id_producto, SUM(cantidad) as vendidos_total
              FROM pedido_detalles
              GROUP BY id_producto
          ) dp ON p.id = dp.id_producto
          WHERE 1=1";
$params = [];

if ($filtro_categoria > 0) {
    $query .= " AND p.id_categoria = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_estado === 'bajo') {
    $query .= " AND p.stock > 0 AND p.stock <= ?";
    $params[] = $stock_minimo;
} elseif ($filtro_estado === 'critico') {
    $query .= " AND p.stock = 0";
} elseif ($filtro_estado === 'normal') {
    $query .= " AND p.stock > ?";
    $params[] = $stock_minimo;
}

$query .= " ORDER BY p.stock ASC, p.nombre ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Calcular métricas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE stock = 0");
$stmt->execute();
$stock_critico = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE stock > 0 AND stock <= ?");
$stmt->execute([$stock_minimo]);
$stock_bajo = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT SUM(stock) as total FROM productos");
$stmt->execute();
$stock_total = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT SUM(precio * stock) as valor FROM productos WHERE stock > 0");
$stmt->execute();
$valor_inventario = $stmt->fetch()['valor'] ?? 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Inventarios - <?php echo SITE_NAME; ?></title>
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
        
        .metric-card.critical .metric-value { color: #ef4444; }
        .metric-card.warning .metric-value { color: #f59e0b; }
        .metric-card.success .metric-value { color: #10b981; }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .inventory-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .inventory-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table thead {
            background: #f3f4f6;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #6b7280;
        }
        
        .inventory-table th,
        .inventory-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .inventory-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stock-badge.critical {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .stock-badge.low {
            background: #fef3c7;
            color: #92400e;
        }
        
        .stock-badge.normal {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        /* Media Queries simplificadas para el diseño */
        @media (max-width: 1024px) {
            .filter-row {
                grid-template-columns: 1fr;
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

        /* Eliminar estilos inservibles (pagination, chart-container, nav-tabs, sidebar, main-content) */
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
            <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php" class="active">Inventarios</a>
            <a href="<?php echo BASE_URL; ?>admin/reportes_ventas.php">Reportes</a>
        </div>
        
        <div class="metrics-grid">
            <div class="metric-card success">
                <h3>Stock Total</h3>
                <div class="metric-value"><?php echo number_format($stock_total); ?></div>
            </div>
            <div class="metric-card">
                <h3>Valor Inventario</h3>
                <div class="metric-value">$<?php echo number_format($valor_inventario, 2); ?></div>
            </div>
            <div class="metric-card warning">
                <h3>Stock Bajo</h3>
                <div class="metric-value"><?php echo $stock_bajo; ?></div>
            </div>
            <div class="metric-card critical">
                <h3>Stock Crítico</h3>
                <div class="metric-value"><?php echo $stock_critico; ?></div>
            </div>
        </div>

        <?php if ($stock_critico > 0 || $stock_bajo > 0): ?>
        <div class="alert alert-warning">
            ⚠️ Tienes <strong><?php echo $stock_bajo + $stock_critico; ?></strong> productos con stock bajo o crítico. 
            Considera realizar nuevas compras.
        </div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="categoria">Categoría</label>
                        <select name="categoria" id="categoria">
                            <option value="0">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="estado">Estado Stock</label>
                        <select name="estado" id="estado">
                            <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="normal" <?php echo $filtro_estado === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="bajo" <?php echo $filtro_estado === 'bajo' ? 'selected' : ''; ?>>Bajo</option>
                            <option value="critico" <?php echo $filtro_estado === 'critico' ? 'selected' : ''; ?>>Crítico</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="stock_minimo">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" id="stock_minimo" value="<?php echo $stock_minimo; ?>" min="1">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php" class="btn btn-secondary">Limpiar</a>
                <a href="<?php echo BASE_URL; ?>admin/api_reportes.php?action=export_inventario&formato=pdf" class="btn btn-success" target="_blank">Exportar PDF</a>
                <a href="<?php echo BASE_URL; ?>admin/api_reportes.php?action=export_inventario&formato=csv" class="btn btn-success">Exportar CSV</a>
            </form>
        </div>

        <div class="inventory-table">
            <h3 style="padding: 1.5rem 1.5rem 0; margin: 0;">Inventario de Productos</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Valor Stock</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #9ca3af;">
                            No hay productos que cumplan los criterios de búsqueda
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): 
                            $valor_stock = $p['precio'] * $p['stock'];
                            
                            if ($p['stock'] == 0) {
                                $estado_clase = 'critical';
                                $estado_texto = 'Crítico';
                            } elseif ($p['stock'] <= $stock_minimo) {
                                $estado_clase = 'low';
                                $estado_texto = 'Bajo';
                            } else {
                                $estado_clase = 'normal';
                                $estado_texto = 'Normal';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['categoria_nombre'] ?? 'N/A'); ?></td>
                            <td>$<?php echo number_format($p['precio'], 2); ?></td>
                            <td><?php echo $p['stock']; ?></td>
                            <td><?php echo $stock_minimo; ?></td>
                            <td>$<?php echo number_format($valor_stock, 2); ?></td>
                            <td>
                                <span class="stock-badge <?php echo $estado_clase; ?>">
                                    <?php echo $estado_texto; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>js/chatbot.js" charset="utf-8"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof WestitoChatbot !== 'undefined') {
                window.westito = new WestitoChatbot();
            }
        });
    </script>
</body>
</html>