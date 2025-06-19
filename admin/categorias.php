<?php

require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

$auth->requireAdmin();

// Inicializar variables
$error = "";
$success = "";
$categoryName = "";
$categoryId = null;

// Procesar creación/actualización de categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        // Crear nueva categoría
        $categoryName = trim($_POST['nombre']);
        
        if (empty($categoryName)) {
            $error = "El nombre de la categoría es obligatorio";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO categorias (nombre) VALUES (?)");
                $stmt->execute([$categoryName]);
                $success = "¡Categoría creada con éxito!";
                $categoryName = ""; // Limpiar el campo para una nueva entrada
            } catch (Exception $e) {
                $error = "Error al crear la categoría: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        // Actualizar categoría existente
        $categoryId = $_POST['id'];
        $categoryName = trim($_POST['nombre']);
        
        if (empty($categoryName)) {
            $error = "El nombre de la categoría es obligatorio";
        } else {
            try {
                $stmt = $db->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
                $stmt->execute([$categoryName, $categoryId]);
                $success = "¡Categoría actualizada con éxito!";
                $categoryId = null;
                $categoryName = "";
            } catch (Exception $e) {
                $error = "Error al actualizar la categoría: " . $e->getMessage();
            }
        }
    }
}

// Eliminar categoría
if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    
    try {
        // Verificar si hay productos asociados
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM productos WHERE id_categoria = ?");
        $stmt->execute([$deleteId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = "No se puede eliminar esta categoría porque tiene productos asociados";
        } else {
            $stmt = $db->prepare("DELETE FROM categorias WHERE id = ?");
            $stmt->execute([$deleteId]);
            $success = "¡Categoría eliminada con éxito!";
        }
    } catch (Exception $e) {
        $error = "Error al eliminar la categoría: " . $e->getMessage();
    }
}

// Editar categoría - Cargar datos
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM categorias WHERE id = ?");
        $stmt->execute([$editId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            $categoryName = $category['nombre'];
            $categoryId = $category['id'];
        }
    } catch (Exception $e) {
        $error = "Error al cargar la categoría: " . $e->getMessage();
    }
}

// Obtener todas las categorías para mostrar en la tabla
$categories = getCategories();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - <?php echo SITE_NAME; ?></title>
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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

        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #475569;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
            color: #1e293b;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
        }
        
        table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
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
            <a href="<?php echo BASE_URL; ?>admin/categorias.php" class="active">Categorías</a>
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php">Pedidos</a>
        </div>
        
        <!-- Formulario para crear/editar categoría -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $categoryId ? 'Editar Categoría' : 'Nueva Categoría'; ?></h2>
            </div>
            <div class="card-content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Categoría</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($categoryName); ?>" required>
                    </div>
                    
                    <?php if ($categoryId): ?>
                        <input type="hidden" name="id" value="<?php echo $categoryId; ?>">
                        <input type="hidden" name="action" value="update">
                        <button type="submit" class="btn btn-primary">Actualizar Categoría</button>
                        <a href="<?php echo BASE_URL; ?>admin/categorias.php" class="btn btn-secondary">Cancelar</a>
                    <?php else: ?>
                        <input type="hidden" name="action" value="create">
                        <button type="submit" class="btn btn-primary">Crear Categoría</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Lista de categorías -->
        <div class="card">
            <div class="card-header">
                <h2>Categorías Existentes</h2>
            </div>
            <div class="card-content">
                <?php if (count($categories) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['nombre']); ?></td>
                                    <td class="actions">
                                        <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-secondary">Editar</a>
                                        <a href="?delete=<?php echo $category['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de eliminar esta categoría?');">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No hay categorías registradas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
