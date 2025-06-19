<?php
header('Content-Type: application/json');

// 1) Carga config
require_once __DIR__ . '/../config.php';

// 2) Carga PDO
require_once __DIR__ . '/../includes/db.php';

try {
    // 3) Lee parámetro opcional ?category
    $categoryId = isset($_GET['category']) && is_numeric($_GET['category'])
        ? intval($_GET['category'])
        : null;

    if ($categoryId) {
        $sql = "
            SELECT id, nombre, descripcion, precio, stock, imagen_url, id_categoria
            FROM productos
            WHERE id_categoria = :cat
            ORDER BY nombre
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':cat', $categoryId, PDO::PARAM_INT);
    } else {
        $sql = "
            SELECT id, nombre, descripcion, precio, stock, imagen_url, id_categoria
            FROM productos
            ORDER BY nombre
        ";
        $stmt = $db->prepare($sql);
    }

    // 4) Ejecuta y recoge
    $stmt->execute();
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5) Devuelve JSON
    echo json_encode([
        'success'  => true,
        'products' => $prods
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}