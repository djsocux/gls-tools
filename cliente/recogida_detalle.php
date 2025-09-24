<?php
require_once '../includes/config.php';

$pickupId = (int)($_GET['id'] ?? 0);
if (!$pickupId) {
    redirect('/cliente/mis_recogidas.php');
}

$db = getDB();

// Get pickup with client info
$stmt = $db->prepare("
    SELECT p.*, c.name as client_name, c.email as client_email, c.phone as client_phone,
           u.name as assigned_name
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    WHERE p.id = ?
");
$stmt->execute([$pickupId]);
$pickup = $stmt->fetch();

if (!$pickup) {
    redirect('/cliente/mis_recogidas.php');
}

// Check permissions
if (isAuthenticated('client')) {
    requireAuth('client');
    if ($pickup['client_id'] != $_SESSION['client_id']) {
        redirect('/cliente/mis_recogidas.php');
    }
} else {
    requireAuth();
}

// Get packages
$stmt = $db->prepare("SELECT * FROM packages WHERE pickup_id = ? ORDER BY id");
$stmt->execute([$pickupId]);
$packages = $stmt->fetchAll();

// Get status history
$stmt = $db->prepare("
    SELECT h.*, u.name as changed_by_name
    FROM pickup_status_history h
    LEFT JOIN users u ON h.changed_by = u.id
    WHERE h.pickup_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$pickupId]);
$history = $stmt->fetchAll();

