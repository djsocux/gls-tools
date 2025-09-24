<?php
// This file uses the same logic as the client version but with delivery layout
// We'll just redirect to the client version since it handles permissions correctly
$pickupId = (int)($_GET['id'] ?? 0);
header("Location: ../cliente/recogida_detalle.php?id=" . $pickupId);
exit;
?>