<?php
require_once '../includes/config.php';
requireAuth('client');

$db = getDB();

// Get client's pickups
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT p.*, COUNT(pkg.id) as package_count
    FROM pickups p
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.client_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['client_id'], $perPage, $offset]);
$pickups = $stmt->fetchAll();

// Get total count for pagination
$stmt = $db->prepare("SELECT COUNT(*) FROM pickups WHERE client_id = ?");
$stmt->execute([$_SESSION['client_id']]);
$totalPickups = $stmt->fetchColumn();
$totalPages = ceil($totalPickups / $perPage);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Recogidas - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>GLS Tools - Panel Cliente</h1>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['client_name']); ?>
                <a href="logout.php">Cerrar Sesión</a>
            </div>
        </div>
    </div>
    
    <nav class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="nueva_recogida.php">Nueva Recogida</a></li>
                <li><a href="mis_recogidas.php" class="active">Mis Recogidas</a></li>
                <li><a href="etiquetas.php">Imprimir Etiquetas</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h2>Historial de Recogidas</h2>
            </div>
            <div class="card-body">
                <?php if (empty($pickups)): ?>
                    <div class="text-center p-4">
                        <h4>📭 No tienes recogidas aún</h4>
                        <p>Cuando solicites tu primera recogida aparecerá aquí.</p>
                        <a href="nueva_recogida.php" class="btn btn-primary">Solicitar Primera Recogida</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Estado</th>
                                    <th>Paquetes</th>
                                    <th>Fecha Recogida</th>
                                    <th>Solicitado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pickups as $pickup): ?>
                                <tr>
                                    <td>#<?php echo $pickup['id']; ?></td>
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
                                           class="btn btn-sm btn-info">Ver Detalles</a>
                                        <?php if (in_array($pickup['status'], ['confirmada', 'sin_asignar', 'asignada', 'en_ruta', 'hecho'])): ?>
                                            <a href="../includes/print_label.php?pickup_id=<?php echo $pickup['id']; ?>" 
                                               target="_blank" class="btn btn-sm btn-warning">Etiquetas</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="text-center mt-4">
                            <div class="btn-group">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" 
                                       class="btn btn-<?php echo $i === $page ? 'primary' : 'secondary'; ?> btn-sm">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>