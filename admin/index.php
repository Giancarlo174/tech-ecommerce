<?php

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/alertas.php';

$auth->requireAdmin();

$products = getProducts(null, 10);
$categories = getCategories();
$brands = getBrands();

// Obtener los últimos 5 pedidos
global $db;
$stmt = $db->prepare("SELECT p.*, u.correo FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario = u.id ORDER BY p.creado_en DESC LIMIT 5");
$stmt->execute();
$recentOrders = $stmt->fetchAll();

// Obtener el total de productos activos, pedidos de hoy e ingresos de hoy
$stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE stock > 0");
$stmt->execute();
$totalProducts = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(creado_en) = CURDATE()");
$stmt->execute();
$todayOrders = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT SUM(total) as total FROM pedidos WHERE DATE(creado_en) = CURDATE()");
$stmt->execute();
$todayRevenue = $stmt->fetch()['total'] ?? 0;

// Obtener alertas de inventario
$alertasInventario = getResumenAlertasInventario();
$productosAlertas = obtenerAlertasInventario(5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/chatbot.css">
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
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            color: #1e293b;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        .product-list,
        .order-list {
            list-style: none;
        }
        
        .product-item,
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .product-item:last-child,
        .order-item:last-child {
            border-bottom: none;
        }
        
        .product-info h4,
        .order-info h4 {
            font-size: 0.875rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .product-info p,
        .order-info p {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .price {
            font-weight: 600;
            color: #059669;
        }
        
        .stock {
            font-size: 0.75rem;
            color: #64748b;
        }
        
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
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
        }
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
            <a href="<?php echo BASE_URL; ?>admin/" class="active">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>admin/productos.php">Productos</a>
            <a href="<?php echo BASE_URL; ?>admin/categorias.php">Categorías</a>
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php">Pedidos</a>
            <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php">Inventarios</a>
            <a href="<?php echo BASE_URL; ?>admin/reportes_ventas.php">Reportes</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Productos Activos</h3>
                <div class="value"><?php echo $totalProducts; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pedidos Hoy</h3>
                <div class="value"><?php echo $todayOrders; ?></div>
            </div>
            <div class="stat-card">
                <h3>Ingresos Hoy</h3>
                <div class="value"><?php echo formatPrice($todayRevenue); ?></div>
            </div>
            <div class="stat-card" style="background: <?php echo ($alertasInventario['critico'] > 0 ? '#fee' : '#fef3c7'); ?>; border-left: 4px solid <?php echo ($alertasInventario['critico'] > 0 ? '#ef4444' : '#f59e0b'); ?>;">
                <h3>⚠️ Alertas Stock</h3>
                <div class="value"><?php echo ($alertasInventario['critico'] ?? 0) + ($alertasInventario['bajo'] ?? 0); ?></div>
            </div>
        </div>

        <?php if (($alertasInventario['critico'] ?? 0) > 0 || ($alertasInventario['bajo'] ?? 0) > 0): ?>
        <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; color: #92400e;">
            <strong>⚠️ Alertas de Inventario</strong>
            <p style="margin-top: 0.5rem; font-size: 0.875rem;">
                Tienes <strong><?php echo $alertasInventario['critico'] ?? 0; ?> productos sin stock</strong> y 
                <strong><?php echo $alertasInventario['bajo'] ?? 0; ?> con stock bajo</strong>.
                <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php" style="color: #92400e; text-decoration: underline;">Ver reportes</a>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2>Productos Recientes</h2>
                    <a href="<?php echo BASE_URL; ?>admin/productos.php" class="btn btn-primary">Ver Todos</a>
                </div>
                <div class="card-content">
                    <ul class="product-list">
                        <?php foreach ($products as $product): ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['nombre']); ?></h4>
                                    <p><?php echo htmlspecialchars($product['categoria'] ?? 'Sin categoría'); ?></p>
                                </div>
                                <div>
                                    <div class="price"><?php echo formatPrice($product['precio']); ?></div>
                                    <div class="stock">Stock: <?php echo $product['stock']; ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Pedidos Recientes</h2>
                    <a href="<?php echo BASE_URL; ?>admin/pedidos.php" class="btn btn-primary">Ver Todos</a>
                </div>
                <div class="card-content">
                    <ul class="order-list">
                        <?php foreach ($recentOrders as $order): ?>
                            <li class="order-item">
                                <div class="order-info">
                                    <h4><?php echo htmlspecialchars($order['nombre_cliente']); ?></h4>
                                    <p><?php echo date('d/m/Y H:i', strtotime($order['creado_en'])); ?></p>
                                </div>
                                <div class="price"><?php echo formatPrice($order['total']); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>⚠️ Productos con Alertas</h2>
                    <a href="<?php echo BASE_URL; ?>admin/reportes_inventarios.php" class="btn btn-primary">Ver Todos</a>
                </div>
                <div class="card-content">
                    <?php if (!empty($productosAlertas)): ?>
                    <ul class="product-list">
                        <?php foreach ($productosAlertas as $alert): ?>
                            <li class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($alert['nombre']); ?></h4>
                                    <p><?php echo htmlspecialchars($alert['categoria'] ?? 'Sin categoría'); ?></p>
                                </div>
                                <div>
                                    <div class="price">Stock: <?php echo $alert['stock']; ?></div>
                                    <div class="stock" style="color: <?php echo ($alert['stock'] == 0 ? '#ef4444' : '#f59e0b'); ?>;">
                                        <?php echo ($alert['stock'] == 0 ? '🔴 Crítico' : '🟠 Bajo'); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p style="color: #9ca3af; text-align: center; padding: 2rem;">
                        ✅ No hay alertas de inventario
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <script src="<?php echo BASE_URL; ?>js/chatbot.js" charset="utf-8"></script>
</body>
</html>
