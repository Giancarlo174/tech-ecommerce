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
    // Array para almacenar errores de validación
    $errores = [];
    $datos_validos = [];
    
    // Validar nombre: obligatorio y solo letras y espacios
    if (!isset($_POST['nombre']) || empty(trim($_POST['nombre']))) {
        $errores['nombre'] = 'El nombre completo es obligatorio';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]+$/', $_POST['nombre'])) {
        $errores['nombre'] = 'El nombre solo puede contener letras y espacios';
    } else {
        $datos_validos['nombre'] = sanitizeInput($_POST['nombre']);
    }
    
    // Validar celular: obligatorio, solo números y mínimo 7 dígitos
    if (!isset($_POST['celular']) || empty(trim($_POST['celular']))) {
        $errores['celular'] = 'El número de celular es obligatorio';
    } elseif (!preg_match('/^\d+$/', $_POST['celular'])) {
        $errores['celular'] = 'El número de celular debe contener solo dígitos';
    } elseif (strlen($_POST['celular']) < 7) {
        $errores['celular'] = 'El número de celular debe tener al menos 7 dígitos';
    } else {
        $datos_validos['celular'] = sanitizeInput($_POST['celular']);
    }
    
    // Validar dirección: obligatoria y sin código HTML
    if (!isset($_POST['direccion']) || empty(trim($_POST['direccion']))) {
        $errores['direccion'] = 'La dirección de envío es obligatoria';
    } else {
        $direccion_limpia = preg_replace('/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i', '', $_POST['direccion']);
        $datos_validos['direccion'] = sanitizeInput($direccion_limpia);
    }

    //NUEVAS VALIDACIONES DE MÉTODO DE PAGO -- JOHAB

    // Validar método de pago (ficticio: tarjeta / yappy)
    if (!isset($_POST['payment_method']) || empty($_POST['payment_method'])) {
        $errores['payment_method'] = 'Seleccione un método de pago';
    } else {
        $pm = $_POST['payment_method'];
        if ($pm === 'tarjeta') {
            // Validar datos de tarjeta (ficticios)
            $card = $_POST['card_number'] ?? '';
            $exp = $_POST['card_expiry'] ?? '';
            $cvv = $_POST['card_cvv'] ?? '';
            
            // Ejemplo válido: '4111 1111 1111 1111'
            if (empty(trim($card))) {
                $errores['card_number'] = 'El número de tarjeta es obligatorio';
            } elseif (!preg_match('/^\d{13,19}$/', preg_replace('/\s+/', '', $card))) {
                $errores['card_number'] = 'Número de tarjeta inválido';
            } else {
                // Guardar máscara (no almacenar número completo en sesión)
                $masked = preg_replace('/\d(?=\d{4})/', '*', preg_replace('/\s+/', '', $card));
                $datos_validos['card_mask'] = $masked;
            }

            // Ejemplo válido: '12/28'
            if (empty(trim($exp))) {
                $errores['card_expiry'] = 'La fecha de expiración es obligatoria';
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $exp)) {
                $errores['card_expiry'] = 'Fecha de expiración inválida (MM/YY)';
            } else {
                $datos_validos['card_expiry'] = sanitizeInput($exp);
            }

            // Ejemplo válido: '123' 
            if (empty(trim($cvv))) {
                $errores['card_cvv'] = 'El código CVV es obligatorio';
            } elseif (!preg_match('/^\d{3}$/', $cvv)) {
                $errores['card_cvv'] = 'CVV inválido (3 dígitos)';
            } else {
                $datos_validos['card_cvv'] = '***'; // no almacenar CVV real
            }

            $datos_validos['metodo_pago'] = 'tarjeta';

        // Ejemplo válido: '12345678'
        } elseif ($pm === 'yappy') {
            $yappy = $_POST['yappy_id'] ?? '';
            if (empty(trim($yappy))) {
                $errores['yappy_id'] = 'El identificador de Yappy es obligatorio';
            } elseif (!preg_match('/^\d{7,20}$/', $yappy)) {
                $errores['yappy_id'] = 'Identificador de Yappy inválido';
            } else {
                $datos_validos['yappy_id'] = sanitizeInput($yappy);
            }
            $datos_validos['metodo_pago'] = 'yappy';
        } else {
            $errores['payment_method'] = 'Método de pago no válido';
        }
    }
    
    // Si hay errores, guardarlos en la sesión y redirigir
    if (!empty($errores)) {
        // Evitar guardar número de tarjeta completo en sesión: enmascarar si viene
        $preserve = $_POST;
        if (isset($preserve['card_number'])) {
            $preserve['card_number'] = preg_replace('/\d(?=\d{4})/', '*', preg_replace('/\s+/', '', $preserve['card_number']));
        }
        if (isset($preserve['card_cvv'])) {
            unset($preserve['card_cvv']);
        }

        $_SESSION['form_errors'] = $errores;
        $_SESSION['form_data'] = $preserve;
        header('Location: ' . BASE_URL . 'cliente/checkout.php');
        exit;
    }
    
    // Si no hay errores, proceder con el procesamiento
    $nombre = $datos_validos['nombre'];
    $celular = $datos_validos['celular'];
    $direccion = $datos_validos['direccion'];
    $metodoPago = $datos_validos['metodo_pago'] ?? 'desconocido';
    
    global $db;
        
        try {
            $db->beginTransaction();
            
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
                $db->rollBack();
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
                
                $db->commit();
            
            // --- INICIO DE LA INTEGRACIÓN DE ENVÍO DE FACTURA ---
            
            $invoiceApiUrl = BASE_URL . 'api/send_invoice_email.php';
            $orderIdForApi = $orderId; // El ID del pedido que se acaba de crear

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $invoiceApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "order_id=$orderIdForApi");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode != 200) {
                // Si falla el envío de email, el pedido ya está confirmado. Solo logueamos el error.
                error_log("Error al enviar factura #$orderId: HTTP $httpCode - $curlError - $apiResponse");
            }
            //  FIN DE LA INTEGRACIÓN DE ENVÍO DE FACTURA 
           
            
            // Limpiar el carrito de compras
            $_SESSION['cart'] = [];
            
            // Mostrar método de pago elegido en el mensaje (ficticio)
            $metodoTexto = $metodoPago === 'tarjeta' ? 'Tarjeta de Crédito' : ($metodoPago === 'yappy' ? 'Yappy' : 'Desconocido');
            $message = "¡Pedido realizado exitosamente! Tu número de pedido es: #$orderId. Método de pago: $metodoTexto. Se ha enviado una copia de la factura a tu correo electrónico.";
        }
    } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al procesar el pedido. Inténtalo de nuevo.';
        }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
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

        /* Confirmation card */
        .order-confirmation { display:flex; justify-content:center; margin:1.5rem 0; }
        .confirm-card { background: #fff; border-radius:12px; box-shadow:0 10px 30px rgba(2,6,23,0.08); display:flex; gap:1.25rem; padding:1.5rem; max-width:900px; width:100%; align-items:center; }
        .confirm-icon { flex:0 0 80px; display:flex; align-items:center; justify-content:center; }
        .confirm-body { flex:1; }
        .confirm-body h2 { margin-bottom:0.25rem; color:#0f172a; }
        .confirm-body .muted { margin-bottom:0.75rem; color:#475569; }
        .order-info { display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1rem; color:#334155; }
        .order-items { background:#f8fafc; padding:0.75rem; border-radius:8px; margin-bottom:1rem; }
        .order-items h4 { margin:0 0 0.5rem 0; }
        .order-items ul { list-style:none; margin:0; padding:0; }
        .order-items li { display:flex; justify-content:space-between; gap:1rem; padding:0.35rem 0; border-bottom:1px dashed #e6eef7; }
        .order-items li:last-child { border-bottom: none; }
        .order-total { text-align:right; margin-top:0.5rem; font-size:1.05rem; }
        .confirm-actions { display:flex; gap:0.75rem; margin-top:0.75rem; }
        
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
        
        /* Estilos para las validaciones de formularios */
        .is-invalid {
            border: 1px solid #dc3545 !important;
            background-color: #fff8f8;
        }
        
        .text-danger {
            color: #dc3545;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }
        
        /* Estilos para el spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-loading {
            opacity: 0.8;
            pointer-events: none;
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

        /* estilos para selección por imagen en métodos de pago */
        .payment-options {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .payment-options input[type="radio"] {
            /* mantener accesible pero oculto visualmente */
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(1px, 1px, 1px, 1px);
            white-space: nowrap;
        }
        .payment-option {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border: 2px solid transparent;
            border-radius: 8px;
            background: #ffffff;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.06s ease;
        }
        .payment-option img {
            display: block;
            max-height: 40px;
            max-width: 120px;
            object-fit: contain;
        }
        /* estilo cuando el radio está seleccionado */
        .payment-options input[type="radio"]:checked + .payment-option {
            border-color: #3b82f6;
            box-shadow: 0 4px 10px rgba(59,130,246,0.12);
            transform: translateY(-2px);
        }
        /* foco por teclado */
        .payment-options input[type="radio"]:focus + .payment-option {
            outline: 3px solid rgba(59,130,246,0.18);
            outline-offset: 2px;
        }
        /* clase accesible para texto oculto */
        .sr-only {
            position: absolute !important;
            height: 1px; width: 1px;
            overflow: hidden;
            clip: rect(1px, 1px, 1px, 1px);
            white-space: nowrap;
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
            <a href="<?php echo BASE_URL; ?>cliente/historial.php">Historial</a>
        </div>

        <!-- ... Mensajes de error/éxito ... -->

        <?php if (!empty($message)): ?>
            <div class="order-confirmation" role="status" aria-live="polite">
                <div class="confirm-card">
                    <div class="confirm-icon">
                        <!-- check mark -->
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="12" cy="12" r="12" fill="#10B981"/>
                            <path d="M7 12.5l2.5 2.5L17 8" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="confirm-body">
                        <h2>¡Pedido realizado!</h2>
                        <p class="muted">Gracias por tu compra. Detalles del pedido:</p>
                        <div class="order-info">
                            <div><strong>Número de pedido:</strong> #<?php echo htmlspecialchars($orderId ?? '—'); ?></div>
                            <div><strong>Método de pago:</strong> <?php echo htmlspecialchars($metodoTexto ?? '—'); ?></div>
                            <div><strong>Factura:</strong> Se ha enviado una copia a tu correo.</div>
                        </div>

                        <div class="order-items">
                            <h4>Resumen</h4>
                            <?php if (!empty($cartItems)): ?>
                                <ul>
                                <?php foreach ($cartItems as $it): ?>
                                    <li>
                                        <span class="item-name"><?php echo htmlspecialchars($it['product']['nombre']); ?></span>
                                        <span class="item-qty">x<?php echo (int)$it['quantity']; ?></span>
                                        <span class="item-price"><?php echo formatPrice($it['subtotal']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="order-total"><strong>Total:</strong> <?php echo formatPrice($cartTotal); ?></div>
                        </div>

                        <div class="confirm-actions">
                            <a href="<?php echo BASE_URL; ?>cliente/historial.php" class="btn btn-primary">Ver historial</a>
                            <a href="<?php echo BASE_URL; ?>cliente/" class="btn btn-secondary">Seguir comprando</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($message)): // Solo mostrar formulario si no hay mensaje de éxito ?>
        <div class="checkout-container">
            <div class="checkout-form">
                <h2 class="form-title">Información de Envío</h2>
                <?php
                // Inicializar variables para errores
                $form_errors = $_SESSION['form_errors'] ?? [];
                $form_data = $_SESSION['form_data'] ?? [];
                
                // Limpiar errores y datos de la sesión después de usarlos
                unset($_SESSION['form_errors'], $_SESSION['form_data']);
                ?>
                 <!--  Formulario de checkout, action vacío para procesar en la misma página. -->
                <form method="POST" action="" id="checkout-form">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" 
                            value="<?php echo htmlspecialchars(isset($form_data['nombre']) ? $form_data['nombre'] : ($_POST['nombre'] ?? '')); ?>" 
                            class="<?php echo isset($form_errors['nombre']) ? 'is-invalid' : ''; ?>" required>
                        <?php if (isset($form_errors['nombre'])): ?>
                            <small class="text-danger"><?php echo $form_errors['nombre']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="celular">Número de Celular</label>
                        <input type="tel" id="celular" name="celular" 
                            value="<?php echo htmlspecialchars(isset($form_data['celular']) ? $form_data['celular'] : ($_POST['celular'] ?? '')); ?>" 
                            class="<?php echo isset($form_errors['celular']) ? 'is-invalid' : ''; ?>" required>
                        <?php if (isset($form_errors['celular'])): ?>
                            <small class="text-danger"><?php echo $form_errors['celular']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección de Envío</label>
                        <textarea id="direccion" name="direccion" 
                            class="<?php echo isset($form_errors['direccion']) ? 'is-invalid' : ''; ?>" required><?php echo htmlspecialchars(isset($form_data['direccion']) ? $form_data['direccion'] : ($_POST['direccion'] ?? '')); ?></textarea>
                        <?php if (isset($form_errors['direccion'])): ?>
                            <small class="text-danger"><?php echo $form_errors['direccion']; ?></small>
                        <?php endif; ?>
                    </div>

                    <!-- Sección de método de pago (ficticio) -->
                    <div class="form-group">
                        <label>Método de Pago</label>
                        <div class="payment-options">
                            <input type="radio" id="pm-tarjeta" name="payment_method" value="tarjeta"
                                <?php echo ((isset($form_data['payment_method']) && $form_data['payment_method'] === 'tarjeta') || ($_POST['payment_method'] ?? '') === 'tarjeta') ? 'checked' : ''; ?>>
                            <label for="pm-tarjeta" class="payment-option" title="Tarjeta de Crédito (ficticio)">
                                <img src="<?php echo BASE_URL; ?>IMAGENES/tarjeta.jpg" alt="Tarjeta de Crédito">
                                <span class="sr-only">Tarjeta de Crédito</span>
                            </label>

                            <input type="radio" id="pm-yappy" name="payment_method" value="yappy"
                                <?php echo ((isset($form_data['payment_method']) && $form_data['payment_method'] === 'yappy') || ($_POST['payment_method'] ?? '') === 'yappy') ? 'checked' : ''; ?>>
                            <label for="pm-yappy" class="payment-option" title="Yappy (ficticio)">
                                <img src="<?php echo BASE_URL; ?>IMAGENES/yappy.png" alt="Yappy">
                                <span class="sr-only">Yappy</span>
                            </label>
                        </div>
                        <?php if (isset($form_errors['payment_method'])): ?>
                            <small class="text-danger"><?php echo $form_errors['payment_method']; ?></small>
                        <?php endif; ?>
                    </div>

                    <!-- Campos de tarjeta (solo visibles cuando se elige tarjeta) -->
                    <div id="tarjeta-fields" style="display: none;">
                        <div class="form-group">
                            <label for="card_number">Número de Tarjeta</label>
                            <input type="text" id="card_number" name="card_number" inputmode="numeric"
                                value="<?php echo htmlspecialchars($form_data['card_number'] ?? ($_POST['card_number'] ?? '')); ?>" 
                                class="<?php echo isset($form_errors['card_number']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($form_errors['card_number'])): ?>
                                <small class="text-danger"><?php echo $form_errors['card_number']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group" style="display:flex; gap:1rem;">
                            <div style="flex:1;">
                                <label for="card_expiry">Expiración (MM/AA)</label>
                                <input type="text" id="card_expiry" name="card_expiry"
                                    value="<?php echo htmlspecialchars($form_data['card_expiry'] ?? ($_POST['card_expiry'] ?? '')); ?>" 
                                    class="<?php echo isset($form_errors['card_expiry']) ? 'is-invalid' : ''; ?>">
                                <?php if (isset($form_errors['card_expiry'])): ?>
                                    <small class="text-danger"><?php echo $form_errors['card_expiry']; ?></small>
                                <?php endif; ?>
                            </div>
                            <div style="width:120px;">
                                <label for="card_cvv">CVV</label>
                                <input type="text" id="card_cvv" name="card_cvv" inputmode="numeric"
                                    value="" class="<?php echo isset($form_errors['card_cvv']) ? 'is-invalid' : ''; ?>">
                                <?php if (isset($form_errors['card_cvv'])): ?>
                                    <small class="text-danger"><?php echo $form_errors['card_cvv']; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Campos de Yappy -->
                    <div id="yappy-fields" style="display: none;">
                        <div class="form-group">
                            <label for="yappy_id">Identificador Yappy</label>
                            <input type="text" id="yappy_id" name="yappy_id"
                                value="<?php echo htmlspecialchars($form_data['yappy_id'] ?? ($_POST['yappy_id'] ?? '')); ?>" 
                                class="<?php echo isset($form_errors['yappy_id']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($form_errors['yappy_id'])): ?>
                                <small class="text-danger"><?php echo $form_errors['yappy_id']; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" id="submit-btn" class="btn btn-success btn-block">Realizar Pedido</button>
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
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
    </script>

    <script>
        // Mostrar/ocultar campos de pago según selección
        (function(){
            const radioTarjeta = document.querySelector('input[name="payment_method"][value="tarjeta"]');
            const radioYappy = document.querySelector('input[name="payment_method"][value="yappy"]');
            const tarjetaFields = document.getElementById('tarjeta-fields');
            const yappyFields = document.getElementById('yappy-fields');

            function toggleFields() {
                const selected = document.querySelector('input[name="payment_method"]:checked');
                if (!selected) {
                    tarjetaFields.style.display = 'none';
                    yappyFields.style.display = 'none';
                    return;
                }
                if (selected.value === 'tarjeta') {
                    tarjetaFields.style.display = 'block';
                    yappyFields.style.display = 'none';
                } else if (selected.value === 'yappy') {
                    tarjetaFields.style.display = 'none';
                    yappyFields.style.display = 'block';
                }
            }

            document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', toggleFields));
            // Ejecutar al cargar para respetar datos en sesión
            toggleFields();
        })();
    </script>
    
    <!-- Script de validaciones -->
    <script src="<?php echo BASE_URL; ?>cliente/js/checkout-validations.js"></script>
    <script src="<?php echo BASE_URL; ?>js/chatbot.js" charset="utf-8"></script>
</body>
</html>
