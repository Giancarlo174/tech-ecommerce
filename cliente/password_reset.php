<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

// Inicio de sesión ya iniciado en config.php

// CSRF helpers
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Create password_resets table if not exists
try {
    $db->prepare("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        correo VARCHAR(255) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX (correo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")->execute();
} catch (Exception $e) {
    // silently ignore; table creation is best-effort
}

// Send email helper using Resend API
function sendResetEmail($to, $token) {
    $resetUrl = BASE_URL . 'cliente/password_reset.php?token=' . urlencode($token) . '&email=' . urlencode($to);
    $subject = SITE_NAME . " - Recuperación de contraseña";
    $message = "Se ha solicitado restablecer la contraseña para esta cuenta. Si no has solicitado esto, ignora este correo.\n\n";
    $message .= "Para restablecer tu contraseña haz click en el siguiente enlace (válido 1 hora):\n\n" . $resetUrl . "\n\n";
    $message .= "Si el enlace no funciona copia y pega la URL en tu navegador.";

    // Prefer explicit RESEND_FROM if set, otherwise fallback to MAIL_FROM
    $fromEmail = defined('RESEND_FROM') ? RESEND_FROM : (defined('MAIL_FROM') ? MAIL_FROM : ('no-reply@' . parse_url(BASE_URL, PHP_URL_HOST)));

    // Prepare Resend API request
    $apiKey = RESEND_API_KEY;
    if (empty($apiKey)) {
        error_log("Resend API key not configured");
        return false;
    }

    // Build payload. Use the $to argument as the recipient so user's input is respected.
    $data = [
        'from' => SITE_NAME . ' <' . $fromEmail . '>',
        'to' => [$to],
        'subject' => $subject,
        'text' => $message
    ];

    // If an admin/test address is configured in RESEND_TO_EMAIL, add it as BCC
    $adminCopy = $_ENV['RESEND_TO_EMAIL'] ?? ($_ENV['RESEND_TO'] ?? '');
    if (!empty($adminCopy)) {
        // Allow multiple addresses separated by commas
        $bcc = array_map('trim', explode(',', $adminCopy));
        if (!empty($bcc)) {
            $data['bcc'] = $bcc;
        }
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Resend typically returns 200 or 202 for accepted messages
    $success = in_array($httpCode, [200, 202], true);

    // Log detailed info for debugging
    $logMsg = sprintf("Resend email result for %s: HTTP %s - %s", $to, $httpCode, $response === false ? 'no-response' : $response);
    error_log($logMsg);

    return $success;
}

// Normalize POST action
$action = $_POST['action'] ?? $_GET['action'] ?? 'request';

$errors = [];
$success = '';

// Handle requesting a reset token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'request')) {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Token CSRF inválido.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Introduce un correo válido.';
    }

    if (empty($errors)) {
        // Find user by correo in usuarios table
        $stmt = $db->prepare('SELECT id, correo FROM usuarios WHERE correo = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always respond success to avoid leaking whether email exists
        $success = 'Si la dirección existe en nuestro sistema, recibirás un correo con instrucciones para restablecer tu contraseña.';

        if ($user) {
            // Generate token and store hashed
            $token = bin2hex(random_bytes(32));
            $tokenHash = password_hash($token, PASSWORD_DEFAULT);
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $createdAt = (new DateTime())->format('Y-m-d H:i:s');

            // Delete previous tokens for this email
            $d = $db->prepare('DELETE FROM password_resets WHERE correo = ?');
            $d->execute([$email]);

            $ins = $db->prepare('INSERT INTO password_resets (correo, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$email, $tokenHash, $expiresAt, $createdAt]);

            // Debug: log token creation (only prefix), expiry and insertion
            error_log(sprintf("[pwd_reset] created token for %s prefix=%s expires_at=%s inserted=%s", $email, substr($token,0,16), $expiresAt, json_encode([$ins->rowCount()==1])));

            // Send the email (best-effort)
            sendResetEmail($email, $token);
        }
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'reset')) {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Token CSRF inválido.';
    }

    if (empty($email) || empty($token)) {
        $errors[] = 'Datos inválidos.';
    }

    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        // Fetch token records for this email that haven't expired
        $stmt = $db->prepare('SELECT id, token_hash, expires_at FROM password_resets WHERE correo = ? AND expires_at >= NOW() ORDER BY created_at DESC');
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll();

            // Debug: log how many rows we fetched for this email
            error_log(sprintf("[pwd_reset] verification attempt for %s: fetched %d rows", $email, count($rows)));

        $valid = false;
        $resetId = null;
        foreach ($rows as $row) {
                // Check password_verify and log result per row (do not log full hash)
                $ok = password_verify($token, $row['token_hash']);
                error_log(sprintf("[pwd_reset] checking row id=%s expires_at=%s verify=%s", $row['id'], $row['expires_at'], $ok ? '1' : '0'));
                if ($ok) {
                    $valid = true;
                    $resetId = $row['id'];
                    break;
                }
        }

        if (!$valid) {
            $errors[] = 'Token inválido o expirado.';
        } else {
            // Update user password
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $u = $db->prepare('UPDATE usuarios SET contrasena = ? WHERE correo = ?');
            $u->execute([$newHash, $email]);

            // Delete all reset tokens for this email
            $del = $db->prepare('DELETE FROM password_resets WHERE correo = ?');
            $del->execute([$email]);

            $success = 'Tu contraseña ha sido actualizada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
        }
    }
}

// If a token is present in GET, show reset form; otherwise show request form
$getToken = $_GET['token'] ?? '';
$getEmail = isset($_GET['email']) ? sanitizeInput($_GET['email']) : '';

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Recuperar contraseña - <?php echo SITE_NAME; ?></title>
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
        
        .reset-container {
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
        
        h2 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        p {
            color: #666;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            text-align: center;
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
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
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
        
        .muted {
            color: #6b7280;
        }
        
        .notice {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #059669;
        }
        
        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .actions .btn {
            width: auto;
            min-width: 180px;
            padding: 0.6rem 1.25rem;
            display: inline-block;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if (!empty($errors)): ?>
            <div class="notice error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <h2>Restablecer contraseña</h2>
            <div class="notice success" role="status" aria-live="polite">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="<?php echo BASE_URL; ?>login.php">Volver a iniciar sesión</a>
            </div>
        <?php elseif (!empty($getToken) && !empty($getEmail)): ?>
            <h2>Restablecer contraseña</h2>
            <p class="muted">Introduce tu nueva contraseña para la cuenta <strong><?php echo htmlspecialchars($getEmail); ?></strong>.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($getToken); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($getEmail); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <div class="form-group">
                    <label for="password">Nueva contraseña</label>
                    <input id="password" name="password" type="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="password2">Confirmar contraseña</label>
                    <input id="password2" name="password2" type="password" required minlength="8">
                </div>
                <button class="btn" type="submit">Actualizar contraseña</button>
                <div class="actions">
                    <a class="link" href="<?php echo BASE_URL; ?>login.php">Volver a iniciar sesión</a>
                </div>
            </form>
        <?php else: ?>
            <h2>Recuperar contraseña</h2>
            <p class="muted">Introduce el correo asociado a tu cuenta. Si existe, recibirás un enlace para restablecer tu contraseña.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" required>
                </div>
                <button class="btn" type="submit">Enviar enlace de recuperación</button>
                <div class="actions">
                    <a class="link" href="<?php echo BASE_URL; ?>login.php">Volver a iniciar sesión</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
