<?php
header('Content-Type: application/json');

// 1) Carga config desde la raíz del proyecto
require_once __DIR__ . '/../config.php';

// 2) Carga la conexión PDO desde includes/db.php
require_once __DIR__ . '/../includes/db.php';

try {
    // 3) Ejecuta la consulta sobre la instancia $db que trae db.php
    $stmt = $db->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
    $stmt->execute();
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4) Devuelve JSON
    echo json_encode([
        'success'    => true,
        'categories' => $cats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}