<?php
require_once '../includes/config.php';
requireAuth('client');

$db = getDB();

// Get client's pickups that can have labels printed
$stmt = $db->prepare("
    SELECT p.*, COUNT(pkg.id) as package_count
    FROM pickups p
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    WHERE p.client_id = ? AND p.status NOT IN ('pendiente_confirmar')
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['client_id']]);
$pickups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Etiquetas - GLS Tools</title>
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
                <li><a href="mis_recogidas.php">Mis Recogidas</a></li>
                <li><a href="etiquetas.php" class="active">Imprimir Etiquetas</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h2>🏷️ Imprimir Etiquetas de Envío</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>ℹ️ Información:</strong> Las etiquetas contienen los datos del destinatario y códigos de barras para que la oficina pueda identificar y procesar los paquetes correctamente.
                </div>
                
                <?php if (empty($pickups)): ?>
                    <div class="text-center p-4">
                        <h4>📭 No hay recogidas disponibles para etiquetar</h4>
                        <p>Solo se pueden imprimir etiquetas para recogidas confirmadas.</p>
                        <a href="nueva_recogida.php" class="btn btn-primary">Solicitar Nueva Recogida</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Recogida</th>
                                    <th>Estado</th>
                                    <th>Paquetes</th>
                                    <th>Fecha Recogida</th>
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
                                    <td>
                                        <a href="../includes/print_label.php?pickup_id=<?php echo $pickup['id']; ?>" 
                                           target="_blank" class="btn btn-primary">
                                            🖨️ Imprimir Todas las Etiquetas
                                        </a>
                                        <a href="recogida_detalle.php?id=<?php echo $pickup['id']; ?>" 
                                           class="btn btn-secondary btn-sm">
                                            Ver Detalles
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning mt-4">
                        <h5>📋 Instrucciones para el uso de etiquetas:</h5>
                        <ol>
                            <li>Imprima las etiquetas en papel adhesivo o papel normal para pegar en los paquetes</li>
                            <li>Cada paquete debe llevar su etiqueta correspondiente</li>
                            <li>Las etiquetas contienen:
                                <ul>
                                    <li><strong>Datos del remitente y destinatario</strong></li>
                                    <li><strong>Código de barras GLS</strong> (si ya tiene número de seguimiento)</li>
                                    <li><strong>Código de barras del sistema</strong> para control interno</li>
                                </ul>
                            </li>
                            <li>La oficina utilizará estos códigos para aplicar las pegatinas oficiales de GLS</li>
                            <li>Asegúrese de que las etiquetas estén bien pegadas y sean legibles</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>