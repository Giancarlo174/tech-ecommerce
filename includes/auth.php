<?php
//  Incluye config.php para acceder a BASE_URL para las redirecciones.
// Usamos __DIR__ para asegurar que la ruta sea correcta independientemente desde dónde se incluya auth.php.
require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/db.php'; //

class Auth {
    private $db;
    
    public function __construct() {
        global $db; 
        $this->db = $db;
    }
    
    public function register($email, $password, $role = 'cliente') {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $this->db->prepare("INSERT INTO usuarios (correo, contrasena, rol) VALUES (?, ?, ?)");
        return $stmt->execute([$email, $hashedPassword, $role]);
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, correo, contrasena, rol FROM usuarios WHERE correo = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['contrasena'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['correo'];
            $_SESSION['user_role'] = $user['rol'];
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    public function requireLogin() {
        //  Si el usuario no está logueado, redirige a la página de login usando BASE_URL.
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin(); //  Primero verifica si está logueado.
        //  Si no es admin, redirige al panel de cliente usando BASE_URL.
        if (!$this->isAdmin()) {
            header('Location: ' . BASE_URL . 'cliente/');
            exit;
        }
    }
}

$auth = new Auth();
?>