$isClient = isAuthenticated('client');
$backUrl = $isClient ? '/cliente/mis_recogidas.php' : 
           ($_SESSION['role'] === 'admin' ? '/administrador/recogidas.php' : '/repartidor/dashboard.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Recogida #<?php echo $pickupId; ?> - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="<?php echo $_SESSION['role'] === 'delivery' ? 'touch-optimized delivery-panel' : ''; ?>">
    <div class="header">
        <div class="container">
            <h1>GLS Tools - <?php echo $isClient ? 'Panel Cliente' : 
                ($_SESSION['role'] === 'admin' ? 'Panel Administrador' : 'Panel Repartidor'); ?></h1>
            <div class="user-info">
                <?php echo htmlspecialchars($isClient ? $_SESSION['client_name'] : $_SESSION['name']); ?>
                <a href="<?php echo $isClient ? '/cliente/logout.php' : '/logout.php'; ?>">Cerrar Sesión</a>
            </div>
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="mb-3">
            <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">← Volver</a>
            <?php if (in_array($pickup['status'], ['confirmada', 'sin_asignar', 'asignada', 'en_ruta', 'hecho'])): ?>
                <a href="../includes/print_label.php?pickup_id=<?php echo $pickupId; ?>" 
                   target="_blank" class="btn btn-warning">🏷️ Imprimir Etiquetas</a>
            <?php endif; ?>
        </div>
        
        <!-- Pickup Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>📦 Recogida #<?php echo $pickupId; ?></h2>
                <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?> float-right" style="font-size: 14px;">
                    <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Información General</h5>
                        <p><strong>Estado:</strong> 
                            <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?>">
                                <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                            </span>
                        </p>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pickup['client_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($pickup['client_email']); ?></p>
                        <p><strong>Teléfono:</strong> 
                            <a href="tel:<?php echo htmlspecialchars($pickup['client_phone']); ?>">
                                <?php echo htmlspecialchars($pickup['client_phone']); ?>
                            </a>
                        </p>
                        <p><strong>Paquetes:</strong> <?php echo count($packages); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Fechas y Asignación</h5>
                        <p><strong>Fecha de Recogida:</strong> 
                            <?php if ($pickup['pickup_date']): ?>
                                <?php echo date('d/m/Y', strtotime($pickup['pickup_date'])); ?>
                                <?php if ($pickup['pickup_time']): ?>
                                    - <?php echo $pickup['pickup_time']; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                No especificada
                            <?php endif; ?>
                        </p>
                        <p><strong>Solicitado:</strong> <?php echo date('d/m/Y H:i', strtotime($pickup['created_at'])); ?></p>
                        <?php if ($pickup['confirmed_at']): ?>
                            <p><strong>Confirmado:</strong> <?php echo date('d/m/Y H:i', strtotime($pickup['confirmed_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($pickup['assigned_at'] && $pickup['assigned_name']): ?>
                            <p><strong>Asignado a:</strong> <?php echo htmlspecialchars($pickup['assigned_name']); ?></p>
                            <p><strong>Fecha asignación:</strong> <?php echo date('d/m/Y H:i', strtotime($pickup['assigned_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($pickup['completed_at']): ?>
                            <p><strong>Completado:</strong> <?php echo date('d/m/Y H:i', strtotime($pickup['completed_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($pickup['notes']): ?>
                    <div class="mt-3">
                        <h5>Notas</h5>
                        <p><?php echo nl2br(htmlspecialchars($pickup['notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Packages -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>📦 Paquetes (<?php echo count($packages); ?>)</h3>
            </div>
            <div class="card-body">
                <?php foreach ($packages as $index => $package): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Paquete <?php echo $index + 1; ?>
                                <?php if ($package['tracking_number']): ?>
                                    <small class="text-muted">- GLS: <?php echo htmlspecialchars($package['tracking_number']); ?></small>
                                <?php endif; ?>
                                <a href="../includes/print_label.php?pickup_id=<?php echo $pickupId; ?>&package_id=<?php echo $package['id']; ?>" 
                                   target="_blank" class="btn btn-sm btn-warning float-right">🏷️ Etiqueta</a>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>📥 Destinatario</h6>
                                    <p><strong><?php echo htmlspecialchars($package['recipient_name']); ?></strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($package['recipient_address'])); ?></p>
                                    <p><?php echo htmlspecialchars($package['recipient_city']); ?> - <?php echo htmlspecialchars($package['recipient_postal_code']); ?></p>
                                    <p><strong>Tel:</strong> 
                                        <a href="tel:<?php echo htmlspecialchars($package['recipient_phone']); ?>">
                                            <?php echo htmlspecialchars($package['recipient_phone']); ?>
                                        </a>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>📋 Detalles del Paquete</h6>
                                    <?php if ($package['weight']): ?>
                                        <p><strong>Peso:</strong> <?php echo $package['weight']; ?> kg</p>
                                    <?php endif; ?>
                                    <?php if ($package['dimensions']): ?>
                                        <p><strong>Dimensiones:</strong> <?php echo htmlspecialchars($package['dimensions']); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Bultos:</strong> <?php echo $package['quantity']; ?></p>
                                    <?php if ($package['service_type']): ?>
                                        <p><strong>Tipo de Servicio:</strong> <?php echo htmlspecialchars($package['service_type']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($package['barcode_pickup']): ?>
                                        <p><strong>Código:</strong> <code><?php echo htmlspecialchars($package['barcode_pickup']); ?></code></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($package['observations']): ?>
                                <div class="mt-3">
                                    <h6>📝 Observaciones</h6>
                                    <p><?php echo nl2br(htmlspecialchars($package['observations'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Status History -->
        <?php if (!empty($history)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>📋 Historial de Estados</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Estado Anterior</th>
                                    <th>Nuevo Estado</th>
                                    <th>Modificado por</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?></td>
                                    <td>
                                        <?php if ($entry['previous_status']): ?>
                                            <span class="badge badge-<?php echo str_replace('_', '-', $entry['previous_status']); ?>">
                                                <?php echo PICKUP_STATUSES[$entry['previous_status']]; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo str_replace('_', '-', $entry['new_status']); ?>">
                                            <?php echo PICKUP_STATUSES[$entry['new_status']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo $entry['changed_by_name'] ? htmlspecialchars($entry['changed_by_name']) : 
                                             ucfirst($entry['changed_by_type']); 
                                        ?>
                                    </td>
                                    <td><?php echo $entry['notes'] ? htmlspecialchars($entry['notes']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>