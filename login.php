<?php
require_once 'includes/config.php';

// If already logged in, redirect based on role
if (isAuthenticated()) {
    if ($_SESSION['role'] === 'admin') {
        redirect('/administrador/dashboard.php');
    } else {
        redirect('/repartidor/dashboard.php');
    }
}

$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            logActivity('User Login', "Usuario {$user['username']} ({$user['role']}) ha iniciado sesión", 'authentication');
            
            if ($user['role'] === 'admin') {
                redirect('/administrador/dashboard.php');
            } else {
                redirect('/repartidor/dashboard.php');
            }
        } else {
            $error = 'Credenciales inválidas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Sistema - GLS Tools</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .access-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .access-links a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        .access-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>GLS Tools</h1>
                <p>Sistema de Gestión de Recogidas</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="Ingrese su usuario" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Ingrese su contraseña" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="access-links">
                <p><strong>Tipos de Acceso:</strong></p>
                <a href="/administrador/">👨‍💼 Panel Administrador</a> | 
                <a href="/repartidor/">🚚 Panel Repartidor</a> | 
                <a href="/cliente/">👤 Acceso Cliente</a>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>