<?php
/**
 * GLS Package Pickup System - Main Configuration
 */

// Database configuration
define('DB_PATH', __DIR__ . '/../database/gls_pickup.db');

// Session configuration
session_start();

// Timezone
date_default_timezone_set('Europe/Madrid');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base URL configuration
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
define('BASE_URL', $protocol . '://' . $host . $script_dir);

// Security
define('HASH_ALGO', PASSWORD_BCRYPT);
define('TOKEN_LENGTH', 32);

// Pickup statuses
define('PICKUP_STATUSES', [
    'pendiente_confirmar' => 'Pendiente de Confirmar',
    'confirmada' => 'Confirmada',
    'sin_asignar' => 'Sin Asignar',
    'asignada' => 'Asignada',
    'en_ruta' => 'En Ruta',
    'no_mercancia' => 'No Mercancía',
    'hecho' => 'Hecho',
    'incidencia' => 'Incidencia',
    'vehiculo_no_apropiado' => 'Vehículo No Apropiado'
]);

// Status colors for UI
define('STATUS_COLORS', [
    'pendiente_confirmar' => '#ffc107',
    'confirmada' => '#28a745',
    'sin_asignar' => '#6c757d',
    'asignada' => '#007bff',
    'en_ruta' => '#17a2b8',
    'no_mercancia' => '#dc3545',
    'hecho' => '#28a745',
    'incidencia' => '#dc3545',
    'vehiculo_no_apropiado' => '#fd7e14'
]);

/**
 * Initialize database if it doesn't exist
 */
function initDatabase() {
    if (!file_exists(DB_PATH)) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);
        
        logActivity('Sistema', 'Base de datos inicializada', 'system');
    }
}

/**
 * Get database connection
 */
function getDB() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die('Error de conexión: ' . $e->getMessage());
    }
}

/**
 * Log activity to Log.dm file
 */
function logActivity($entity, $description, $type = 'modification') {
    $logFile = __DIR__ . '/../Log.dm';
    $timestamp = date('d/m/Y - H:i');
    $logEntry = "[{$timestamp}] {$entity}: {$description}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Generate secure token
 */
function generateToken($length = TOKEN_LENGTH) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, HASH_ALGO);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated($userType = null) {
    if ($userType === 'client') {
        return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
    } else {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Require authentication
 */
function requireAuth($userType = null, $role = null) {
    if (!isAuthenticated($userType)) {
        if ($userType === 'client') {
            redirect('/cliente/login.php');
        } else {
            redirect('/login.php');
        }
    }
    
    if ($role && isset($_SESSION['role']) && $_SESSION['role'] !== $role) {
        redirect('/unauthorized.php');
    }
}

// Initialize database on first load
initDatabase();
?>