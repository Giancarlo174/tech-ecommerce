<?php
//  Habilita la visualización de errores para depuración.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireLogin();

// Obtener el filtro de categoría desde la URL
// Si la categoría es una cadena vacía o no está seteada, asignamos null
// Si es un número válido, lo convertimos a entero
$categoryFilter = (isset($_GET['categoria']) && $_GET['categoria'] !== '') ? intval($_GET['categoria']) : null;

// Obtenemos los productos filtrados por categoría, si se especifica
$products = getProducts($categoryFilter);
$categories = getCategories();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - <?php echo SITE_NAME; ?></title>
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
        
        .btn.in-cart {
            background-color: #10b981;
            color: white;
            opacity: 0.8;
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
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #374151;
        }
        
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .product-description {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #059669;
        }
        
        .product-stock {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .cart-info {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .cart-count {
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state h3 {
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
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="<?php echo BASE_URL; ?>cliente/" class="active">Catálogo</a>
            <a href="<?php echo BASE_URL; ?>cliente/carrito.php">Carrito</a>
            <a href="<?php echo BASE_URL; ?>cliente/historial.php">Historial</a>
        </div>
        
        <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
            <div class="cart-info">
                <span>Tienes <span class="cart-count"><?php echo array_sum($_SESSION['cart']); ?></span> productos en tu carrito</span>
                <a href="<?php echo BASE_URL; ?>cliente/carrito.php" class="btn btn-success">Ver Carrito</a>
            </div>
        <?php endif; ?>
        
        <div class="filters">
             <!--  El action del formulario de filtros es vacío para procesar en la misma página. -->
            <form method="GET" action="" class="filter-group">
                <label for="categoria">Filtrar por categoría:</label>
                <select name="categoria" id="categoria" onchange="this.form.submit()">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($categoryFilter): ?>
                    <!--  Enlace para quitar filtro, apunta a la URL base del catálogo. -->
                    <a href="<?php echo BASE_URL; ?>cliente/" class="btn btn-secondary btn-small">Quitar Filtro</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <h3>No hay productos disponibles</h3>
                <p>Intenta con otra categoría o revisa más tarde.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo htmlspecialchars($product['imagen_url']); ?>" alt="<?php echo htmlspecialchars($product['nombre']); ?>" class="product-image">
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['nombre']); ?></h3>
                            <p class="product-description"><?php echo htmlspecialchars(substr($product['descripcion'], 0, 100)) . (strlen($product['descripcion']) > 100 ? '...' : ''); ?></p>
                            <div class="product-meta">
                                <span class="product-price"><?php echo formatPrice($product['precio']); ?></span>
                                <span class="product-stock">Stock: <?php echo $product['stock']; ?></span>
                            </div>
                            <div class="product-actions">
                                <!--  Botón para agregar al carrito vía AJAX -->
                                <form class="add-to-cart-form">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="button" class="btn btn-primary add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">Agregar al Carrito</button>
                                </form>
                                <!-- Podría haber un botón de "Ver Detalles" que lleve a una página de producto individual si existiera -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!--  JavaScript para manejo de AJAX en la adición de productos al carrito -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        //  Array para mantener un registro de los productos en el carrito
        const productsInCart = [];
        
        //  Selecciona todos los botones de "Agregar al Carrito"
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        
        //  Marcar los productos que ya están en el carrito
        checkProductsInCart();
        
        //  Para cada botón, añade un event listener
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const form = this.closest('form');
                
                //  Verificar si el producto ya está en el carrito
                if (isProductInCart(productId)) {
                    showNotification('Este producto ya está en tu carrito', 'info');
                    return;
                }
                
                //  Deshabilitar el botón mientras se procesa
                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Agregando...';
                
                //  Crear FormData con los datos del formulario
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('product_id', productId);
                formData.append('quantity', 1); // Por defecto agregamos 1 unidad
                
                //  Realizar la petición AJAX
                fetch('<?php echo BASE_URL; ?>cliente/ajax_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    //  Restaurar el botón
                    this.disabled = false;
                    
                    //  Mostrar notificación de éxito o error
                    if (data.success) {
                        //  Marcar el producto como agregado al carrito
                        markProductAsAdded(this, productId);
                        
                        showNotification('Producto agregado al carrito', 'success');
                        
                        //  Actualizar contador del carrito en el header
                        updateCartCounter(data.cartCount);
                        
                        //  Actualizar o mostrar la información del carrito
                        updateCartInfo(data.cartCount);
                    } else {
                        this.textContent = originalText;
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.disabled = false;
                    this.textContent = originalText;
                    showNotification('Error al agregar el producto', 'error');
                });
            });
        });
        
        //  Función para actualizar el contador del carrito en el header
        function updateCartCounter(count) {
            const cartCounter = document.querySelector('.header-actions .btn-primary');
            if (cartCounter) {
                cartCounter.textContent = `Carrito (${count})`;
            }
        }
        
        //  Función para actualizar la información del carrito en la página
        function updateCartInfo(count) {
            let cartInfo = document.querySelector('.cart-info');
            
            // Si no existe el elemento de información del carrito y hay productos, lo creamos
            if (!cartInfo && count > 0) {
                cartInfo = document.createElement('div');
                cartInfo.className = 'cart-info';
                
                // Crear el contenido
                cartInfo.innerHTML = `
                    <span>Tienes <span class="cart-count">${count}</span> productos en tu carrito</span>
                    <a href="<?php echo BASE_URL; ?>cliente/carrito.php" class="btn btn-success">Ver Carrito</a>
                `;
                
                // Insertar antes de los filtros
                const container = document.querySelector('.container');
                const filters = document.querySelector('.filters');
                if (container && filters) {
                    container.insertBefore(cartInfo, filters);
                }
            } 
            // Si ya existe, actualizamos el contador
            else if (cartInfo && count > 0) {
                const cartCount = cartInfo.querySelector('.cart-count');
                if (cartCount) {
                    cartCount.textContent = count;
                }
            } 
            // Si el contador llega a 0, quitamos el elemento
            else if (cartInfo && count === 0) {
                cartInfo.remove();
            }
        }
        
        //  Función para mostrar notificaciones
        function showNotification(message, type) {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            // Estilos para la notificación
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.padding = '10px 20px';
            notification.style.borderRadius = '4px';
            notification.style.fontWeight = '500';
            notification.style.zIndex = '1000';
            notification.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            
            // Aplicar estilos según el tipo
            if (type === 'success') {
                notification.style.backgroundColor = '#10b981';
                notification.style.color = 'white';
            } else if (type === 'error') {
                notification.style.backgroundColor = '#ef4444';
                notification.style.color = 'white';
            }
            
            // Añadir al DOM
            document.body.appendChild(notification);
            
            // Eliminar después de un tiempo
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s ease';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }
        
        //  Función para verificar si un producto está en el carrito
        function isProductInCart(productId) {
            return productsInCart.includes(parseInt(productId));
        }
        
        //  Función para marcar un producto como agregado al carrito
        function markProductAsAdded(button, productId) {
            //  Guardar el ID del producto en el array
            productsInCart.push(parseInt(productId));
            
            //  Cambiar el aspecto del botón
            button.textContent = 'En el carrito';
            button.disabled = true;
            button.classList.add('in-cart');
            button.style.backgroundColor = '#10b981'; // Verde
            button.style.cursor = 'default';
        }
        
        //  Función para verificar qué productos ya están en el carrito al cargar la página
        function checkProductsInCart() {
            //  Hacer una petición AJAX para obtener los productos en el carrito
            fetch('<?php echo BASE_URL; ?>cliente/ajax_cart.php?check_cart=1')
            .then(response => response.json())
            .then(data => {
                if (data.cart && Object.keys(data.cart).length > 0) {
                    //  Para cada producto en el carrito, marcar el botón correspondiente
                    Object.keys(data.cart).forEach(productId => {
                        productsInCart.push(parseInt(productId));
                        const button = document.querySelector(`.add-to-cart-btn[data-product-id="${productId}"]`);
                        if (button) {
                            markProductAsAdded(button, productId);
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error al verificar el carrito:', error);
            });
        }
    });
    </script>
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <script src="<?php echo BASE_URL; ?>js/chatbot.js"></script>
    
    <script>
        // Inicializar el chatbot cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof WestitoChatbot !== 'undefined') {
                window.westito = new WestitoChatbot();
            }
        });
    </script>
</body>
</html>
