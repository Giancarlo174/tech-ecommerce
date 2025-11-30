<?php
require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAdmin();

//  Iniciar la sesión si no está iniciada para usar variables de sesión para los mensajes.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//  Inicializar mensajes desde la sesión y luego limpiarlos.
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);


// Función para sanitizar entradas de formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Array para almacenar errores de validación
                $errores = [];
                $datos_validos = [];
                
                // Validar nombre: obligatorio y sin código HTML
                if (!isset($_POST['nombre']) || empty(trim($_POST['nombre']))) {
                    $errores['nombre'] = 'El nombre es obligatorio';
                } else {
                    $nombre_limpio = preg_replace('/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i', '', $_POST['nombre']);
                    $datos_validos['nombre'] = sanitizeInput($nombre_limpio);
                }
                
                // Validar precio: obligatorio, formato correcto y positivo
                if (!isset($_POST['precio']) || empty(trim($_POST['precio']))) {
                    $errores['precio'] = 'El precio es obligatorio';
                } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $_POST['precio'])) {
                    $errores['precio'] = 'El precio debe tener un formato válido (ej: 123 o 123.45)';
                } elseif (floatval($_POST['precio']) <= 0) {
                    $errores['precio'] = 'El precio debe ser mayor a 0';
                } else {
                    $datos_validos['precio'] = floatval($_POST['precio']);
                }
                
                // Validar stock: obligatorio y entero positivo
                if (!isset($_POST['stock']) || empty(trim($_POST['stock']))) {
                    $errores['stock'] = 'El stock es obligatorio';
                } elseif (!preg_match('/^\d+$/', $_POST['stock'])) {
                    $errores['stock'] = 'El stock debe ser un número entero positivo';
                } else {
                    $datos_validos['stock'] = intval($_POST['stock']);
                }
                
                // Validar categoría
                if (!isset($_POST['categoria']) || empty($_POST['categoria'])) {
                    $errores['categoria'] = 'La categoría es obligatoria';
                } else {
                    $datos_validos['categoria'] = intval($_POST['categoria']);
                }
                
                // Validar marca
                if (!isset($_POST['marca']) || empty($_POST['marca'])) {
                    $errores['marca'] = 'La marca es obligatoria';
                } else {
                    $datos_validos['marca'] = intval($_POST['marca']);
                }
                
                // Validar descripción: obligatoria y sin código HTML
                if (!isset($_POST['descripcion']) || empty(trim($_POST['descripcion']))) {
                    $errores['descripcion'] = 'La descripción es obligatoria';
                } else {
                    $descripcion_limpia = preg_replace('/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i', '', $_POST['descripcion']);
                    $datos_validos['descripcion'] = sanitizeInput($descripcion_limpia);
                }
                
                // Validar imagen: obligatoria, tipo y tamaño correctos
                $imagen_url = BASE_URL . 'placeholder.svg?height=300&width=300'; // Valor por defecto
                $imagen_deletehash = null;
                
                if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                    $errores['imagen'] = 'Debe seleccionar una imagen';
                } else {
                    // Verificar tipo de archivo
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                    if (!in_array($_FILES['imagen']['type'], $allowed_types)) {
                        $errores['imagen'] = 'Solo se permiten archivos JPG, JPEG o PNG';
                    } 
                    // Verificar tamaño (máximo 2MB)
                    elseif ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
                        $errores['imagen'] = 'El archivo no debe superar los 2MB';
                    } 
                    // Si pasa todas las validaciones, proceder con la subida
                    else {
                        $uploaded_result = uploadToImgurOAuth($_FILES['imagen']['tmp_name']);
                        if ($uploaded_result && isset($uploaded_result['link'])) {
                            $imagen_url = $uploaded_result['link'];
                            $imagen_deletehash = $uploaded_result['deletehash'] ?? null;
                        } else {
                            $errores['imagen'] = 'Error al subir la imagen a Imgur';
                        }
                    }
                }
                
                // Si hay errores, volver a mostrar el formulario con los errores
                if (!empty($errores)) {
                    $_SESSION['form_errors'] = $errores;
                    $_SESSION['form_data'] = $_POST; // Guardar datos enviados para rellenar el formulario
                    header("Location: " . BASE_URL . "admin/productos.php");
                    exit;
                }
                
                // Si no hay errores, proceder con la inserción
                global $db;
                $stmt = $db->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, imagen_url, imagen_deletehash, id_categoria, id_marca) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([
                    $datos_validos['nombre'], 
                    $datos_validos['descripcion'], 
                    $datos_validos['precio'], 
                    $datos_validos['stock'], 
                    $imagen_url, 
                    $imagen_deletehash, 
                    $datos_validos['categoria'], 
                    $datos_validos['marca']
                ])) {
                    $_SESSION['message'] = 'Producto agregado exitosamente';
                } else {
                    $_SESSION['error'] = 'Error al agregar el producto';
                }
                header("Location: " . BASE_URL . "admin/productos.php");
                exit;
                
            case 'edit':
                // Array para almacenar errores de validación
                $errores = [];
                $datos_validos = [];
                
                // Validar el ID
                if (!isset($_POST['id']) || empty($_POST['id']) || !is_numeric($_POST['id'])) {
                    $errores['id'] = 'ID del producto inválido';
                } else {
                    $datos_validos['id'] = intval($_POST['id']);
                }
                
                // Validar nombre: obligatorio y sin código HTML
                if (!isset($_POST['nombre']) || empty(trim($_POST['nombre']))) {
                    $errores['nombre'] = 'El nombre es obligatorio';
                } else {
                    $nombre_limpio = preg_replace('/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i', '', $_POST['nombre']);
                    $datos_validos['nombre'] = sanitizeInput($nombre_limpio);
                }
                
                // Validar precio: obligatorio, formato correcto y positivo
                if (!isset($_POST['precio']) || empty(trim($_POST['precio']))) {
                    $errores['precio'] = 'El precio es obligatorio';
                } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $_POST['precio'])) {
                    $errores['precio'] = 'El precio debe tener un formato válido (ej: 123 o 123.45)';
                } elseif (floatval($_POST['precio']) <= 0) {
                    $errores['precio'] = 'El precio debe ser mayor a 0';
                } else {
                    $datos_validos['precio'] = floatval($_POST['precio']);
                }
                
                // Validar stock: obligatorio y entero positivo
                if (!isset($_POST['stock']) || empty(trim($_POST['stock']))) {
                    $errores['stock'] = 'El stock es obligatorio';
                } elseif (!preg_match('/^\d+$/', $_POST['stock'])) {
                    $errores['stock'] = 'El stock debe ser un número entero positivo';
                } else {
                    $datos_validos['stock'] = intval($_POST['stock']);
                }
                
                // Validar categoría
                if (!isset($_POST['categoria']) || empty($_POST['categoria'])) {
                    $errores['categoria'] = 'La categoría es obligatoria';
                } else {
                    $datos_validos['categoria'] = intval($_POST['categoria']);
                }
                
                // Validar marca
                if (!isset($_POST['marca']) || empty($_POST['marca'])) {
                    $errores['marca'] = 'La marca es obligatoria';
                } else {
                    $datos_validos['marca'] = intval($_POST['marca']);
                }
                
                // Validar descripción: obligatoria y sin código HTML
                if (!isset($_POST['descripcion']) || empty(trim($_POST['descripcion']))) {
                    $errores['descripcion'] = 'La descripción es obligatoria';
                } else {
                    $descripcion_limpia = preg_replace('/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i', '', $_POST['descripcion']);
                    $datos_validos['descripcion'] = sanitizeInput($descripcion_limpia);
                }
                
                // Si hay errores, volver a mostrar el formulario con los errores
                if (!empty($errores)) {
                    $_SESSION['form_errors'] = $errores;
                    $_SESSION['form_data'] = $_POST; // Guardar datos enviados para rellenar el formulario
                    header("Location: " . BASE_URL . "admin/productos.php?action=edit&id=" . $_POST['id']);
                    exit;
                }
                
                global $db;
                
                // Obtenemos el deletehash actual de la imagen para poder eliminarla si hay una nueva
                $stmt_get = $db->prepare("SELECT imagen_deletehash FROM productos WHERE id = ?");
                $stmt_get->execute([$datos_validos['id']]);
                $producto_actual = $stmt_get->fetch();
                $old_deletehash = $producto_actual['imagen_deletehash'] ?? null;
                
                $sql = "UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_categoria = ?, id_marca = ?";
                $params = [$datos_validos['nombre'], $datos_validos['descripcion'], $datos_validos['precio'], $datos_validos['stock'], $datos_validos['categoria'], $datos_validos['marca']];
                
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    // Verificar tipo de archivo
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                    if (!in_array($_FILES['imagen']['type'], $allowed_types)) {
                        $_SESSION['error'] = 'Solo se permiten archivos JPG, JPEG o PNG';
                        header("Location: " . BASE_URL . "admin/productos.php?action=edit&id=" . $datos_validos['id']);
                        exit;
                    } 
                    // Verificar tamaño (máximo 2MB)
                    elseif ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
                        $_SESSION['error'] = 'El archivo no debe superar los 2MB';
                        header("Location: " . BASE_URL . "admin/productos.php?action=edit&id=" . $datos_validos['id']);
                        exit;
                    }
                    // Continuar con el proceso de subida
                    //  Subir la nueva imagen a Imgur
                    $uploaded_result = uploadToImgurOAuth($_FILES['imagen']['tmp_name']);
                    if ($uploaded_result && isset($uploaded_result['link'])) {
                        $imagen_url = $uploaded_result['link'];
                        $imagen_deletehash = $uploaded_result['deletehash'] ?? null;
                        
                        //  Eliminar la imagen anterior de Imgur si existe un deletehash
                        if (!empty($old_deletehash)) {
                            $delete_result = deleteFromImgur($old_deletehash);
                            if (!$delete_result) {
                                //  Si falla la eliminación, lo registramos pero continuamos con la actualización
                                error_log("Error al eliminar imagen anterior de Imgur para producto ID: " . $datos_validos['id'] . ", deletehash: $old_deletehash");
                            }
                        }
                        
                        $sql .= ", imagen_url = ?, imagen_deletehash = ?";
                        $params[] = $imagen_url;
                        $params[] = $imagen_deletehash;
                    } else {
                         //  Guardar error si la subida a Imgur falla.
                        $_SESSION['error'] = 'Error al subir la nueva imagen a Imgur.';
                        header("Location: " . BASE_URL . "admin/productos.php");
                        exit;
                    }
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $datos_validos['id'];
                
                $stmt = $db->prepare($sql);
                if ($stmt->execute($params)) {
                    //  Guardar mensaje de éxito en la sesión.
                    $_SESSION['message'] = 'Producto actualizado exitosamente';
                } else {
                    //  Guardar mensaje de error en la sesión.
                    $_SESSION['error'] = 'Error al actualizar el producto';
                }
                header("Location: " . BASE_URL . "admin/productos.php");
                exit;
                
            case 'delete':
                $id = intval($_POST['id']);
                global $db;
                
                //  Primero obtenemos el deletehash de la imagen para poder eliminarla de Imgur
                $stmt_get = $db->prepare("SELECT imagen_deletehash FROM productos WHERE id = ?");
                $stmt_get->execute([$id]);
                $producto = $stmt_get->fetch();
                
                //  Si existe un deletehash, intentamos eliminar la imagen de Imgur
                if ($producto && !empty($producto['imagen_deletehash'])) {
                    $delete_result = deleteFromImgur($producto['imagen_deletehash']);
                    if (!$delete_result) {
                        //  Si falla la eliminación de la imagen, lo registramos pero continuamos con la eliminación del producto
                        error_log("Error al eliminar imagen de Imgur para producto ID: $id, deletehash: " . $producto['imagen_deletehash']);
                    }
                }
                
                //  Eliminamos el producto de la base de datos
                $stmt = $db->prepare("DELETE FROM productos WHERE id = ?");
                if ($stmt->execute([$id])) {
                    //  Guardar mensaje de éxito en la sesión.
                    $_SESSION['message'] = 'Producto eliminado exitosamente';
                } else {
                    //  Guardar mensaje de error en la sesión.
                    $_SESSION['error'] = 'Error al eliminar el producto';
                }
                header("Location: " . BASE_URL . "admin/productos.php");
                exit;
        }
    }
}

