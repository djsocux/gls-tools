<?php
require_once '../includes/config.php';
requireAuth(null, 'admin');

$db = getDB();

// Get statistics
$stats = [];

// Total pickups by status
$stmt = $db->query("
    SELECT status, COUNT(*) as count 
    FROM pickups 
    GROUP BY status
");
while ($row = $stmt->fetch()) {
    $stats['pickups_by_status'][$row['status']] = $row['count'];
}

// Today's pickups
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM pickups 
    WHERE DATE(created_at) = DATE('now')
");
$stats['today_pickups'] = $stmt->fetchColumn();

// Pending confirmations
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM pickups 
    WHERE status = 'pendiente_confirmar'
");
$stats['pending_confirmations'] = $stmt->fetchColumn();

// Active clients
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM clients 
    WHERE active = 1
");
$stats['active_clients'] = $stmt->fetchColumn();

// Active delivery users
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role = 'delivery' AND active = 1
");
$stats['delivery_users'] = $stmt->fetchColumn();

// Recent pickups needing attention
$stmt = $db->query("
    SELECT p.*, c.name as client_name, COUNT(pkg.id) as package_count
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.status IN ('pendiente_confirmar', 'confirmada', 'sin_asignar')
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
$recentPickups = $stmt->fetchAll();

// Delivery users with current assignments
$stmt = $db->query("
    SELECT u.*, COUNT(p.id) as assigned_pickups
    FROM users u
    LEFT JOIN pickups p ON u.id = p.assigned_to AND p.status IN ('asignada', 'en_ruta')
    WHERE u.role = 'delivery' AND u.active = 1
    GROUP BY u.id
    ORDER BY assigned_pickups DESC
");
$deliveryUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>GLS Tools - Panel Administrador</h1>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['name']); ?>
                <a href="../logout.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
    
    <nav class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="recogidas.php">Recogidas</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="repartidores.php">Repartidores</a></li>
                <li><a href="configuracion.php">Configuración</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?php echo $stats['pending_confirmations']; ?></h3>
                        <p>Pendientes Confirmar</p>
                        <a href="recogidas.php?status=pendiente_confirmar" class="btn btn-sm btn-warning">Ver</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?php echo $stats['today_pickups']; ?></h3>
                        <p>Recogidas Hoy</p>
                        <a href="recogidas.php?date=today" class="btn btn-sm btn-primary">Ver</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?php echo $stats['active_clients']; ?></h3>
                        <p>Clientes Activos</p>
                        <a href="clientes.php" class="btn btn-sm btn-success">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info"><?php echo $stats['delivery_users']; ?></h3>
                        <p>Repartidores</p>
                        <a href="repartidores.php" class="btn btn-sm btn-info">Gestionar</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Distribution Chart -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Estado de Recogidas</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach (PICKUP_STATUSES as $status => $label): ?>
                            <?php $count = $stats['pickups_by_status'][$status] ?? 0; ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo $label; ?></span>
                                    <span><?php echo $count; ?></span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" style="width: <?php echo $count > 0 ? ($count / array_sum($stats['pickups_by_status'] ?? [1]) * 100) : 0; ?>%; background-color: <?php echo STATUS_COLORS[$status]; ?>;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Users Status -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Estado Repartidores</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($deliveryUsers)): ?>
                            <p class="text-center">No hay repartidores registrados</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Repartidor</th>
                                            <th>Asignadas</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deliveryUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['assigned_pickups'] > 0 ? 'warning' : 'success'; ?>">
                                                    <?php echo $user['assigned_pickups']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">Activo</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Pickups Needing Attention -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Recogidas Pendientes de Gestión</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentPickups)): ?>
                            <p class="text-center">No hay recogidas pendientes</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Estado</th>
                                            <th>Paquetes</th>
                                            <th>Fecha Recogida</th>
                                            <th>Creado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPickups as $pickup): ?>
                                        <tr>
                                            <td>#<?php echo $pickup['id']; ?></td>
                                            <td><?php echo htmlspecialchars($pickup['client_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?>">
                                                    <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $pickup['package_count']; ?></td>
                                            <td>
                                                <?php if ($pickup['pickup_date']): ?>
                                                    <?php echo date('d/m/Y', strtotime($pickup['pickup_date'])); ?>
                                                    <?php if ($pickup['pickup_time']): ?>
                                                        <br><small><?php echo $pickup['pickup_time']; ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($pickup['created_at'])); ?></td>
                                            <td>
                                                <a href="recogida_detalle.php?id=<?php echo $pickup['id']; ?>" 
                                                   class="btn btn-sm btn-primary">Ver</a>
                                                <?php if ($pickup['status'] === 'pendiente_confirmar'): ?>
                                                    <button onclick="confirmPickup(<?php echo $pickup['id']; ?>)" 
                                                            class="btn btn-sm btn-success">Confirmar</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function confirmPickup(pickupId) {
            if (confirm('¿Confirmar esta recogida?')) {
                StatusManager.updateStatus(pickupId, 'confirmada', 'Confirmada por administrador');
            }
        }
    </script>
</body>
</html>