<?php
/**
 * Sistema de Alertas de Inventario
 * Proporciona funciones para gestionar alertas de stock bajo/crítico
 */

require_once 'db.php';

class AlertasInventario {
    private $db;
    private $stock_minimo = 3; // Valor por defecto. ¡Fácil de cambiar aquí!
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Permite cambiar el stock mínimo al valor deseado desde fuera de la clase.
     * @param int $minimo Nuevo valor de stock mínimo.
     */
    public function setStockMinimo(int $minimo) {
        $this->stock_minimo = $minimo;
    }
    
    /**
     * Obtiene todas las alertas activas del sistema
     * @return array Lista de alertas
     */
    public function obtenerAlertas($limite = 10) {
        $sql = "
            SELECT 
                p.id, p.nombre, p.stock, p.precio,
                c.nombre as categoria,
                (p.precio * p.stock) as valor_stock,
                CASE 
                    WHEN p.stock = 0 THEN 'CRITICO'
                    WHEN p.stock <= ? THEN 'BAJO'
                    ELSE 'NORMAL'
                END as nivel_alerta
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id
            WHERE p.stock <= ?
            ORDER BY p.stock ASC, p.nombre ASC
            LIMIT " . (int)$limite . "
        ";
        
        $stmt = $this->db->prepare($sql);
        // Se enlaza el stock_minimo dos veces
        $stmt->execute([$this->stock_minimo, $this->stock_minimo]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtiene el resumen de alertas
     * @return array Conteos por nivel
     */
    public function getResumenAlertas() {
        $sql = "
            SELECT 
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as critico,
                SUM(CASE WHEN stock > 0 AND stock <= ? THEN 1 ELSE 0 END) as bajo,
                SUM(CASE WHEN stock > ? THEN 1 ELSE 0 END) as normal,
                COUNT(*) as total
            FROM productos
        ";
        
        $stmt = $this->db->prepare($sql);
        // Se enlaza el stock_minimo dos veces
        $stmt->execute([$this->stock_minimo, $this->stock_minimo]);
        return $stmt->fetch();
    }
    
    /**
     * Registra un evento de alerta
     * @param int $id_producto
     * @param string $tipo (CRITICO, BAJO, REORDEN, etc)
     * @param string $descripcion
     */
    public function registrarEvento($id_producto, $tipo, $descripcion = '') {
        // Opcional: guardar en tabla de historial
        // $stmt = $this->db->prepare("
        //     INSERT INTO alertas_historial (id_producto, tipo, descripcion, fecha)
        //     VALUES (?, ?, ?, NOW())
        // ");
        // $stmt->execute([$id_producto, $tipo, $descripcion]);
    }
    
    /**
     * Obtiene productos sugeridos para reorden
     * @return array Productos a reabastecer
     */
    public function productosParaReorden() {
        $sql = "
            SELECT 
                p.id, p.nombre, p.stock, p.precio,
                c.nombre as categoria,
                ROUND((SELECT AVG(pd.cantidad) FROM pedido_detalles pd 
                                         WHERE pd.id_producto = p.id), 2) as promedio_venta
            FROM productos p
            LEFT JOIN categorias c ON p.id_categoria = c.id
            WHERE p.stock <= ? AND p.stock > 0
            ORDER BY p.stock ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        // Se enlaza el stock_minimo una vez
        $stmt->execute([$this->stock_minimo]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtiene estadísticas de salud del inventario
     * @return array Estadísticas
     */
    public function estadisticasInventario() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_productos,
                SUM(stock) as stock_total,
                ROUND(AVG(stock), 2) as stock_promedio,
                MIN(stock) as stock_minimo,
                MAX(stock) as stock_maximo,
                SUM(precio * stock) as valor_total_inventario,
                ROUND(SUM(precio * stock) / COUNT(*), 2) as valor_promedio
            FROM productos
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
}

// Funciones auxiliares
function obtenerAlertasInventario(int $limite = 10, int $stock_minimo = 3) {
    $alertas = new AlertasInventario();
    $alertas->setStockMinimo($stock_minimo);
    return $alertas->obtenerAlertas($limite);
}

function getResumenAlertasInventario(int $stock_minimo = 3) {
    $alertas = new AlertasInventario();
    $alertas->setStockMinimo($stock_minimo);
    return $alertas->getResumenAlertas();
}

function getProductosParaReorden(int $stock_minimo = 3) {
    $alertas = new AlertasInventario();
    $alertas->setStockMinimo($stock_minimo);
    return $alertas->productosParaReorden();
}

function getEstadisticasInventario() {
    $alertas = new AlertasInventario();
    return $alertas->estadisticasInventario();
}
?>