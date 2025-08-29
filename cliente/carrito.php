<?php
require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireLogin();

$message = '';
$error = '';

// Manejar las acciones del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $productId = intval($_POST['product_id']);
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
                
                // Verificar que el producto exista y tenga stock disponible
                global $db;
                $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ? AND stock > 0");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $currentInCart = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId] : 0;
                    $newQuantity = min($quantity, $product['stock'] - $currentInCart);
                    
                    if ($newQuantity > 0) {
                        addToCart($productId, $newQuantity);
                        $message = 'Producto agregado al carrito';
                    } else {
                        $error = 'No hay suficiente stock disponible';
                    }
                } else {
                    $error = 'Producto no disponible';
                }
                break;
                
            case 'update':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                
                //  Solo permitir eliminar con el botón "Eliminar", no con cantidad 0
                // La cantidad mínima ahora es 1
                if ($quantity < 1) {
                    $quantity = 1;
                }
                
                // Verify stock
                global $db;
                $stmt = $db->prepare("SELECT stock FROM productos WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $maxQuantity = min($quantity, $product['stock']);
                    $_SESSION['cart'][$productId] = $maxQuantity;
                    $message = 'Carrito actualizado';
                }
                break;
                
            case 'remove':
                $productId = intval($_POST['product_id']);
                removeFromCart($productId);
                $message = 'Producto eliminado del carrito';
                break;
                
            case 'clear':
                $_SESSION['cart'] = [];
                $message = 'Carrito vaciado';
                break;
        }
    }
}

