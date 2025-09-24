<?php
require_once '../includes/config.php';

if (isset($_SESSION['client_name'])) {
    logActivity('Cliente Logout', "Cliente {$_SESSION['client_name']} ha cerrado sesión", 'authentication');
}

// Clear client session
session_unset();
session_destroy();

redirect('/cliente/login.php');
?>