// Obtener la lista de productos para mostrar en la página
global $db;
$stmt = $db->prepare("SELECT p.*, c.nombre as categoria, m.nombre as marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id ORDER BY p.creado_en DESC");
$stmt->execute();
$products = $stmt->fetchAll();

$categories = getCategories();
$brands = getBrands();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?php echo SITE_NAME; ?></title>
    <!--  Incluir SweetAlert2 CDN -->
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
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="file"],
        select,
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
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th,
        td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            font-size: 1.25rem;
            color: #1e293b;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        /*  Estilos para el input de archivo personalizado */
        .custom-file-input-container {
            position: relative;
            overflow: hidden;
            display: block; /* Cambiado a block para que ocupe el ancho completo como otros inputs */
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: white;
            cursor: pointer;
            padding: 0; /* Eliminado padding aquí, se manejará en el label interno */
        }

        .custom-file-input-container input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 10; /* Asegurar que esté por encima del label */
        }

        .custom-file-input-label-wrapper {
            display: flex; /* Usar flex para alinear el botón y el texto */
            align-items: center;
            padding: 0.75rem; /* Padding que estaba en el contenedor */
            height: 100%;
        }
        
        .custom-file-input-button-like { /* Simula un botón */
            background-color: #e9ecef; /* Un color gris claro, similar a los botones de browser */
            color: #495057;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            border: 1px solid #ced4da;
            margin-right: 0.75rem;
            font-size: 1rem;
            white-space: nowrap;
            line-height: 1.5; /* Alineación vertical del texto */
        }

        .custom-file-input-text {
            color: #6c757d; /* Color de texto para "Ningún archivo seleccionado" o nombre de archivo */
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-grow: 1; /* Para que ocupe el espacio restante */
            line-height: 1.5; /* Alineación vertical del texto */
        }

        .custom-file-input-container.file-selected .custom-file-input-text {
            color: #212529; /* Texto más oscuro cuando hay un archivo */
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

        /* Estilos para el spinner de carga */
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
        
        /* Estilos para el preview de imagen */
        .image-preview-container {
            margin-top: 0.5rem;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            object-fit: cover;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .image-preview {
                max-width: 150px;
                max-height: 150px;
            }
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
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
            <a href="<?php echo BASE_URL; ?>admin/">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>admin/productos.php" class="active">Productos</a>
            <a href="<?php echo BASE_URL; ?>admin/categorias.php">Categorías</a>
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php">Pedidos</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Agregar Producto</h2>
            </div>
            <div class="card-content">
                <?php
                // Mostrar errores y datos previos si existen en la sesión
                $form_errors = $_SESSION['form_errors'] ?? [];
                $form_data = $_SESSION['form_data'] ?? [];
                
                // Limpiar errores y datos de la sesión después de usarlos
                unset($_SESSION['form_errors'], $_SESSION['form_data']);
                ?>
                <form method="POST" enctype="multipart/form-data" id="product-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" required 
                                value="<?php echo isset($form_data['nombre']) ? htmlspecialchars($form_data['nombre']) : ''; ?>"
                                class="<?php echo isset($form_errors['nombre']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($form_errors['nombre'])): ?>
                                <small class="text-danger"><?php echo $form_errors['nombre']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <input type="text" id="precio" name="precio" required 
                                value="<?php echo isset($form_data['precio']) ? htmlspecialchars($form_data['precio']) : ''; ?>"
                                class="<?php echo isset($form_errors['precio']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($form_errors['precio'])): ?>
                                <small class="text-danger"><?php echo $form_errors['precio']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock</label>
                            <input type="text" id="stock" name="stock" required 
                                value="<?php echo isset($form_data['stock']) ? htmlspecialchars($form_data['stock']) : ''; ?>"
                                class="<?php echo isset($form_errors['stock']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($form_errors['stock'])): ?>
                                <small class="text-danger"><?php echo $form_errors['stock']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" required
                                class="<?php echo isset($form_errors['categoria']) ? 'is-invalid' : ''; ?>">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo (isset($form_data['categoria']) && $form_data['categoria'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['categoria'])): ?>
                                <small class="text-danger"><?php echo $form_errors['categoria']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <select id="marca" name="marca" required
                                class="<?php echo isset($form_errors['marca']) ? 'is-invalid' : ''; ?>">
                                <option value="">Seleccionar marca</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>"
                                        <?php echo (isset($form_data['marca']) && $form_data['marca'] == $brand['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['marca'])): ?>
                                <small class="text-danger"><?php echo $form_errors['marca']; ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="imagen">Imagen</label>
                            <!--  Input de archivo personalizado -->
                            <div class="custom-file-input-container <?php echo isset($form_errors['imagen']) ? 'is-invalid' : ''; ?>">
                                <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/jpg,image/png" 
                                    onchange="updateFileName('imagen', 'imagen_file_name_display_text', 'imagen_file_name_display_button')">
                                <div class="custom-file-input-label-wrapper">
                                    <span class="custom-file-input-button-like" id="imagen_file_name_display_button">Seleccionar archivo</span>
                                    <span class="custom-file-input-text" id="imagen_file_name_display_text">Ningún archivo seleccionado</span>
                                </div>
                            </div>
                            <?php if (isset($form_errors['imagen'])): ?>
                                <small class="text-danger"><?php echo $form_errors['imagen']; ?></small>
                            <?php endif; ?>
                            <div id="imagen_preview_container" class="image-preview-container" style="display: none;">
                                <img id="imagen_preview" class="image-preview" alt="Preview de imagen">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" 
                                class="<?php echo isset($form_errors['descripcion']) ? 'is-invalid' : ''; ?>"><?php echo isset($form_data['descripcion']) ? htmlspecialchars($form_data['descripcion']) : ''; ?></textarea>
                            <?php if (isset($form_errors['descripcion'])): ?>
                                <small class="text-danger"><?php echo $form_errors['descripcion']; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="submit-btn">Agregar Producto</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Lista de Productos</h2>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Marca</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No hay productos para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars(filter_var($product['imagen_url'], FILTER_VALIDATE_URL) ? $product['imagen_url'] : BASE_URL . 'placeholder.svg?height=60&width=60'); ?>" alt="<?php echo htmlspecialchars($product['nombre']); ?>" class="product-image">
                                        </td>
                                        <td><?php echo htmlspecialchars($product['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($product['categoria'] ?? 'Sin categoría'); ?></td>
                                        <td><?php echo htmlspecialchars($product['marca'] ?? 'Sin marca'); ?></td>
                                        <td><?php echo formatPrice($product['precio']); ?></td>
                                        <td><?php echo $product['stock']; ?></td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn btn-secondary btn-small edit-btn" 
                                                        data-id="<?php echo $product['id']; ?>" 
                                                        data-nombre="<?php echo htmlspecialchars($product['nombre']); ?>" 
                                                        data-descripcion="<?php echo htmlspecialchars($product['descripcion']); ?>" 
                                                        data-precio="<?php echo $product['precio']; ?>" 
                                                        data-stock="<?php echo $product['stock']; ?>" 
                                                        data-categoria="<?php echo $product['id_categoria']; ?>" 
                                                        data-marca="<?php echo $product['id_marca']; ?>"
                                                        data-imagen_url="<?php echo htmlspecialchars($product['imagen_url']); ?>">Editar</button>
                                                <form method="POST" style="display: inline-block;" onsubmit="return confirmDelete(event);">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small delete-btn" id="delete-btn-<?php echo $product['id']; ?>">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Producto -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Producto</h3>
                <button class="close-modal" onclick="closeModal('editProductModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editProductForm">
                <?php
                // Mostrar errores y datos previos si existen en la sesión
                $form_errors = $_SESSION['form_errors'] ?? [];
                $form_data = $_SESSION['form_data'] ?? [];
                
                // Limpiar errores y datos de la sesión después de usarlos
                unset($_SESSION['form_errors'], $_SESSION['form_data']);
                ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre</label>
                        <input type="text" id="edit_nombre" name="nombre" required 
                            class="<?php echo isset($form_errors['nombre']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($form_errors['nombre'])): ?>
                            <small class="text-danger"><?php echo $form_errors['nombre']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_precio">Precio</label>
                        <input type="text" id="edit_precio" name="precio" required
                            class="<?php echo isset($form_errors['precio']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($form_errors['precio'])): ?>
                            <small class="text-danger"><?php echo $form_errors['precio']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_stock">Stock</label>
                        <input type="text" id="edit_stock" name="stock" required
                            class="<?php echo isset($form_errors['stock']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($form_errors['stock'])): ?>
                            <small class="text-danger"><?php echo $form_errors['stock']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_categoria">Categoría</label>
                        <select id="edit_categoria" name="categoria" required
                            class="<?php echo isset($form_errors['categoria']) ? 'is-invalid' : ''; ?>">
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($form_errors['categoria'])): ?>
                            <small class="text-danger"><?php echo $form_errors['categoria']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_marca">Marca</label>
                        <select id="edit_marca" name="marca" required
                            class="<?php echo isset($form_errors['marca']) ? 'is-invalid' : ''; ?>">
                            <option value="">Seleccionar marca</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($form_errors['marca'])): ?>
                            <small class="text-danger"><?php echo $form_errors['marca']; ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_imagen">Nueva Imagen (opcional)</label>
                        <!--  Input de archivo personalizado para el modal -->
                        <div class="custom-file-input-container <?php echo isset($form_errors['imagen']) ? 'is-invalid' : ''; ?>">
                            <input type="file" id="edit_imagen" name="imagen" accept="image/jpeg,image/jpg,image/png" onchange="updateFileName('edit_imagen', 'edit_imagen_file_name_display_text', 'edit_imagen_file_name_display_button')">
                            <div class="custom-file-input-label-wrapper">
                                <span class="custom-file-input-button-like" id="edit_imagen_file_name_display_button">Seleccionar archivo</span>
                                <span class="custom-file-input-text" id="edit_imagen_file_name_display_text">Ningún archivo seleccionado</span>
                            </div>
                        </div>
                        <?php if (isset($form_errors['imagen'])): ?>
                            <small class="text-danger"><?php echo $form_errors['imagen']; ?></small>
                        <?php endif; ?>
                        <div id="edit_imagen_preview_container" class="image-preview-container" style="display: none;">
                            <img id="edit_imagen_preview" class="image-preview" alt="Preview de nueva imagen">
                        </div>
                        <p style="font-size: 0.8rem; margin-top: 0.5rem;">Imagen actual: <img id="current_image_preview" src="" alt="Imagen actual" style="max-width: 100px; max-height: 100px; vertical-align: middle;"></p>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_descripcion">Descripción</label>
                        <textarea id="edit_descripcion" name="descripcion"
                            class="<?php echo isset($form_errors['descripcion']) ? 'is-invalid' : ''; ?>"></textarea>
                        <?php if (isset($form_errors['descripcion'])): ?>
                            <small class="text-danger"><?php echo $form_errors['descripcion']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" id="update-btn">Actualizar Producto</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        //  Script para manejar mensajes con SweetAlert2 y la lógica del modal de edición.
        document.addEventListener('DOMContentLoaded', function() {
            const message = '<?php echo $message; ?>';
            const error = '<?php echo $error; ?>';

            if (message) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: message,
                    confirmButtonColor: '#3b82f6'
                });
            }

            if (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error,
                    confirmButtonColor: '#3b82f6'
                });
            }

            // Lógica del Modal de Edición
            const editButtons = document.querySelectorAll('.edit-btn');
            const editModal = document.getElementById('editProductModal');
            const editForm = document.getElementById('editProductForm');
            const currentImagePreview = document.getElementById('current_image_preview');

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_nombre').value = this.dataset.nombre;
                    document.getElementById('edit_descripcion').value = this.dataset.descripcion;
                    document.getElementById('edit_precio').value = this.dataset.precio;
                    document.getElementById('edit_stock').value = this.dataset.stock;
                    document.getElementById('edit_categoria').value = this.dataset.categoria;
                    document.getElementById('edit_marca').value = this.dataset.marca;
                    currentImagePreview.src = this.dataset.imagen_url ? this.dataset.imagen_url : '<?php echo BASE_URL . "placeholder.svg?height=60&width=60"; ?>';
                    
                    // Resetear el input de archivo y ocultar preview
                    const editImagenInput = document.getElementById('edit_imagen');
                    const editPreviewContainer = document.getElementById('edit_imagen_preview_container');
                    const editTextDisplay = document.getElementById('edit_imagen_file_name_display_text');
                    const editContainer = editImagenInput.closest('.custom-file-input-container');
                    
                    editImagenInput.value = '';
                    if (editTextDisplay) editTextDisplay.textContent = 'Ningún archivo seleccionado';
                    if (editContainer) editContainer.classList.remove('file-selected');
                    if (editPreviewContainer) editPreviewContainer.style.display = 'none';
                    
                    editModal.classList.add('active');
                });
            });
        });

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            
            // Resetear inputs de archivo cuando se cierra el modal de edición
            if (modalId === 'editProductModal') {
                const editImagenInput = document.getElementById('edit_imagen');
                const editPreviewContainer = document.getElementById('edit_imagen_preview_container');
                const editTextDisplay = document.getElementById('edit_imagen_file_name_display_text');
                const editContainer = editImagenInput.closest('.custom-file-input-container');
                
                editImagenInput.value = '';
                if (editTextDisplay) editTextDisplay.textContent = 'Ningún archivo seleccionado';
                if (editContainer) editContainer.classList.remove('file-selected');
                if (editPreviewContainer) editPreviewContainer.style.display = 'none';
            }
        }

        //  Función para actualizar el nombre del archivo seleccionado en el input personalizado.
        function updateFileName(inputId, textDisplayId, buttonDisplayId) {
            const input = document.getElementById(inputId);
            const textDisplay = document.getElementById(textDisplayId);
            const container = input.closest('.custom-file-input-container');

            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                textDisplay.textContent = file.name;
                if (container) container.classList.add('file-selected');

                // Mostrar preview de imagen
                const previewContainerId = inputId.replace('imagen', 'imagen_preview_container');
                const previewId = inputId.replace('imagen', 'imagen_preview');
                const previewContainer = document.getElementById(previewContainerId);
                const preview = document.getElementById(previewId);

                if (previewContainer && preview && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                textDisplay.textContent = 'Ningún archivo seleccionado';
                if (container) container.classList.remove('file-selected');

                // Ocultar preview
                const previewContainerId = inputId.replace('imagen', 'imagen_preview_container');
                const previewContainer = document.getElementById(previewContainerId);
                if (previewContainer) {
                    previewContainer.style.display = 'none';
                }
            }
        }

        //  Función para confirmar eliminación con SweetAlert2.
        function confirmDelete(event) {
            event.preventDefault(); // Prevenir el envío inmediato del formulario
            const form = event.target;
            const deleteButton = form.querySelector('button[type="submit"]');

            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, ¡elimínalo!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Guardar el texto original del botón
                    const originalText = deleteButton.textContent;
                    
                    // Agregar spinner y cambiar texto
                    deleteButton.innerHTML = '<span class="spinner"></span> Eliminando...';
                    deleteButton.classList.add('btn-loading');
                    
                    // Por si el envío falla por alguna razón, restaurar el botón después de 30 segundos
                    setTimeout(() => {
                        if (document.body.contains(deleteButton)) {
                            deleteButton.innerHTML = originalText;
                            deleteButton.classList.remove('btn-loading');
                        }
                    }, 30000);
                    
                    form.submit(); // Enviar el formulario si el usuario confirma
                }
            });
            return false; // Asegurar que el formulario no se envíe hasta la confirmación
        }
    </script>
</div>
    <!-- Scripts para validaciones de formulario -->
    <script src="<?php echo BASE_URL; ?>admin/js/validaciones.js"></script>
</body>
</html>
