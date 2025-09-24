<?php
require_once '../includes/config.php';
requireAuth(null, 'delivery');

$db = getDB();

// Get assigned pickups
$stmt = $db->prepare("
    SELECT p.*, c.name as client_name, c.phone as client_phone, c.address as client_address,
           COUNT(pkg.id) as package_count
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.assigned_to = ? AND p.status IN ('asignada', 'en_ruta')
    GROUP BY p.id
    ORDER BY p.pickup_date ASC, p.pickup_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$assignedPickups = $stmt->fetchAll();

// Get today's completed pickups
$stmt = $db->prepare("
    SELECT p.*, c.name as client_name, COUNT(pkg.id) as package_count
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.assigned_to = ? AND p.status IN ('hecho', 'no_mercancia', 'incidencia') 
          AND DATE(p.completed_at) = DATE('now')
    GROUP BY p.id
    ORDER BY p.completed_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$todayCompleted = $stmt->fetchAll();

// Statistics
$stats = [
    'assigned' => 0,
    'en_route' => 0,
    'completed_today' => count($todayCompleted)
];

foreach ($assignedPickups as $pickup) {
    if ($pickup['status'] === 'asignada') {
        $stats['assigned']++;
    } elseif ($pickup['status'] === 'en_ruta') {
        $stats['en_route']++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Repartidor - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Touch-optimized styles */
        body.touch-optimized .btn {
            min-height: 60px;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        body.touch-optimized .table td {
            padding: 20px 15px;
            font-size: 16px;
        }
        
        body.touch-optimized .card {
            margin-bottom: 30px;
        }
        
        .status-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .status-buttons .btn {
            flex: 1;
            min-width: 120px;
        }
        
        .pickup-card {
            border-left: 5px solid #007bff;
            margin-bottom: 20px;
        }
        
        .pickup-card.en-ruta {
            border-left-color: #17a2b8;
        }
    </style>
</head>
<body class="touch-optimized delivery-panel">
    <div class="header">
        <div class="container">
            <h1>🚚 Panel Repartidor</h1>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['name']); ?>
                <a href="../logout.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
    
    <nav class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php" class="active">Mis Recogidas</a></li>
                <li><a href="historial.php">Historial</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Statistics -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="text-warning"><?php echo $stats['assigned']; ?></h2>
                        <p>Asignadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="text-info"><?php echo $stats['en_route']; ?></h2>
                        <p>En Ruta</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h2 class="text-success"><?php echo $stats['completed_today']; ?></h2>
                        <p>Completadas Hoy</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Assigned Pickups -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>🎯 Recogidas Asignadas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignedPickups)): ?>
                            <div class="text-center p-4">
                                <h4>✅ No tienes recogidas asignadas</h4>
                                <p>Cuando se te asignen nuevas recogidas aparecerán aquí.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($assignedPickups as $pickup): ?>
                                <div class="pickup-card card <?php echo $pickup['status']; ?>" data-pickup-id="<?php echo $pickup['id']; ?>">
                                    <div class="card-header">
                                        <div class="row">
                                            <div class="col-8">
                                                <h5>📦 Recogida #<?php echo $pickup['id']; ?></h5>
                                                <small><?php echo htmlspecialchars($pickup['client_name']); ?></small>
                                            </div>
                                            <div class="col-4 text-right">
                                                <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?> status-badge">
                                                    <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>📍 Dirección:</strong><br>
                                                   <?php echo htmlspecialchars($pickup['client_address']); ?></p>
                                                <p><strong>📞 Teléfono:</strong> 
                                                   <a href="tel:<?php echo htmlspecialchars($pickup['client_phone']); ?>">
                                                       <?php echo htmlspecialchars($pickup['client_phone']); ?>
                                                   </a>
                                                </p>
                                                <p><strong>📦 Paquetes:</strong> <?php echo $pickup['package_count']; ?></p>
                                                <?php if ($pickup['pickup_date']): ?>
                                                    <p><strong>📅 Fecha:</strong> <?php echo date('d/m/Y', strtotime($pickup['pickup_date'])); ?>
                                                       <?php if ($pickup['pickup_time']): ?>
                                                           - <?php echo $pickup['pickup_time']; ?>
                                                       <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="status-buttons">
                                                    <?php if ($pickup['status'] === 'asignada'): ?>
                                                        <button onclick="updatePickupStatus(<?php echo $pickup['id']; ?>, 'en_ruta')" 
                                                                class="btn btn-info">
                                                            🚚 Iniciar Ruta
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($pickup['status'], ['asignada', 'en_ruta'])): ?>
                                                        <button onclick="updatePickupStatus(<?php echo $pickup['id']; ?>, 'hecho')" 
                                                                class="btn btn-success">
                                                            ✅ Completar
                                                        </button>
                                                        <button onclick="updatePickupStatus(<?php echo $pickup['id']; ?>, 'no_mercancia')" 
                                                                class="btn btn-warning">
                                                            ❌ No Mercancía
                                                        </button>
                                                        <button onclick="updatePickupStatus(<?php echo $pickup['id']; ?>, 'incidencia')" 
                                                                class="btn btn-danger">
                                                            ⚠️ Incidencia
                                                        </button>
                                                        <button onclick="updatePickupStatus(<?php echo $pickup['id']; ?>, 'vehiculo_no_apropiado')" 
                                                                class="btn btn-secondary">
                                                            🚛 Vehículo No Apropiado
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <a href="recogida_detalle.php?id=<?php echo $pickup['id']; ?>" 
                                                       class="btn btn-primary">
                                                        📋 Ver Detalles
                                                    </a>
                                                </div>
                                                
                                                <?php if ($pickup['notes']): ?>
                                                    <div class="mt-3">
                                                        <strong>📝 Notas:</strong><br>
                                                        <small><?php echo htmlspecialchars($pickup['notes']); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Completed -->
        <?php if (!empty($todayCompleted)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>✅ Completadas Hoy</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Estado</th>
                                            <th>Paquetes</th>
                                            <th>Completado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayCompleted as $pickup): ?>
                                        <tr>
                                            <td>#<?php echo $pickup['id']; ?></td>
                                            <td><?php echo htmlspecialchars($pickup['client_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?>">
                                                    <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $pickup['package_count']; ?></td>
                                            <td><?php echo date('H:i', strtotime($pickup['completed_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function updatePickupStatus(pickupId, newStatus) {
            let confirmMessage = '';
            switch(newStatus) {
                case 'en_ruta':
                    confirmMessage = '¿Iniciar ruta para esta recogida?';
                    break;
                case 'hecho':
                    confirmMessage = '¿Confirmar que la recogida está completada?';
                    break;
                case 'no_mercancia':
                    confirmMessage = '¿Confirmar que no había mercancía para recoger?';
                    break;
                case 'incidencia':
                    confirmMessage = '¿Reportar incidencia para esta recogida?';
                    break;
                case 'vehiculo_no_apropiado':
                    confirmMessage = '¿Reportar que el vehículo no es apropiado?';
                    break;
            }
            
            if (confirm(confirmMessage)) {
                const notes = newStatus === 'incidencia' ? prompt('Describe la incidencia:') : '';
                StatusManager.updateStatus(pickupId, newStatus, notes || '');
            }
        }
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>