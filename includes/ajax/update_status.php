<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$pickupId = (int)($_POST['pickup_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (!$pickupId || !in_array($newStatus, array_keys(PICKUP_STATUSES))) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Get current pickup info
    $stmt = $db->prepare("SELECT * FROM pickups WHERE id = ?");
    $stmt->execute([$pickupId]);
    $pickup = $stmt->fetch();
    
    if (!$pickup) {
        throw new Exception('Recogida no encontrada');
    }
    
    // Check permissions
    $userType = isset($_SESSION['role']) ? $_SESSION['role'] : 'client';
    if ($userType === 'delivery' && $pickup['assigned_to'] != $_SESSION['user_id']) {
        throw new Exception('No tienes permisos para esta recogida');
    }
    
    $currentStatus = $pickup['status'];
    
    // Update pickup
    $updateFields = ['status = ?', 'updated_at = datetime("now")'];
    $updateValues = [$newStatus];
    
    // Set timestamps based on status
    switch ($newStatus) {
        case 'confirmada':
            $updateFields[] = 'confirmed_at = datetime("now")';
            break;
        case 'asignada':
            $updateFields[] = 'assigned_at = datetime("now")';
            break;
        case 'hecho':
        case 'no_mercancia':
        case 'incidencia':
        case 'vehiculo_no_apropiado':
            $updateFields[] = 'completed_at = datetime("now")';
            break;
    }
    
    $updateValues[] = $pickupId;
    
    $stmt = $db->prepare("UPDATE pickups SET " . implode(', ', $updateFields) . " WHERE id = ?");
    $stmt->execute($updateValues);
    
    // Add to history
    $changedBy = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $changedByType = $userType === 'client' ? 'client' : $userType;
    
    $stmt = $db->prepare("
        INSERT INTO pickup_status_history (pickup_id, previous_status, new_status, changed_by, changed_by_type, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$pickupId, $currentStatus, $newStatus, $changedBy, $changedByType, $notes]);
    
    $db->commit();
    
    // Log activity
    $userName = $_SESSION['name'] ?? $_SESSION['client_name'] ?? 'Sistema';
    logActivity('Estado Actualizado', "Recogida #{$pickupId} cambió de {$currentStatus} a {$newStatus} por {$userName}", 'status_change');
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>