<?php
require_once '../includes/config.php';
requireAuth(null, 'admin');

$db = getDB();

// Handle status updates
if ($_POST['action'] ?? '' === 'update_status') {
    $pickupId = (int)($_POST['pickup_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $assignedTo = $_POST['assigned_to'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    
    if ($pickupId && in_array($newStatus, array_keys(PICKUP_STATUSES))) {
        try {
            $db->beginTransaction();
            
            // Get current status for history
            $stmt = $db->prepare("SELECT status FROM pickups WHERE id = ?");
            $stmt->execute([$pickupId]);
            $currentStatus = $stmt->fetchColumn();
            
            // Update pickup
            $updateFields = ['status = ?'];
            $updateValues = [$newStatus];
            
            if ($assignedTo && $newStatus === 'asignada') {
                $updateFields[] = 'assigned_to = ?';
                $updateValues[] = $assignedTo;
                $updateFields[] = 'assigned_at = datetime("now")';
            }
            
            if ($newStatus === 'confirmada') {
                $updateFields[] = 'confirmed_at = datetime("now")';
            }
            
            if (in_array($newStatus, ['hecho', 'no_mercancia', 'incidencia'])) {
                $updateFields[] = 'completed_at = datetime("now")';
            }
            
            $updateValues[] = $pickupId;
            
            $stmt = $db->prepare("UPDATE pickups SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateValues);
            
            // Add to history
            $stmt = $db->prepare("
                INSERT INTO pickup_status_history (pickup_id, previous_status, new_status, changed_by, changed_by_type, notes)
                VALUES (?, ?, ?, ?, 'admin', ?)
            ");
            $stmt->execute([$pickupId, $currentStatus, $newStatus, $_SESSION['user_id'], $notes]);
            
            $db->commit();
            
            logActivity('Recogida Actualizada', "Recogida #{$pickupId} cambió de {$currentStatus} a {$newStatus}", 'status_change');
            
            $success = "Estado actualizado correctamente.";
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$clientFilter = $_GET['client'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$whereConditions = [];
$whereValues = [];

if ($statusFilter && in_array($statusFilter, array_keys(PICKUP_STATUSES))) {
    $whereConditions[] = "p.status = ?";
    $whereValues[] = $statusFilter;
}

if ($dateFilter === 'today') {
    $whereConditions[] = "DATE(p.created_at) = DATE('now')";
} elseif ($dateFilter === 'week') {
    $whereConditions[] = "p.created_at >= datetime('now', '-7 days')";
} elseif ($dateFilter) {
    $whereConditions[] = "DATE(p.pickup_date) = ?";
    $whereValues[] = $dateFilter;
}

if ($clientFilter) {
    $whereConditions[] = "c.name LIKE ?";
    $whereValues[] = "%{$clientFilter}%";
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$stmt = $db->prepare("
    SELECT COUNT(*)
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    {$whereClause}
");
$stmt->execute($whereValues);
$totalPickups = $stmt->fetchColumn();
$totalPages = ceil($totalPickups / $perPage);

// Get pickups
$offset = ($page - 1) * $perPage;
$stmt = $db->prepare("
    SELECT p.*, c.name as client_name, c.phone as client_phone,
           u.name as assigned_name,
           COUNT(pkg.id) as package_count
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    LEFT JOIN packages pkg ON p.id = pkg.pickup_id
    {$whereClause}
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($whereValues, [$perPage, $offset]));
$pickups = $stmt->fetchAll();

// Get delivery users for assignment
$stmt = $db->query("SELECT * FROM users WHERE role = 'delivery' AND active = 1 ORDER BY name");
$deliveryUsers = $stmt->fetchAll();

// Get clients for filter
$stmt = $db->query("SELECT DISTINCT name FROM clients WHERE active = 1 ORDER BY name");
$clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recogidas - GLS Tools</title>
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="recogidas.php" class="active">Recogidas</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="repartidores.php">Repartidores</a></li>
                <li><a href="configuracion.php">Configuración</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Filtros</h4>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-control form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach (PICKUP_STATUSES as $status => $label): ?>
                                <option value="<?php echo $status; ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <select name="date" class="form-control form-select">
                            <option value="">Todas las fechas</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Hoy</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Esta semana</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" name="client" class="form-control" 
                               value="<?php echo htmlspecialchars($clientFilter); ?>" 
                               placeholder="Buscar cliente...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="recogidas.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Pickups Table -->
        <div class="card">
            <div class="card-header">
                <h4>Recogidas (<?php echo $totalPickups; ?> total)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($pickups)): ?>
                    <p class="text-center">No se encontraron recogidas con los filtros seleccionados.</p>
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
                                    <th>Asignado a</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pickups as $pickup): ?>
                                <tr>
                                    <td>#<?php echo $pickup['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($pickup['client_name']); ?>
                                        <br><small><?php echo htmlspecialchars($pickup['client_phone']); ?></small>
                                    </td>
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
                                        <?php echo $pickup['assigned_name'] ? htmlspecialchars($pickup['assigned_name']) : '-'; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pickup['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="recogida_detalle.php?id=<?php echo $pickup['id']; ?>" 
                                               class="btn btn-sm btn-info">Ver</a>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    data-toggle="modal" data-target="#statusModal" 
                                                    onclick="openStatusModal(<?php echo $pickup['id']; ?>, '<?php echo $pickup['status']; ?>')">
                                                Cambiar Estado
                                            </button>
                                        </div>
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
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>&client=<?php echo urlencode($clientFilter); ?>" 
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
    
    <!-- Status Update Modal -->
    <div id="statusModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 15% auto; padding: 20px; width: 80%; max-width: 500px; border-radius: 8px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4>Cambiar Estado de Recogida</h4>
                <button type="button" onclick="closeStatusModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="pickup_id" id="modalPickupId">
                
                <div class="form-group">
                    <label class="form-label">Nuevo Estado</label>
                    <select name="status" id="modalStatus" class="form-control form-select" required>
                        <?php foreach (PICKUP_STATUSES as $status => $label): ?>
                            <option value="<?php echo $status; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="assignmentGroup" style="display: none;">
                    <label class="form-label">Asignar a Repartidor</label>
                    <select name="assigned_to" id="modalAssignedTo" class="form-control form-select">
                        <option value="">Seleccionar repartidor...</option>
                        <?php foreach ($deliveryUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Observaciones sobre el cambio de estado..."></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                    <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function openStatusModal(pickupId, currentStatus) {
            document.getElementById('modalPickupId').value = pickupId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
            
            // Show assignment dropdown for 'asignada' status
            const statusSelect = document.getElementById('modalStatus');
            const assignmentGroup = document.getElementById('assignmentGroup');
            
            statusSelect.addEventListener('change', function() {
                if (this.value === 'asignada') {
                    assignmentGroup.style.display = 'block';
                    document.getElementById('modalAssignedTo').required = true;
                } else {
                    assignmentGroup.style.display = 'none';
                    document.getElementById('modalAssignedTo').required = false;
                }
            });
            
            // Trigger change event to set initial state
            statusSelect.dispatchEvent(new Event('change'));
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeStatusModal();
            }
        }
    </script>
</body>
</html>