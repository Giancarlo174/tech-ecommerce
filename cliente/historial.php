<?php
require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireLogin();

// Limpia el historial de compras expirado 
cleanExpiredHistory();

$message = '';
$error = '';

// Mnajear la acción de volver a comprar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $productId = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    // Verifica que el producto exista y tenga suficiente stock
    global $db;
    $stmt = $db->prepare("SELECT * FROM productos WHERE id = ? AND stock >= ?");
    $stmt->execute([$productId, $quantity]);
    $product = $stmt->fetch();
    
    if ($product) {
        addToCart($productId, $quantity);
        $message = 'Producto agregado al carrito nuevamente';
    } else {
        $error = 'El producto no está disponible o no hay suficiente stock';
    }
}

// Manejar la acción de borrar el historial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_history') {
    global $db;
    $stmt = $db->prepare("DELETE FROM historial_compras WHERE id_usuario = ?");
    if ($stmt->execute([$_SESSION['user_id']])) {
        $message = 'Historial eliminado exitosamente';
    } else {
        $error = 'Error al eliminar el historial';
    }
}

// Obtener el historial de compras del usuario
global $db;
$stmt = $db->prepare("
    SELECT hc.*, p.nombre, p.precio, p.imagen_url, p.stock 
    FROM historial_compras hc 
    LEFT JOIN productos p ON hc.id_producto = p.id 
    WHERE hc.id_usuario = ? 
    ORDER BY hc.fecha_compra DESC
");
$stmt->execute([$_SESSION['user_id']]);
$historyItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras - <?php echo SITE_NAME; ?></title>
    <!--  SweetAlert2 CDN para notificaciones mejoradas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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
        

        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .history-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .history-header h2 {
            font-size: 1.25rem;
            color: #1e293b;
        }
        
        .history-content {
            padding: 1.5rem;
        }
        
        .history-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .item-details {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .item-date {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .item-price {
            font-weight: 600;
            color: #059669;
            margin-bottom: 0.5rem;
        }
        
        .empty-history {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-history h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #374151;
        }
        
        .unavailable {
            opacity: 0.6;
        }
        
        .unavailable .item-name::after {
            content: " (No disponible)";
            color: #ef4444;
            font-weight: normal;
            font-size: 0.875rem;
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
            
            .history-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-actions {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><a href="<?php echo BASE_URL; ?>cliente/"><?php echo SITE_NAME; ?></a></h1>
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
            <a href="<?php echo BASE_URL; ?>cliente/historial.php" class="active">Historial</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="history-card">
            <?php if (!empty($historyItems)): ?>
            <div class="history-header">
                <h2>Tu Historial de Compras</h2>
                <!--  Formulario para borrar el historial -->
                <form id="clearHistoryForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_history">
                    <button type="submit" class="btn btn-danger">Borrar Historial</button>
                </form>
            </div>
            <div class="history-content">
                <?php foreach ($historyItems as $item): ?>
                    <div class="history-item <?php echo ($item['stock'] <= 0) ? 'unavailable' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($item['imagen_url'] ?: BASE_URL . 'assets/images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>" class="item-image">
                        <div class="item-info">
                            <h3 class="item-name"><?php echo htmlspecialchars($item['nombre']); ?></h3>
                            <p class="item-details">Cantidad: <?php echo $item['cantidad']; ?> | Precio unitario: <?php echo formatPrice($item['precio']); ?></p>
                            <p class="item-date">Comprado el: <?php echo date('d/m/Y H:i', strtotime($item['fecha_compra'])); ?></p>
                        </div>
                        <div class="item-actions">
                            <span class="item-price"><?php echo formatPrice($item['precio'] * $item['cantidad']); ?></span>
                            <?php if ($item['stock'] > 0): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reorder">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id_producto']; ?>">
                                    <input type="hidden" name="quantity" value="<?php echo $item['cantidad']; ?>">
                                    <button type="submit" class="btn btn-success btn-small">Volver a Comprar</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-small" disabled>No Disponible</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-history">
                <h3>Tu historial de compras está vacío</h3>
                <p>No has realizado ninguna compra todavía.</p>
                <a href="<?php echo BASE_URL; ?>cliente/" class="btn btn-primary">Ver Productos</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    //  Muestra mensajes de PHP (éxito o error) usando SweetAlert2.
    <?php if (!empty($message)): ?>
    Swal.fire({
        title: '¡Éxito!',
        text: '<?php echo addslashes($message); ?>',
        icon: 'success',
        confirmButtonText: 'Aceptar'
    });
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    Swal.fire({
        title: 'Error',
        text: '<?php echo addslashes($error); ?>',
        icon: 'error',
        confirmButtonText: 'Aceptar'
    });
    <?php endif; ?>

    //  Maneja la confirmación para borrar el historial.
    const clearHistoryForm = document.getElementById('clearHistoryForm');
    if (clearHistoryForm) {
        clearHistoryForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Previene el envío normal del formulario.
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Quieres borrar todo tu historial? Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, borrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si el usuario confirma, se envía el formulario.
                    event.target.submit();
                }
            });
        });
    }
});
</script>
</body>
</html>
