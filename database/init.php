<?php
/**
 * Database Initialization Script
 * Run this once to create the database with sample data
 */

require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDB();
    
    // Check if tables exist
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "Base de datos ya inicializada.\n";
        exit;
    }
    
    // Create database schema
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $db->exec($schema);
    
    // Insert sample client
    $sampleToken = generateToken();
    $stmt = $db->prepare("
        INSERT INTO clients (token, name, email, phone, address, city, postal_code)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sampleToken,
        'Cliente Demo',
        'cliente@demo.com',
        '123456789',
        'Calle de Ejemplo, 123',
        'Madrid',
        '28001'
    ]);
    
    // Insert sample delivery user
    $stmt = $db->prepare("
        INSERT INTO users (username, password, role, name, email, phone)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'repartidor1',
        hashPassword('delivery123'),
        'delivery',
        'Juan Repartidor',
        'repartidor@gls-tools.com',
        '987654321'
    ]);
    
    echo "Base de datos inicializada correctamente.\n";
    echo "Token del cliente demo: " . $sampleToken . "\n";
    echo "Usuario admin: admin / admin123\n";
    echo "Usuario repartidor: repartidor1 / delivery123\n";
    
    logActivity('Database Init', 'Base de datos inicializada con datos de ejemplo', 'system');
    
} catch (Exception $e) {
    echo "Error al inicializar la base de datos: " . $e->getMessage() . "\n";
}
?>