<?php
require_once '../config.php'; 
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAdmin();

// Obtener los pedidos de la base de datos
global $db;
$stmt = $db->prepare("
    SELECT p.*, u.correo 
    FROM pedidos p 
    LEFT JOIN usuarios u ON p.id_usuario = u.id 
    ORDER BY p.creado_en DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - <?php echo SITE_NAME; ?></title>
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
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            color: #1e293b;
        }
        
        .card-content {
            padding: 1.5rem;
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
        
        .order-details {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .total {
            font-weight: 600;
            color: #059669;
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
            <a href="<?php echo BASE_URL; ?>admin/categorias.php">Categorías</a>
            <a href="<?php echo BASE_URL; ?>admin/pedidos.php" class="active">Pedidos</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Lista de Pedidos</h2>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['nombre_cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($order['correo'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($order['celular']); ?></td>
                                    <td class="total"><?php echo formatPrice($order['total']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['creado_en'])); ?></td>
                                    <td>
                                        <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)" class="btn btn-primary btn-small">Ver Detalles</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalles del Pedido -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalles del Pedido</h3>
                <button class="close-modal" onclick="closeOrderModal()">&times;</button>
            </div>
            <div id="orderDetails">
                <!-- Los detalles del pedido se cargarán aquí -->
            </div>
        </div>
    </div>
    
    <script>
        async function viewOrderDetails(orderId) {
            const orderDetailsDiv = document.getElementById('orderDetails');
            const modalTitle = document.querySelector('#orderModal .modal-header h3');
            
            modalTitle.textContent = `Detalles del Pedido #${orderId}`;
            orderDetailsDiv.innerHTML = '<p>Cargando detalles...</p>';
            document.getElementById('orderModal').classList.add('active');

            try {
                //  Construye la URL para la petición AJAX usando BASE_URL.
                const response = await fetch(`<?php echo BASE_URL; ?>admin/ajax_get_order_details.php?orderId=${orderId}`);
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                const data = await response.json();

                if (data.success && data.order) {
                    let html = '<div class="order-summary">';
                    html += `<p><strong>ID Pedido:</strong> ${data.order.id}</p>`;
                    html += `<p><strong>Cliente:</strong> ${escapeHTML(data.order.nombre_cliente)}</p>`;
                    html += `<p><strong>Correo Usuario:</strong> ${escapeHTML(data.order.correo_usuario || 'N/A')}</p>`;
                    html += `<p><strong>Dirección:</strong> ${escapeHTML(data.order.direccion || 'N/A')}</p>`;
                    html += `<p><strong>Teléfono:</strong> ${escapeHTML(data.order.celular)}</p>`;
                    html += `<p><strong>Total:</strong> ${formatCurrency(data.order.total)}</p>`;
                    html += `<p><strong>Fecha:</strong> ${new Date(data.order.creado_en).toLocaleString('es-ES')}</p>`;
                    html += '</div>';
                    
                    html += '<h4>Artículos del Pedido:</h4>';
                    if (data.items && data.items.length > 0) {
                        html += '<ul class="order-items-list">';
                        data.items.forEach(item => {
                            html += `<li class="order-item">
                                        <span>${escapeHTML(item.nombre_producto)} (x${item.cantidad})</span>
                                        <span>${formatCurrency(item.precio_unitario * item.cantidad)}</span>
                                     </li>`;
                        });
                        html += '</ul>';
                    } else {
                        html += '<p>No se encontraron artículos para este pedido.</p>';
                    }
                    orderDetailsDiv.innerHTML = html;
                } else {
                    orderDetailsDiv.innerHTML = `<p>Error al cargar detalles: ${escapeHTML(data.message)}</p>`;
                }
            } catch (error) {
                console.error('Error al obtener detalles del pedido:', error);
                orderDetailsDiv.innerHTML = '<p>Ocurrió un error al contactar al servidor. Por favor, inténtelo de nuevo.</p>';
            }
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        //  Cierra el modal si se hace clic fuera de su contenido.
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });

        //  Función auxiliar para escapar HTML y prevenir XSS.
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        //  Función auxiliar para formatear moneda.
        function formatCurrency(amount) {
            return parseFloat(amount).toLocaleString('es-ES', { style: 'currency', currency: 'USD' }); // Ajusta la moneda según sea necesario
        }
    </script>
</body>
</html>
