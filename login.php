<?php
require_once 'config.php'; 
require_once 'includes/functions.php'; 
require_once 'includes/auth.php';

$error = '';

//  Maneja el envío del formulario de inicio de sesión.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']); 
    $password = $_POST['password'];
    
    //  Intenta iniciar sesión con las credenciales proporcionadas.
    if ($auth->login($email, $password)) {
        //  Si el inicio de sesión es exitoso, redirige según el rol del usuario.
        if ($auth->isAdmin()) {
            header('Location: ' . BASE_URL . 'admin/');
        } else {
            header('Location: ' . BASE_URL . 'cliente/');
        }
        exit; //  Termina la ejecución después de la redirección.
    } else {
        //  Si las credenciales son incorrectas, muestra un mensaje de error.
        $error = 'Credenciales incorrectas';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <!--  SweetAlert2 CDN para notificaciones mejoradas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
                
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1><?php echo SITE_NAME; ?></h1>
        </div>
        
        <form method="POST" action="<?php echo BASE_URL; ?>login.php">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>
        
        <div class="register-link">
            <a href="<?php echo BASE_URL; ?>registro.php">¿No tienes cuenta? Regístrate</a>
        </div>
    </div>

    <!-- Chatbot Westito -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/chatbot.css">
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
        
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (!empty($error)): ?>
            Swal.fire({
                title: 'Error de Autenticación',
                text: '<?php echo addslashes($error); ?>',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
            <?php endif; ?>
        });
    </script>
    
    <!-- Script del Chatbot -->
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
