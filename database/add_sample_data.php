<?php
/**
 * Add sample data to existing database
 */
require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDB();
    
    // Add sample client if none exists
    $stmt = $db->query("SELECT COUNT(*) FROM clients");
    $clientCount = $stmt->fetchColumn();
    
    if ($clientCount == 0) {
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
        echo "Cliente demo creado con token: " . $sampleToken . "\n";
    }
    
    // Add sample delivery user if only admin exists
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'delivery'");
    $deliveryCount = $stmt->fetchColumn();
    
    if ($deliveryCount == 0) {
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
        echo "Usuario repartidor creado: repartidor1 / delivery123\n";
    }
    
    // Show access credentials
    echo "\n=== CREDENCIALES DE ACCESO ===\n";
    echo "Admin: admin / admin123\n";
    echo "Repartidor: repartidor1 / delivery123\n";
    
    $stmt = $db->query("SELECT token FROM clients LIMIT 1");
    $clientToken = $stmt->fetchColumn();
    if ($clientToken) {
        echo "Cliente token: " . $clientToken . "\n";
    }
    
    logActivity('Sample Data', 'Datos de ejemplo agregados al sistema', 'setup');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>