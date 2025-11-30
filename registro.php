<?php
// Es importante que config.php se cargue primero para definir BASE_URL y SITE_NAME.
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php'; // sanitizeInput() se espera aquí.

$error = '';
$success = '';

//  Procesa el formulario de registro cuando se envía.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADVERTENCIA: sanitizeInput() aún no está definida si no está en functions.php.
    // Esto causará un error fatal si la función no existe.
    $email = sanitizeInput($_POST['email']); 
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    //  Valida las contraseñas.
    if ($password !== $confirmPassword) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        //  Intenta registrar al nuevo usuario.
        try {
            if ($auth->register($email, $password)) {
                $success = 'Cuenta creada exitosamente. Puedes iniciar sesión ahora.';
            } else {
                $error = 'Error al crear la cuenta.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Correo duplicado';
            } else {
                $error = 'Error al crear la cuenta.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo SITE_NAME; ?></title>
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
        
        .register-container {
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
        
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
             <!--  Utiliza SITE_NAME para el logo. -->
            <h1><?php echo SITE_NAME; ?></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" class="btn">Crear Cuenta</button>
        </form>
        
        <div class="login-link">
            <!--  Utiliza BASE_URL para construir el enlace a la página de inicio de sesión. -->
            <a href="<?php echo BASE_URL; ?>login.php">¿Ya tienes cuenta? Inicia sesión</a>
        </div>
    </div>
    
    <!-- Chatbot Westito -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/chatbot.css">
    
    <script>
        // Variable global para la URL base del proyecto
        window.WESTECH_BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    
    <!-- Script del Chatbot -->
    <script src="<?php echo BASE_URL; ?>js/chatbot.js"></script>
    
    <script>
        // Inicializar el chatbot cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof WestitoChatbot !== 'undefined') {
                window.westito = new WestitoChatbot();
            }
            
            // Mostrar alerta si hay error de correo duplicado
            <?php if ($error === 'Correo duplicado'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Correo ya registrado',
                text: 'El correo electrónico ya está registrado. Por favor, utiliza otro correo o inicia sesión.',
                confirmButtonText: 'Entendido'
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
