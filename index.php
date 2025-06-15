<?php

require_once 'config.php'; 
require_once 'includes/auth.php'; 

//  Verifica si el usuario está logueado.
if ($auth->isLoggedIn()) {
    //  Si está logueado, verifica si es administrador.
    if ($auth->isAdmin()) {
        //  Redirige al panel de administrador usando la URL base.
        header('Location: ' . BASE_URL . 'admin/');
    } else {
        //  Redirige al panel de cliente usando la URL base.
        header('Location: ' . BASE_URL . 'cliente/');
    }
} else {
    //  Si no está logueado, redirige a la página de login usando la URL base.
    header('Location: ' . BASE_URL . 'login.php');
}
exit; //  Termina la ejecución del script después de la redirección.
?>
