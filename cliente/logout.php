<?php

// config.php es necesario para BASE_URL en la redirección.
require_once '../config.php';
require_once '../includes/auth.php';

$auth->logout();
//  Redirige a la página de login usando BASE_URL.
header('Location: ' . BASE_URL . 'login.php');
exit;
?>
