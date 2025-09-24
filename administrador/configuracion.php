<?php
require_once '../includes/config.php';
requireAuth(null, 'admin');

$db = getDB();
$success = '';
$error = '';

// Handle configuration updates
if ($_POST['action'] ?? '' === 'save_config') {
    try {
        $configs = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'company_phone' => trim($_POST['company_phone'] ?? ''),
            'pickup_time_slots' => trim($_POST['pickup_time_slots'] ?? ''),
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $db->prepare("
                INSERT OR REPLACE INTO config (config_key, config_value, updated_at)
                VALUES (?, ?, datetime('now'))
            ");
            $stmt->execute([$key, $value]);
        }
        
        $success = "Configuración guardada correctamente.";
        logActivity('Configuración Actualizada', "Configuración del sistema actualizada por {$_SESSION['name']}", 'config');
    } catch (Exception $e) {
        $error = "Error al guardar configuración: " . $e->getMessage();
    }
}

// Handle database maintenance
if ($_POST['action'] ?? '' === 'cleanup_logs') {
    try {
        // Keep only last 1000 log entries
        $stmt = $db->query("
            DELETE FROM pickup_status_history 
            WHERE id NOT IN (
                SELECT id FROM pickup_status_history 
                ORDER BY created_at DESC 
                LIMIT 1000
            )
        ");
        $deletedLogs = $stmt->rowCount();
        
        $success = "Se eliminaron {$deletedLogs} registros de historial antiguos.";
        logActivity('Limpieza BD', "Eliminados {$deletedLogs} registros de historial", 'maintenance');
    } catch (Exception $e) {
        $error = "Error en la limpieza: " . $e->getMessage();
    }
}

// Get current configuration
$configs = [];
$stmt = $db->query("SELECT config_key, config_value FROM config");
while ($row = $stmt->fetch()) {
    $configs[$row['config_key']] = $row['config_value'];
}

// Get system statistics
$stats = [];

// Database size
if (file_exists(DB_PATH)) {
    $stats['db_size'] = round(filesize(DB_PATH) / 1024 / 1024, 2) . ' MB';
} else {
    $stats['db_size'] = 'N/A';
}

// Record counts
$tables = ['clients', 'users', 'pickups', 'packages', 'pickup_status_history'];
foreach ($tables as $table) {
    $stmt = $db->query("SELECT COUNT(*) FROM {$table}");
    $stats[$table . '_count'] = $stmt->fetchColumn();
}

// Oldest and newest records
$stmt = $db->query("SELECT MIN(created_at) as oldest, MAX(created_at) as newest FROM pickups");
$dateRange = $stmt->fetch();
$stats['oldest_pickup'] = $dateRange['oldest'] ? date('d/m/Y', strtotime($dateRange['oldest'])) : 'N/A';
$stats['newest_pickup'] = $dateRange['newest'] ? date('d/m/Y', strtotime($dateRange['newest'])) : 'N/A';

// Log file size
$logFile = __DIR__ . '/../Log.dm';
if (file_exists($logFile)) {
    $stats['log_size'] = round(filesize($logFile) / 1024, 2) . ' KB';
} else {
    $stats['log_size'] = 'N/A';
}

// Disk space (approximate)
$stats['disk_usage'] = round((filesize(DB_PATH) + filesize($logFile)) / 1024 / 1024, 2) . ' MB';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - GLS Tools</title>
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
                <li><a href="recogidas.php">Recogidas</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="repartidores.php">Repartidores</a></li>
                <li><a href="configuracion.php" class="active">Configuración</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Configuration Form -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>⚙️ Configuración General</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_config">
                            
                            <div class="form-group">
                                <label class="form-label">Nombre de la Empresa</label>
                                <input type="text" name="company_name" class="form-control"
                                       value="<?php echo htmlspecialchars($configs['company_name'] ?? 'GLS Tools'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Dirección de la Empresa</label>
                                <textarea name="company_address" class="form-control" rows="3"><?php echo htmlspecialchars($configs['company_address'] ?? 'Dirección de la oficina'); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Teléfono de la Empresa</label>
                                <input type="tel" name="company_phone" class="form-control"
                                       value="<?php echo htmlspecialchars($configs['company_phone'] ?? '123-456-789'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Franjas Horarias de Recogida</label>
                                <textarea name="pickup_time_slots" class="form-control" rows="4"><?php echo htmlspecialchars($configs['pickup_time_slots'] ?? '09:00-10:00,10:00-11:00,11:00-12:00,14:00-15:00,15:00-16:00,16:00-17:00'); ?></textarea>
                                <small class="text-muted">Separar con comas. Formato: HH:MM-HH:MM</small>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Database Maintenance -->
                <div class="card">
                    <div class="card-header">
                        <h3>🔧 Mantenimiento del Sistema</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>⚠️ Atención:</strong> Las operaciones de mantenimiento pueden afectar el rendimiento del sistema temporalmente.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Limpieza de Historial</h5>
                                <p>Elimina registros antiguos del historial de cambios de estado para optimizar la base de datos.</p>
                                <form method="POST" onsubmit="return confirm('¿Eliminar registros antiguos del historial?')">
                                    <input type="hidden" name="action" value="cleanup_logs">
                                    <button type="submit" class="btn btn-warning">
                                        🧹 Limpiar Historial Antiguo
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <h5>Backup de Base de Datos</h5>
                                <p>Descarga una copia de seguridad de la base de datos SQLite.</p>
                                <a href="../includes/backup_db.php" class="btn btn-info" target="_blank">
                                    💾 Descargar Backup
                                </a>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Registro de Actividad</h5>
                                <p>Ver el archivo de log completo del sistema.</p>
                                <a href="../Log.dm" class="btn btn-secondary" target="_blank">
                                    📄 Ver Log Completo
                                </a>
                            </div>
                            <div class="col-md-6">
                                <h5>Información Técnica</h5>
                                <p>Detalles técnicos del sistema y la base de datos.</p>
                                <button onclick="showTechInfo()" class="btn btn-info">
                                    🔍 Info Técnica
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Statistics -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3>📊 Estadísticas del Sistema</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Base de Datos:</strong><br>
                            <small>Tamaño: <?php echo $stats['db_size']; ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Registros:</strong><br>
                            <small>
                                • Clientes: <?php echo $stats['clients_count']; ?><br>
                                • Usuarios: <?php echo $stats['users_count']; ?><br>
                                • Recogidas: <?php echo $stats['pickups_count']; ?><br>
                                • Paquetes: <?php echo $stats['packages_count']; ?><br>
                                • Historial: <?php echo $stats['pickup_status_history_count']; ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Rango de Fechas:</strong><br>
                            <small>
                                Desde: <?php echo $stats['oldest_pickup']; ?><br>
                                Hasta: <?php echo $stats['newest_pickup']; ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Archivos:</strong><br>
                            <small>
                                Log: <?php echo $stats['log_size']; ?><br>
                                Total: <?php echo $stats['disk_usage']; ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Versión PHP:</strong><br>
                            <small><?php echo PHP_VERSION; ?></small>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Servidor Web:</strong><br>
                            <small><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>⚡ Acciones Rápidas</h3>
                    </div>
                    <div class="card-body">
                        <a href="dashboard.php" class="btn btn-primary btn-sm mb-2" style="width: 100%;">
                            📊 Dashboard
                        </a>
                        <a href="recogidas.php?status=pendiente_confirmar" class="btn btn-warning btn-sm mb-2" style="width: 100%;">
                            ⏳ Pendientes Confirmar
                        </a>
                        <a href="recogidas.php?date=today" class="btn btn-info btn-sm mb-2" style="width: 100%;">
                            📅 Recogidas Hoy
                        </a>
                        <a href="clientes.php" class="btn btn-success btn-sm mb-2" style="width: 100%;">
                            👥 Gestionar Clientes
                        </a>
                        <a href="repartidores.php" class="btn btn-secondary btn-sm" style="width: 100%;">
                            🚚 Gestionar Repartidores
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Technical Info Modal -->
    <div id="techModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 5% auto; padding: 20px; width: 90%; max-width: 800px; border-radius: 8px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4>Información Técnica del Sistema</h4>
                <button type="button" onclick="closeTechModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="techContent" style="font-family: monospace; font-size: 12px; white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 4px;">
                Cargando información técnica...
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function showTechInfo() {
            document.getElementById('techModal').style.display = 'block';
            
            const info = `
INFORMACIÓN TÉCNICA - GLS TOOLS PICKUP SYSTEM
============================================

Base de Datos:
- Archivo: <?php echo DB_PATH; ?>
- Tamaño: <?php echo $stats['db_size']; ?>
- Tipo: SQLite 3

Tablas y Registros:
- clients: <?php echo $stats['clients_count']; ?> registros
- users: <?php echo $stats['users_count']; ?> registros  
- pickups: <?php echo $stats['pickups_count']; ?> registros
- packages: <?php echo $stats['packages_count']; ?> registros
- pickup_status_history: <?php echo $stats['pickup_status_history_count']; ?> registros
- config: Configuración del sistema

Estados de Recogida:
<?php foreach (PICKUP_STATUSES as $key => $label): ?>
- <?php echo $key; ?>: <?php echo $label; ?>
<?php endforeach; ?>

Sistema:
- PHP: <?php echo PHP_VERSION; ?>
- Servidor: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?>
- Timezone: <?php echo date_default_timezone_get(); ?>
- Memoria PHP: <?php echo ini_get('memory_limit'); ?>
- Max Upload: <?php echo ini_get('upload_max_filesize'); ?>

Archivos del Sistema:
- Base de datos: <?php echo $stats['db_size']; ?>
- Log de actividad: <?php echo $stats['log_size']; ?>
- Uso total: <?php echo $stats['disk_usage']; ?>

Configuración Actual:
<?php foreach ($configs as $key => $value): ?>
- <?php echo $key; ?>: <?php echo $value; ?>
<?php endforeach; ?>

Información de Sesión:
- Usuario: <?php echo $_SESSION['username']; ?>
- Rol: <?php echo $_SESSION['role']; ?>
- ID de Sesión: <?php echo session_id(); ?>

Fecha/Hora Actual: <?php echo date('Y-m-d H:i:s'); ?>
            `;
            
            document.getElementById('techContent').textContent = info.trim();
        }
        
        function closeTechModal() {
            document.getElementById('techModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('techModal');
            if (event.target === modal) {
                closeTechModal();
            }
        }
    </script>
</body>
</html>