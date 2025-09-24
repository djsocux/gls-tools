<?php
require_once 'config.php';

$pickupId = (int)($_GET['pickup_id'] ?? 0);
$packageId = (int)($_GET['package_id'] ?? 0);

if (!$pickupId) {
    die('ID de recogida requerido');
}

$db = getDB();

// Get pickup info
$stmt = $db->prepare("
    SELECT p.*, c.name as client_name, c.address as client_address, c.city as client_city, c.postal_code as client_postal_code, c.phone as client_phone
    FROM pickups p
    JOIN clients c ON p.client_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pickupId]);
$pickup = $stmt->fetch();

if (!$pickup) {
    die('Recogida no encontrada');
}

// Check permissions
if (isAuthenticated('client')) {
    if ($pickup['client_id'] != $_SESSION['client_id']) {
        die('No tienes permisos para ver esta recogida');
    }
}

// Get packages
$packageWhere = $packageId ? 'AND pkg.id = ?' : '';
$packageValues = $packageId ? [$pickupId, $packageId] : [$pickupId];

$stmt = $db->prepare("
    SELECT * FROM packages pkg
    WHERE pkg.pickup_id = ? {$packageWhere}
    ORDER BY pkg.id
");
$stmt->execute($packageValues);
$packages = $stmt->fetchAll();

if (empty($packages)) {
    die('No se encontraron paquetes');
}

// Generate barcodes if needed
foreach ($packages as &$package) {
    if (!$package['barcode_pickup']) {
        $barcode = 'PU' . str_pad($pickupId, 6, '0', STR_PAD_LEFT) . '-' . generateToken(4);
        $stmt = $db->prepare("UPDATE packages SET barcode_pickup = ? WHERE id = ?");
        $stmt->execute([$barcode, $package['id']]);
        $package['barcode_pickup'] = $barcode;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas de Envío - Recogida #<?php echo $pickupId; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            .label-container, .label-container * { visibility: visible; }
            .label { page-break-after: always; }
            .label:last-child { page-break-after: avoid; }
            .no-print { display: none !important; }
        }
        
        .label {
            border: 2px solid #000;
            padding: 20px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 400px;
            background: white;
        }
        
        .label h3 {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .label-section {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
        }
        
        .label-section h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .barcode-container {
            text-align: center;
            margin: 15px 0;
        }
        
        .pickup-info {
            font-size: 12px;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="no-print">
            <div class="card">
                <div class="card-header">
                    <h2>Etiquetas de Envío - Recogida #<?php echo $pickupId; ?></h2>
                </div>
                <div class="card-body">
                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pickup['client_name']); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge badge-<?php echo str_replace('_', '-', $pickup['status']); ?>">
                            <?php echo PICKUP_STATUSES[$pickup['status']]; ?>
                        </span>
                    </p>
                    <p><strong>Paquetes:</strong> <?php echo count($packages); ?></p>
                    
                    <div class="text-center mt-3">
                        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Etiquetas</button>
                        <button onclick="window.close()" class="btn btn-secondary">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="label-container">
            <?php foreach ($packages as $index => $package): ?>
                <div class="label">
                    <h3>GLS TOOLS - ETIQUETA DE ENVÍO</h3>
                    
                    <div class="label-section">
                        <h4>📤 Remitente:</h4>
                        <strong><?php echo htmlspecialchars($pickup['client_name']); ?></strong><br>
                        <?php echo htmlspecialchars($pickup['client_address']); ?><br>
                        <?php echo htmlspecialchars($pickup['client_city']); ?> - <?php echo htmlspecialchars($pickup['client_postal_code']); ?><br>
                        Tel: <?php echo htmlspecialchars($pickup['client_phone']); ?>
                    </div>
                    
                    <div class="label-section">
                        <h4>📥 Destinatario:</h4>
                        <strong><?php echo htmlspecialchars($package['recipient_name']); ?></strong><br>
                        <?php echo htmlspecialchars($package['recipient_address']); ?><br>
                        <?php echo htmlspecialchars($package['recipient_city']); ?> - <?php echo htmlspecialchars($package['recipient_postal_code']); ?><br>
                        Tel: <?php echo htmlspecialchars($package['recipient_phone']); ?>
                    </div>
                    
                    <?php if ($package['tracking_number']): ?>
                        <div class="label-section">
                            <h4>📋 Número GLS:</h4>
                            <div class="barcode-container">
                                <svg id="gls-barcode-<?php echo $index; ?>"></svg>
                                <div style="font-weight: bold; margin-top: 5px;">
                                    <?php echo htmlspecialchars($package['tracking_number']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="label-section">
                        <h4>🏷️ Código Recogida:</h4>
                        <div class="barcode-container">
                            <svg id="pickup-barcode-<?php echo $index; ?>"></svg>
                            <div style="font-weight: bold; margin-top: 5px;">
                                <?php echo htmlspecialchars($package['barcode_pickup']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pickup-info">
                        <strong>Recogida #<?php echo $pickupId; ?></strong> | 
                        Paquete <?php echo $index + 1; ?> de <?php echo count($packages); ?><br>
                        <?php if ($package['weight']): ?>Peso: <?php echo $package['weight']; ?>kg | <?php endif; ?>
                        <?php if ($package['dimensions']): ?>Dim: <?php echo htmlspecialchars($package['dimensions']); ?> | <?php endif; ?>
                        Bultos: <?php echo $package['quantity']; ?><br>
                        Fecha: <?php echo date('d/m/Y H:i'); ?>
                        <?php if ($package['observations']): ?>
                            <br><small><strong>Obs:</strong> <?php echo htmlspecialchars($package['observations']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Generate barcodes
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($packages as $index => $package): ?>
                // Pickup barcode
                JsBarcode("#pickup-barcode-<?php echo $index; ?>", "<?php echo $package['barcode_pickup']; ?>", {
                    format: "CODE128",
                    width: 2,
                    height: 50,
                    displayValue: false
                });
                
                <?php if ($package['tracking_number']): ?>
                // GLS tracking barcode
                JsBarcode("#gls-barcode-<?php echo $index; ?>", "<?php echo $package['tracking_number']; ?>", {
                    format: "CODE128",
                    width: 2,
                    height: 50,
                    displayValue: false
                });
                <?php endif; ?>
            <?php endforeach; ?>
        });
    </script>
</body>
</html>