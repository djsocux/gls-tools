<?php
require_once '../includes/config.php';
requireAuth('client');

$db = getDB();

// Get client info
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$_SESSION['client_id']]);
$client = $stmt->fetch();

// Get pickup statistics
$stmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM pickups 
    WHERE client_id = ? 
    GROUP BY status
");
$stmt->execute([$_SESSION['client_id']]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

// Get recent pickups
$stmt = $db->prepare("
    SELECT p.*, COUNT(pkg.id) as package_count
    FROM pickups p
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.client_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['client_id']]);
$recentPickups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Cliente - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>GLS Tools - Panel Cliente</h1>
            <div class="user-info">
                Bienvenido, <?php echo htmlspecialchars($client['name']); ?>
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
    
    <nav class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="nueva_recogida.php">Nueva Recogida</a></li>
                <li><a href="mis_recogidas.php">Mis Recogidas</a></li>
                <li><a href="etiquetas.php">Imprimir Etiquetas</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $statusCounts['pendiente_confirmar'] ?? 0; ?></h3>
                        <p>Pendientes de Confirmar</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo ($statusCounts['confirmada'] ?? 0) + ($statusCounts['sin_asignar'] ?? 0); ?></h3>
                        <p>Confirmadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo ($statusCounts['asignada'] ?? 0) + ($statusCounts['en_ruta'] ?? 0); ?></h3>
                        <p>En Proceso</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $statusCounts['hecho'] ?? 0; ?></h3>
                        <p>Completadas</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Acciones Rápidas</h4>
                    </div>
                    <div class="card-body">
                        <a href="nueva_recogida.php" class="btn btn-primary btn-lg mb-2" style="width: 100%;">
                            📦 Nueva Recogida
                        </a>
                        <a href="mis_recogidas.php" class="btn btn-secondary btn-lg mb-2" style="width: 100%;">
                            📋 Ver Mis Recogidas
                        </a>
                        <a href="etiquetas.php" class="btn btn-warning btn-lg" style="width: 100%;">
                            🏷️ Imprimir Etiquetas
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Pickups -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Recogidas Recientes</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentPickups)): ?>
                            <p class="text-center">No hay recogidas recientes</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Estado</th>
                                            <th>Paquetes</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPickups as $pickup): ?>
                                        <tr>
                                            <td>#<?php echo $pickup['id']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?>">
                                                    <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $pickup['package_count']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($pickup['created_at'])); ?></td>
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
        
        <!-- Client Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Información de la Cuenta</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                                <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($client['address']); ?></p>
                                <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($client['city']); ?></p>
                                <p><strong>Código Postal:</strong> <?php echo htmlspecialchars($client['postal_code']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>