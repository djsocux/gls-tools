<?php
require_once '../includes/config.php';
requireAuth(null, 'admin');

$db = getDB();
$success = '';
$error = '';

// Handle delivery user creation/editing
if ($_POST['action'] ?? '' === 'save_delivery') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $active = isset($_POST['active']) ? 1 : 0;
    
    try {
        if (empty($username) || empty($name)) {
            throw new Exception('Usuario y nombre son obligatorios');
        }
        
        if ($userId > 0) {
            // Update existing user
            $updateFields = ['username = ?', 'name = ?', 'email = ?', 'phone = ?', 'active = ?', 'updated_at = datetime("now")'];
            $updateValues = [$username, $name, $email, $phone, $active];
            
            if (!empty($password)) {
                $updateFields[] = 'password = ?';
                $updateValues[] = hashPassword($password);
            }
            
            $updateValues[] = $userId;
            
            $stmt = $db->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateValues);
            $success = "Repartidor actualizado correctamente.";
            logActivity('Repartidor Actualizado', "Repartidor {$name} actualizado por {$_SESSION['name']}", 'update');
        } else {
            // Create new user
            if (empty($password)) {
                throw new Exception('La contraseña es obligatoria para nuevos usuarios');
            }
            
            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('El nombre de usuario ya existe');
            }
            
            $stmt = $db->prepare("
                INSERT INTO users (username, password, role, name, email, phone, active)
                VALUES (?, ?, 'delivery', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username, 
                hashPassword($password), 
                $name, 
                $email, 
                $phone, 
                $active
            ]);
            $success = "Repartidor creado correctamente.";
            logActivity('Repartidor Creado', "Repartidor {$name} creado por {$_SESSION['name']}", 'creation');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get delivery users with statistics
$stmt = $db->query("
    SELECT u.*,
           COUNT(CASE WHEN p.status IN ('asignada', 'en_ruta') THEN 1 END) as active_pickups,
           COUNT(CASE WHEN p.status = 'hecho' AND DATE(p.completed_at) = DATE('now') THEN 1 END) as today_completed,
           COUNT(CASE WHEN p.status = 'hecho' THEN 1 END) as total_completed
    FROM users u
    LEFT JOIN pickups p ON u.id = p.assigned_to
    WHERE u.role = 'delivery'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$deliveryUsers = $stmt->fetchAll();

// Get user for editing
$editUser = null;
if ($editId = (int)($_GET['edit'] ?? 0)) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Repartidores - GLS Tools</title>
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
                <li><a href="repartidores.php" class="active">Repartidores</a></li>
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
        
        <!-- Delivery User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><?php echo $editUser ? 'Editar Repartidor' : 'Nuevo Repartidor'; ?></h3>
                <?php if ($editUser): ?>
                    <a href="repartidores.php" class="btn btn-secondary btn-sm float-right">Cancelar Edición</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_delivery">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Usuario *</label>
                                <input type="text" name="username" class="form-control" required
                                       value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>">
                                <small class="text-muted">Sin espacios ni caracteres especiales</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($editUser['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    Contraseña <?php echo $editUser ? '(Dejar vacío para mantener actual)' : '*'; ?>
                                </label>
                                <input type="password" name="password" class="form-control" 
                                       <?php echo !$editUser ? 'required' : ''; ?>>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" name="active" <?php echo (!$editUser || $editUser['active']) ? 'checked' : ''; ?>>
                                    Repartidor Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editUser ? 'Actualizar Repartidor' : 'Crear Repartidor'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Delivery Users Table -->
        <div class="card">
            <div class="card-header">
                <h3>Lista de Repartidores (<?php echo count($deliveryUsers); ?> total)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($deliveryUsers)): ?>
                    <div class="text-center p-4">
                        <h5>No hay repartidores registrados</h5>
                        <p>Crea el primer repartidor usando el formulario superior.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Activas</th>
                                    <th>Hoy</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveryUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['active_pickups'] > 0 ? 'warning' : 'secondary'; ?>">
                                            <?php echo $user['active_pickups']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo $user['today_completed']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $user['total_completed']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                            <a href="recogidas.php?assigned_to=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Ver Recogidas</a>
                                            <?php if ($user['active_pickups'] > 0): ?>
                                                <span class="btn btn-sm btn-secondary disabled">En Servicio</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo array_sum(array_column($deliveryUsers, 'active_pickups')); ?></h3>
                        <p>Recogidas Activas Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo array_sum(array_column($deliveryUsers, 'today_completed')); ?></h3>
                        <p>Completadas Hoy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo count(array_filter($deliveryUsers, function($u) { return $u['active']; })); ?></h3>
                        <p>Repartidores Activos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Validate username input
        document.querySelector('input[name="username"]')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        });
        
        // Password strength indicator
        document.querySelector('input[name="password"]')?.addEventListener('input', function(e) {
            const password = e.target.value;
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];
            const labels = ['Muy débil', 'Débil', 'Regular', 'Buena', 'Excelente'];
            
            if (password.length > 0) {
                if (!e.target.nextElementSibling?.classList.contains('password-strength')) {
                    const indicator = document.createElement('div');
                    indicator.className = 'password-strength mt-1';
                    e.target.parentNode.appendChild(indicator);
                }
                const indicator = e.target.parentNode.querySelector('.password-strength');
                indicator.innerHTML = `<small style="color: ${colors[strength - 1] || colors[0]}">Fortaleza: ${labels[strength - 1] || labels[0]}</small>`;
            }
        });
    </script>
</body>
</html>