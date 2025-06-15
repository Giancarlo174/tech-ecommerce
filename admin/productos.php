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
                $nombre = sanitizeInput($_POST['nombre']);
                $descripcion = sanitizeInput($_POST['descripcion']);
                $precio = floatval($_POST['precio']);
                $stock = intval($_POST['stock']);
                $categoria = intval($_POST['categoria']);
                $marca = intval($_POST['marca']);
                
                //  URL de imagen por defecto o subida a Imgur.
                $imagen_url = BASE_URL . 'placeholder.svg?height=300&width=300'; // Usar BASE_URL para placeholder
                $imagen_deletehash = null; // Inicializar deletehash como null
                
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $uploaded_result = uploadToImgurOAuth($_FILES['imagen']['tmp_name']);
                    if ($uploaded_result && isset($uploaded_result['link'])) {
                        $imagen_url = $uploaded_result['link'];
                        $imagen_deletehash = $uploaded_result['deletehash'] ?? null;
                    } else {
                        //  Guardar error si la subida a Imgur falla.
                        $_SESSION['error'] = 'Error al subir la imagen a Imgur.';
                        header("Location: " . BASE_URL . "admin/productos.php");
                        exit;
                    }
                }
                
                global $db;
                $stmt = $db->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, imagen_url, imagen_deletehash, id_categoria, id_marca) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen_url, $imagen_deletehash, $categoria, $marca])) {
                    //  Guardar mensaje de éxito en la sesión.
                    $_SESSION['message'] = 'Producto agregado exitosamente';
                } else {
                    //  Guardar mensaje de error en la sesión.
                    $_SESSION['error'] = 'Error al agregar el producto';
                }
                header("Location: " . BASE_URL . "admin/productos.php");
                exit;
                
            case 'edit':
                $id = intval($_POST['id']);
                $nombre = sanitizeInput($_POST['nombre']);
                $descripcion = sanitizeInput($_POST['descripcion']);
                $precio = floatval($_POST['precio']);
                $stock = intval($_POST['stock']);
                $categoria = intval($_POST['categoria']);
                $marca = intval($_POST['marca']);
                
                global $db;
                
                //  Obtenemos el deletehash actual de la imagen para poder eliminarla si hay una nueva
                $stmt_get = $db->prepare("SELECT imagen_deletehash FROM productos WHERE id = ?");
                $stmt_get->execute([$id]);
                $producto_actual = $stmt_get->fetch();
                $old_deletehash = $producto_actual['imagen_deletehash'] ?? null;
                
                $sql = "UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_categoria = ?, id_marca = ?";
                $params = [$nombre, $descripcion, $precio, $stock, $categoria, $marca];
                
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
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
                                error_log("Error al eliminar imagen anterior de Imgur para producto ID: $id, deletehash: $old_deletehash");
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
                $params[] = $id;
                
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
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php">Pedidos</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Agregar Producto</h2>
            </div>
            <div class="card-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <input type="number" id="precio" name="precio" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock</label>
                            <input type="number" id="stock" name="stock" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" required>
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <select id="marca" name="marca" required>
                                <option value="">Seleccionar marca</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="imagen">Imagen</label>
                            <!--  Input de archivo personalizado -->
                            <div class="custom-file-input-container">
                                <input type="file" id="imagen" name="imagen" accept="image/*" onchange="updateFileName('imagen', 'imagen_file_name_display_text', 'imagen_file_name_display_button')">
                                <div class="custom-file-input-label-wrapper">
                                    <span class="custom-file-input-button-like" id="imagen_file_name_display_button">Seleccionar archivo</span>
                                    <span class="custom-file-input-text" id="imagen_file_name_display_text">Ningún archivo seleccionado</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar Producto</button>
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
                                                    <button type="submit" class="btn btn-danger btn-small">Eliminar</button>
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
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre</label>
                        <input type="text" id="edit_nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_precio">Precio</label>
                        <input type="number" id="edit_precio" name="precio" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_stock">Stock</label>
                        <input type="number" id="edit_stock" name="stock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_categoria">Categoría</label>
                        <select id="edit_categoria" name="categoria" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_marca">Marca</label>
                        <select id="edit_marca" name="marca" required>
                            <option value="">Seleccionar marca</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_imagen">Nueva Imagen (opcional)</label>
                        <!--  Input de archivo personalizado para el modal -->
                        <div class="custom-file-input-container">
                            <input type="file" id="edit_imagen" name="imagen" accept="image/*" onchange="updateFileName('edit_imagen', 'edit_imagen_file_name_display_text', 'edit_imagen_file_name_display_button')">
                            <div class="custom-file-input-label-wrapper">
                                <span class="custom-file-input-button-like" id="edit_imagen_file_name_display_button">Seleccionar archivo</span>
                                <span class="custom-file-input-text" id="edit_imagen_file_name_display_text">Ningún archivo seleccionado</span>
                            </div>
                        </div>
                        <p style="font-size: 0.8rem; margin-top: 0.5rem;">Imagen actual: <img id="current_image_preview" src="" alt="Imagen actual" style="max-width: 100px; max-height: 100px; vertical-align: middle;"></p>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_descripcion">Descripción</label>
                        <textarea id="edit_descripcion" name="descripcion"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Actualizar Producto</button>
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
                    editModal.classList.add('active');
                });
            });
        });

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        //  Función para actualizar el nombre del archivo seleccionado en el input personalizado.
        function updateFileName(inputId, textDisplayId, buttonDisplayId) {
            const input = document.getElementById(inputId);
            const textDisplay = document.getElementById(textDisplayId);
            const container = input.closest('.custom-file-input-container');

            if (input.files && input.files.length > 0) {
                textDisplay.textContent = input.files[0].name;
                if (container) container.classList.add('file-selected');
            } else {
                textDisplay.textContent = 'Ningún archivo seleccionado';
                if (container) container.classList.remove('file-selected');
            }
        }

        //  Función para confirmar eliminación con SweetAlert2.
        function confirmDelete(event) {
            event.preventDefault(); // Prevenir el envío inmediato del formulario
            const form = event.target;

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
                    form.submit(); // Enviar el formulario si el usuario confirma
                }
            });
            return false; // Asegurar que el formulario no se envíe hasta la confirmación
        }
    </script>
</body>
</html>
