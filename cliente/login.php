<?php
require_once '../includes/config.php';

// If already logged in, redirect to dashboard
if (isAuthenticated('client')) {
    redirect('/cliente/dashboard.php');
}

$error = '';

if ($_POST) {
    $token = trim($_POST['token'] ?? '');
    
    if (empty($token)) {
        $error = 'Por favor, ingrese su token de acceso.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM clients WHERE token = ? AND active = 1");
        $stmt->execute([$token]);
        $client = $stmt->fetch();
        
        if ($client) {
            $_SESSION['client_id'] = $client['id'];
            $_SESSION['client_name'] = $client['name'];
            $_SESSION['client_token'] = $client['token'];
            
            logActivity('Cliente Login', "Cliente {$client['name']} ha iniciado sesión", 'authentication');
            redirect('/cliente/dashboard.php');
        } else {
            $error = 'Token de acceso inválido o cliente inactivo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Cliente - GLS Tools</title>
    <link rel="stylesheet" href="../assets/css/main.css">
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
        .token-input {
            font-family: monospace;
            letter-spacing: 2px;
            text-align: center;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Acceso Cliente</h1>
                <p>Ingrese su token único para acceder al sistema</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Token de Acceso</label>
                    <input type="text" name="token" class="form-control token-input" 
                           placeholder="Ingrese su token" required maxlength="64">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    Acceder
                </button>
            </form>
            
            <div class="text-center mt-4">
                <p><small>¿No tiene token? Contacte con su administrador</small></p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>