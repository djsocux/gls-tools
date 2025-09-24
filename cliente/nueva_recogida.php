<?php
require_once '../includes/config.php';
requireAuth('client');

$db = getDB();
$success = '';
$error = '';

if ($_POST) {
    try {
        $pickup_date = $_POST['pickup_date'] ?? '';
        $pickup_time = $_POST['pickup_time'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $packages = $_POST['packages'] ?? [];
        
        // Validation
        if (empty($pickup_date) || empty($pickup_time)) {
            throw new Exception('La fecha y hora de recogida son obligatorias.');
        }
        
        if (empty($packages)) {
            throw new Exception('Debe agregar al menos un paquete.');
        }
        
        // Validate packages
        foreach ($packages as $package) {
            if (empty($package['recipient_name']) || empty($package['recipient_phone']) || 
                empty($package['recipient_address']) || empty($package['recipient_city']) || 
                empty($package['recipient_postal_code'])) {
                throw new Exception('Todos los campos obligatorios de los paquetes deben completarse.');
            }
        }
        
        $db->beginTransaction();
        
        // Insert pickup
        $stmt = $db->prepare("
            INSERT INTO pickups (client_id, pickup_date, pickup_time, notes, status, created_at)
            VALUES (?, ?, ?, ?, 'pendiente_confirmar', datetime('now'))
        ");
        $stmt->execute([$_SESSION['client_id'], $pickup_date, $pickup_time, $notes]);
        $pickupId = $db->lastInsertId();
        
        // Insert packages
        $stmt = $db->prepare("
            INSERT INTO packages (
                pickup_id, tracking_number, recipient_name, recipient_phone, recipient_address,
                recipient_city, recipient_postal_code, weight, dimensions, quantity,
                service_type, observations, barcode_pickup, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        
        foreach ($packages as $package) {
            $barcodePickup = 'PU' . str_pad($pickupId, 6, '0', STR_PAD_LEFT) . '-' . generateToken(4);
            
            $stmt->execute([
                $pickupId,
                $package['tracking_number'] ?: null,
                $package['recipient_name'],
                $package['recipient_phone'],
                $package['recipient_address'],
                $package['recipient_city'],
                $package['recipient_postal_code'],
                $package['weight'] ?: null,
                $package['dimensions'] ?: null,
                $package['quantity'] ?: 1,
                $package['service_type'] ?: null,
                $package['observations'] ?: null,
                $barcodePickup
            ]);
        }
        
        $db->commit();
        
        logActivity('Nueva Recogida', "Recogida #{$pickupId} creada por cliente {$_SESSION['client_name']}", 'creation');
        
        $success = "¡Recogida creada exitosamente! Número de recogida: #{$pickupId}";
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// Get time slots from config
$stmt = $db->prepare("SELECT config_value FROM config WHERE config_key = 'pickup_time_slots'");
$stmt->execute();
$timeSlots = explode(',', $stmt->fetchColumn() ?: '09:00-10:00,10:00-11:00,11:00-12:00,14:00-15:00,15:00-16:00,16:00-17:00');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Recogida - GLS Tools</title>
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
                <li><a href="nueva_recogida.php" class="active">Nueva Recogida</a></li>
                <li><a href="mis_recogidas.php">Mis Recogidas</a></li>
                <li><a href="etiquetas.php">Imprimir Etiquetas</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h2>Solicitar Nueva Recogida</h2>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" id="pickup-form">
                    <!-- Pickup Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Información de la Recogida</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Fecha de Recogida *</label>
                                        <input type="date" name="pickup_date" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Franja Horaria *</label>
                                        <select name="pickup_time" class="form-control form-select" required>
                                            <option value="">Seleccione una franja horaria</option>
                                            <?php foreach ($timeSlots as $slot): ?>
                                                <option value="<?php echo trim($slot); ?>"><?php echo trim($slot); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Notas adicionales</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Instrucciones especiales, ubicación específica, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Packages -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Paquetes a Recoger
                                <button type="button" class="btn btn-success btn-sm float-right" 
                                        onclick="PackageManager.addPackageRow()">
                                    Agregar Paquete
                                </button>
                            </h4>
                        </div>
                        <div class="card-body">
                            <div id="packages-container">
                                <!-- First package (required) -->
                                <div class="package-row card mb-3">
                                    <div class="card-header">
                                        <h5>Paquete 1</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Número de seguimiento GLS (opcional)</label>
                                                    <input type="text" name="packages[0][tracking_number]" class="form-control">
                                                    <small class="text-muted">Si ya tiene el número de seguimiento de GLS</small>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Nombre del destinatario *</label>
                                                    <input type="text" name="packages[0][recipient_name]" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Teléfono del destinatario *</label>
                                                    <input type="tel" name="packages[0][recipient_phone]" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Dirección del destinatario *</label>
                                                    <textarea name="packages[0][recipient_address]" class="form-control" rows="2" required></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Ciudad *</label>
                                                    <input type="text" name="packages[0][recipient_city]" class="form-control" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Código postal *</label>
                                                    <input type="text" name="packages[0][recipient_postal_code]" class="form-control" required pattern="\\d{5}">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Peso aproximado (kg)</label>
                                                    <input type="number" step="0.1" name="packages[0][weight]" class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Dimensiones aproximadas</label>
                                                    <input type="text" name="packages[0][dimensions]" class="form-control" 
                                                           placeholder="Ej: 30x20x10 cm">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Cantidad de bultos</label>
                                                    <input type="number" min="1" name="packages[0][quantity]" class="form-control" value="1">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Observaciones</label>
                                                    <textarea name="packages[0][observations]" class="form-control" rows="2" 
                                                              placeholder="Características especiales, fragilidad, etc."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Enviar Solicitud de Recogida
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary btn-lg ml-2">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>