<?php
require_once 'includes/config.php';

if (isset($_SESSION['name'])) {
    logActivity('User Logout', "Usuario {$_SESSION['name']} ({$_SESSION['role']}) ha cerrado sesión", 'authentication');
}

// Clear session
session_unset();
session_destroy();

redirect('/login.php');
?>