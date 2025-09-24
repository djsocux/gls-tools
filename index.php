<?php
require_once 'includes/config.php';

// If already authenticated, redirect to appropriate dashboard
if (isAuthenticated('client')) {
    redirect('/cliente/dashboard.php');
} elseif (isAuthenticated()) {
    if ($_SESSION['role'] === 'admin') {
        redirect('/administrador/dashboard.php');
    } else {
        redirect('/repartidor/dashboard.php');
    }
}

// Default redirect to login
redirect('/login.php');
?>