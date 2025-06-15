<?php
require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireLogin();

$cartItems = getCartItems();
$cartTotal = getCartTotal();

if (empty($cartItems)) {
    header('Location: ' . BASE_URL . 'cliente/carrito.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitizeInput($_POST['nombre']);
    $celular = sanitizeInput($_POST['celular']);
    $direccion = sanitizeInput($_POST['direccion']);
    
    if (empty($nombre) || empty($celular) || empty($direccion)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        global $db;
        
        try {
            $db->getConnection()->beginTransaction();
            
            // Verificar stock denuevo antes de procesar el pedido
            $stockError = false;
            foreach ($cartItems as $item) {
                $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ?");
                $stmt->execute([$item['product']['id']]);
                $currentStock = $stmt->fetch()['stock'];
                
                if ($currentStock < $item['quantity']) {
                    $stockError = true;
                    break;
                }
            }
            
            if ($stockError) {
                $db->getConnection()->rollBack();
                $error = 'Algunos productos no tienen suficiente stock disponible';
            } else {
                // Crear el pedido
                $stmt = $db->prepare("INSERT INTO pedidos (id_usuario, nombre_cliente, celular, direccion, total) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $nombre, $celular, $direccion, $cartTotal]);
                $orderId = $db->lastInsertId();
                
                // Añadir mas detalles y actualizar stock
                foreach ($cartItems as $item) {
                    // Añadir detalles del pedido
                    $stmt = $db->prepare("INSERT INTO pedido_detalles (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$orderId, $item['product']['id'], $item['quantity'], $item['product']['precio']]);
                    
                    // Actualizar stock del producto
                    $stmt = $db->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['product']['id']]);
                    
                    // Registrar en historial de compras
                    $stmt = $db->prepare("INSERT INTO historial_compras (id_usuario, id_producto, cantidad) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $item['product']['id'], $item['quantity']]);
                }
                
                $db->getConnection()->commit();
                
                // Limpiar el carrito de compras
                $_SESSION['cart'] = [];
                
                $message = "¡Pedido realizado exitosamente! Tu número de pedido es: #$orderId";
            }
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            $error = 'Error al procesar el pedido. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
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
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
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
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .message.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }
        
        .checkout-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .order-summary {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            height: fit-content;
        }
        
        .summary-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .item-quantity {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .item-price {
            font-weight: 600;
            color: #059669;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            margin-top: 1rem;
        }
        
        .success-message {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
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
            
            .checkout-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?></h1>
        <div class="header-actions">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
            <a href="<?php echo BASE_URL; ?>cliente/carrito.php" class="btn btn-primary">
                Carrito (<?php echo isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?>)
            </a>
            <a href="<?php echo BASE_URL; ?>cliente/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
        </div>
    </div>
    
    <div class="container">
        <div class="nav-menu">
            <a href="<?php echo BASE_URL; ?>cliente/">Catálogo</a>
            <a href="<?php echo BASE_URL; ?>cliente/carrito.php">Carrito</a>
            <a href="<?php echo BASE_URL; ?>cliente/historial.php">Historial</a>
        </div>

        <!-- ... Mensajes de error/éxito ... -->

        <?php if (!empty($message)): ?>
            <div class="message success"><?php echo $message; ?></div>
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>cliente/historial.php" class="btn btn-primary">Ver Historial de Pedidos</a>
                <a href="<?php echo BASE_URL; ?>cliente/" class="btn btn-secondary">Seguir Comprando</a>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($message)): // Solo mostrar formulario si no hay mensaje de éxito ?>
        <div class="checkout-container">
            <div class="checkout-form">
                <h2 class="form-title">Información de Envío</h2>
                 <!--  Formulario de checkout, action vacío para procesar en la misma página. -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="celular">Número de Celular</label>
                        <input type="tel" id="celular" name="celular" value="<?php echo htmlspecialchars($_POST['celular'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección de Envío</label>
                        <textarea id="direccion" name="direccion" required><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">Realizar Pedido</button>
                </form>
            </div>
            
            <div class="order-summary">
                <h3 class="summary-title">Resumen del Pedido</h3>
                <?php foreach ($cartItems as $item): ?>
                    <div class="order-item">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['product']['nombre']); ?></div>
                            <div class="item-quantity">Cantidad: <?php echo $item['quantity']; ?></div>
                        </div>
                        <div class="item-price"><?php echo formatPrice($item['subtotal']); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="summary-total">
                    <span>Total</span>
                    <span><?php echo formatPrice($cartTotal); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
