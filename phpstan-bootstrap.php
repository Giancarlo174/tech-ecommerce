<?php
/**
 * PHPStan Bootstrap File
 * Define stubs for global constants, functions, and variables
 * This helps PHPStan understand the project's global scope
 */

// ========== Global Constants (from config.php) ==========
if (!defined('SITE_NAME')) define('SITE_NAME', '');
if (!defined('BASE_URL')) define('BASE_URL', '');
if (!defined('DB_HOST')) define('DB_HOST', '');
if (!defined('DB_NAME')) define('DB_NAME', '');
if (!defined('DB_USER')) define('DB_USER', '');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('IMGUR_CLIENT_ID')) define('IMGUR_CLIENT_ID', '');
if (!defined('IMGUR_CLIENT_SECRET')) define('IMGUR_CLIENT_SECRET', '');
if (!defined('IMGUR_ACCESS_TOKEN')) define('IMGUR_ACCESS_TOKEN', '');
if (!defined('IMGUR_REFRESH_TOKEN')) define('IMGUR_REFRESH_TOKEN', '');
if (!defined('IMGUR_REDIRECT_URI')) define('IMGUR_REDIRECT_URI', '');
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', '');
if (!defined('DEFAULT_IMGUR_CLIENT_ID')) define('DEFAULT_IMGUR_CLIENT_ID', '');

// ========== Global PDO Database Instance (from includes/db.php) ==========
/**
 * @var PDO
 */
$db = new PDO('mysql:host=localhost;dbname=test', 'root', '');

// ========== Global Auth Instance (from includes/auth.php) ==========
class Auth {
    /**
     * @return void
     */
    public function requireLogin(): void {}
    
    /**
     * @return void
     */
    public function requireAdmin(): void {}
    
    /**
     * @return bool
     */
    public function isLoggedIn(): bool { return false; }
    
    /**
     * @return bool
     */
    public function isAdmin(): bool { return false; }
    
    /**
     * @param string $email
     * @param string $password
     * @param string $role
     * @return bool
     */
    public function register(string $email, string $password, string $role = 'cliente'): bool { return false; }
    
    /**
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function login(string $email, string $password): bool { return false; }
    
    /**
     * @return void
     */
    public function logout(): void {}
}

/**
 * @var Auth
 */
$auth = new Auth();

// ========== Global Functions (from includes/functions.php) ==========

/**
 * Add item to shopping cart
 * @param int $productId
 * @param int $quantity
 * @return void
 */
function addToCart(int $productId, int $quantity = 1): void {}

/**
 * Remove item from shopping cart
 * @param int $productId
 * @return void
 */
function removeFromCart(int $productId): void {}

/**
 * Get all items in shopping cart
 * @return array<int|string, mixed>
 */
function getCartItems(): array { return []; }

/**
 * Get total price of shopping cart
 * @return float
 */
function getCartTotal(): float { return 0.0; }

/**
 * Sanitize user input
 * @param string $input
 * @return string
 */
function sanitizeInput(string $input): string { return ''; }

/**
 * Format price for display
 * @param float|int $price
 * @return string
 */
function formatPrice($price): string { return ''; }

/**
 * Upload image to Imgur (wrapper function)
 * @param string $tmpFilePath
 * @return string
 */
function uploadToImgur(string $tmpFilePath): string { return ''; }

/**
 * Upload image to Imgur anonymously
 * @param string $tmpFilePath
 * @return string
 */
function uploadToImgurAnonymous(string $tmpFilePath): string { return ''; }

/**
 * Upload image to Imgur using OAuth
 * @param string $tmpFilePath
 * @return array<string, mixed>|null
 */
function uploadToImgurOAuth(string $tmpFilePath): ?array { return null; }

/**
 * Delete image from Imgur
 * @param string $deletehash
 * @return bool
 */
function deleteFromImgur(string $deletehash): bool { return false; }

/**
 * Get products from database
 * @param int|null $categoryId
 * @param int|null $limit
 * @return array<int|string, mixed>
 */
function getProducts(?int $categoryId = null, ?int $limit = null): array { return []; }

/**
 * Get all product categories
 * @return array<int|string, mixed>
 */
function getCategories(): array { return []; }

/**
 * Get all product brands
 * @return array<int|string, mixed>
 */
function getBrands(): array { return []; }

/**
 * Clean expired purchase history
 * @return void
 */
function cleanExpiredHistory(): void {}
?>
