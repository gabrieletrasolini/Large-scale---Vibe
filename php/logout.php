<?php
session_start(); // Avvia la sessione

// Elimina tutte le variabili di sessione
$_SESSION = [];

// Distrugge la sessione
session_destroy();

// Reindirizza alla pagina di login
header("Location: index.php");
exit;
?>