$cartItems = getCartItems();
$cartTotal = getCartTotal();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo SITE_NAME; ?></title>
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
        
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }
        
        .cart-items {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-header h2 {
            font-size: 1.25rem;
            color: #1e293b;
        }
        
        .cart-content {
            padding: 1.5rem;
        }
        
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .cart-item:last-child {
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
        
        .item-price {
            color: #059669;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .item-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .quantity-input {
            width: 60px;
            padding: 0.25rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        .quantity-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        .cart-summary {
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
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
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
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #374151;
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
            
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-controls {
                justify-content: center;
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
            <a href="<?php echo BASE_URL; ?>cliente/carrito.php" class="active">Carrito</a>
            <a href="<?php echo BASE_URL; ?>cliente/historial.php">Historial</a>
        </div>

        <!-- ... Mensajes de error/éxito ... -->

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <h2>Tu carrito está vacío</h2>
                <p>Añade productos desde el catálogo para continuar.</p>
                <a href="<?php echo BASE_URL; ?>cliente/" class="btn btn-primary">Ir al Catálogo</a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="cart-items">
                    <div class="cart-header">
                        <h2>Tu Carrito</h2>
                        <!--  Formulario para vaciar carrito, action vacío para procesar en la misma página. -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-danger btn-small">Vaciar Carrito</button>
                        </form>
                    </div>
                    <div class="cart-content">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <img src="<?php echo htmlspecialchars($item['product']['imagen_url']); ?>" alt="<?php echo htmlspecialchars($item['product']['nombre']); ?>" class="item-image">
                                <div class="item-info">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['product']['nombre']); ?></h3>
                                    <p class="item-price"><?php echo formatPrice($item['product']['precio']); ?></p>
                                    <!--  Control de cantidad con actualización automática -->
                                    <div class="item-controls">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>" class="product-id">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['product']['stock']; ?>" 
                                               class="quantity-input" 
                                               data-price="<?php echo $item['product']['precio']; ?>"
                                               data-id="<?php echo $item['product']['id']; ?>">
                                    </div>
                                </div>
                                <div class="item-subtotal">
                                    <p><?php echo formatPrice($item['subtotal']); ?></p>
                                    <!--  Formulario para eliminar item, action vacío. -->
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="cart-summary">
                    <h3>Resumen del Pedido</h3>
                    <div class="summary-item">
                        <span>Subtotal:</span>
                        <span><?php echo formatPrice($cartTotal); ?></span>
                    </div>
                    <!-- Aquí podrían ir más detalles como envío, impuestos, etc. -->
                    <div class="summary-total">
                        <span>Total:</span>
                        <span><?php echo formatPrice($cartTotal); ?></span>
                    </div>
                    <a href="<?php echo BASE_URL; ?>cliente/checkout.php" class="btn btn-success btn-block">Continuar Compra</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!--  Script para manejar la actualización automática del carrito -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        //  Seleccionar todos los inputs de cantidad
        const quantityInputs = document.querySelectorAll('.quantity-input');
        
        //  Para cada input de cantidad, añadir evento de cambio
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                const productId = this.getAttribute('data-id');
                const quantity = parseInt(this.value);
                const price = parseFloat(this.getAttribute('data-price'));
                
                //  Validar cantidad mínima (no permitir menos de 1)
                if (quantity < 1) {
                    this.value = 1;
                    return;
                }
                
                //  Validar cantidad máxima (stock)
                if (quantity > parseInt(this.getAttribute('max'))) {
                    this.value = this.getAttribute('max');
                    return;
                }
                
                //  Actualizar carrito mediante AJAX
                updateCart(productId, quantity, this);
            });
        });
        
        //  Función para actualizar el carrito con AJAX
        function updateCart(productId, quantity, inputElement) {
            //  Crear objeto FormData para enviar los datos
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            //  Mostrar indicador de carga
            const cartItem = inputElement.closest('.cart-item');
            cartItem.style.opacity = '0.6';
            
            //  Realizar petición AJAX
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                //  Actualizar subtotal del producto
                const price = parseFloat(inputElement.getAttribute('data-price'));
                const subtotal = price * quantity;
                const subtotalElement = cartItem.querySelector('.item-subtotal p');
                subtotalElement.textContent = formatPrice(subtotal);
                
                //  Actualizar total del carrito
                updateCartTotal();
                
                //  Actualizar contador de carrito en el header
                updateCartCount();
                
                //  Restaurar opacidad
                cartItem.style.opacity = '1';
                
                //  Ya no necesitamos verificar si la cantidad es 0
                // porque ahora no permitimos cantidad menor a 1
            })
            .catch(error => {
                console.error('Error al actualizar el carrito:', error);
                cartItem.style.opacity = '1';
                //  Mostrar mensaje de error
                alert('Error al actualizar el carrito. Por favor, inténtalo de nuevo.');
            });
        }
        
        //  Función para actualizar el total del carrito
        function updateCartTotal() {
            let total = 0;
            const items = document.querySelectorAll('.cart-item');
            
            items.forEach(item => {
                const input = item.querySelector('.quantity-input');
                const price = parseFloat(input.getAttribute('data-price'));
                const quantity = parseInt(input.value);
                total += price * quantity;
            });
            
            //  Actualizar los elementos del resumen
            const summarySubtotal = document.querySelector('.summary-item span:last-child');
            const summaryTotal = document.querySelector('.summary-total span:last-child');
            
            if (summarySubtotal && summaryTotal) {
                summarySubtotal.textContent = formatPrice(total);
                summaryTotal.textContent = formatPrice(total);
            }
        }
        
        //  Función para actualizar el contador de carrito en el header
        function updateCartCount() {
            let count = 0;
            const items = document.querySelectorAll('.quantity-input');
            
            items.forEach(item => {
                count += parseInt(item.value);
            });
            
            const cartCountElement = document.querySelector('.header-actions .btn-primary');
            if (cartCountElement) {
                cartCountElement.textContent = `Carrito (${count})`;
            }
        }
        
        //  Función para verificar si el carrito está vacío
        function checkEmptyCart() {
            const items = document.querySelectorAll('.cart-item');
            if (items.length === 0) {
                //  Recargar la página para mostrar el mensaje de carrito vacío
                window.location.reload();
            }
        }
        
        //  Función para dar formato al precio
        function formatPrice(price) {
            return '$' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    });
    </script>
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <script src="<?php echo BASE_URL; ?>js/chatbot.js"></script>
</body>
</html>
