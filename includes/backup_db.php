<?php
require_once 'config.php';
requireAuth(null, 'admin');

$backupFile = 'gls_backup_' . date('Y-m-d_H-i-s') . '.db';

if (file_exists(DB_PATH)) {
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize(DB_PATH));
    
    // Output file contents
    readfile(DB_PATH);
    
    // Log the backup
    logActivity('Backup BD', "Backup de base de datos descargado por {$_SESSION['name']}", 'backup');
    
    exit;
} else {
    die('Error: No se encontró la base de datos');
}
?>