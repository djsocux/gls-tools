<?php
require_once '../includes/config.php';
requireAuth(null, 'admin');

$db = getDB();
$success = '';
$error = '';

// Handle client creation/editing
if ($_POST['action'] ?? '' === 'save_client') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    
    try {
        if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($city) || empty($postalCode)) {
            throw new Exception('Todos los campos son obligatorios');
        }
        
        if ($clientId > 0) {
            // Update existing client
            $stmt = $db->prepare("
                UPDATE clients 
                SET name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ?, active = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $address, $city, $postalCode, $active, $clientId]);
            $success = "Cliente actualizado correctamente.";
            logActivity('Cliente Actualizado', "Cliente {$name} actualizado por {$_SESSION['name']}", 'update');
        } else {
            // Create new client
            $token = generateToken();
            $stmt = $db->prepare("
                INSERT INTO clients (token, name, email, phone, address, city, postal_code, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$token, $name, $email, $phone, $address, $city, $postalCode, $active]);
            $success = "Cliente creado correctamente. Token: " . $token;
            logActivity('Cliente Creado', "Cliente {$name} creado por {$_SESSION['name']}", 'creation');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle token regeneration
if ($_POST['action'] ?? '' === 'regenerate_token') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    try {
        $newToken = generateToken();
        $stmt = $db->prepare("UPDATE clients SET token = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$newToken, $clientId]);
        $success = "Token regenerado: " . $newToken;
        logActivity('Token Regenerado', "Token regenerado para cliente ID {$clientId}", 'update');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get clients
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT c.*, COUNT(p.id) as pickup_count
    FROM clients c
    LEFT JOIN pickups p ON c.id = p.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$clients = $stmt->fetchAll();

// Get total count
$stmt = $db->query("SELECT COUNT(*) FROM clients");
$totalClients = $stmt->fetchColumn();
$totalPages = ceil($totalClients / $perPage);

// Get client for editing
$editClient = null;
if ($editId = (int)($_GET['edit'] ?? 0)) {
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$editId]);
    $editClient = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - GLS Tools</title>
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
                <li><a href="clientes.php" class="active">Clientes</a></li>
                <li><a href="repartidores.php">Repartidores</a></li>
                <li><a href="configuracion.php">Configuración</a></li>
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
        
        <!-- Client Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><?php echo $editClient ? 'Editar Cliente' : 'Nuevo Cliente'; ?></h3>
                <?php if ($editClient): ?>
                    <a href="clientes.php" class="btn btn-secondary btn-sm float-right">Cancelar Edición</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_client">
                    <?php if ($editClient): ?>
                        <input type="hidden" name="client_id" value="<?php echo $editClient['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($editClient['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($editClient['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Teléfono *</label>
                                <input type="tel" name="phone" class="form-control" required
                                       value="<?php echo htmlspecialchars($editClient['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Dirección *</label>
                                <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($editClient['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ciudad *</label>
                                <input type="text" name="city" class="form-control" required
                                       value="<?php echo htmlspecialchars($editClient['city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Código Postal *</label>
                                <input type="text" name="postal_code" class="form-control" required pattern="\d{5}"
                                       value="<?php echo htmlspecialchars($editClient['postal_code'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="active" <?php echo (!$editClient || $editClient['active']) ? 'checked' : ''; ?>>
                                    Cliente Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editClient ? 'Actualizar Cliente' : 'Crear Cliente'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Clients Table -->
        <div class="card">
            <div class="card-header">
                <h3>Lista de Clientes (<?php echo $totalClients; ?> total)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Ciudad</th>
                                <th>Recogidas</th>
                                <th>Estado</th>
                                <th>Token</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?php echo $client['id']; ?></td>
                                <td><?php echo htmlspecialchars($client['name']); ?></td>
                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                <td><?php echo htmlspecialchars($client['city']); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $client['pickup_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $client['active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $client['active'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="font-family: monospace;">
                                        <?php echo substr($client['token'], 0, 8); ?>...
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?edit=<?php echo $client['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <button onclick="showToken(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['token']); ?>')" 
                                                class="btn btn-sm btn-info">Ver Token</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="regenerate_token">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" 
                                                    onclick="return confirm('¿Regenerar token? El anterior dejará de funcionar.')">
                                                Nuevo Token
                                            </button>
                                        </form>
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
                                <a href="?page=<?php echo $i; ?>" 
                                   class="btn btn-<?php echo $i === $page ? 'primary' : 'secondary'; ?> btn-sm">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Token Modal -->
    <div id="tokenModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background: white; margin: 15% auto; padding: 20px; width: 80%; max-width: 500px; border-radius: 8px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4>Token de Acceso</h4>
                <button type="button" onclick="closeTokenModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body">
                <p>Token de acceso para el cliente:</p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 16px; word-break: break-all; text-align: center; margin: 15px 0;" id="tokenDisplay"></div>
                <button onclick="copyToken()" class="btn btn-primary">Copiar Token</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function showToken(clientId, token) {
            document.getElementById('tokenDisplay').textContent = token;
            document.getElementById('tokenModal').style.display = 'block';
        }
        
        function closeTokenModal() {
            document.getElementById('tokenModal').style.display = 'none';
        }
        
        function copyToken() {
            const tokenText = document.getElementById('tokenDisplay').textContent;
            navigator.clipboard.writeText(tokenText).then(function() {
                alert('Token copiado al portapapeles');
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('tokenModal');
            if (event.target === modal) {
                closeTokenModal();
            }
        }
    </script>
</body>
</html>