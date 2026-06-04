<?php
session_start();

if (!isset($_SESSION['utente_loggato'])) {
    header('Location: login.php');
    exit;
}

$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
$username = $_SESSION['utente_loggato'];

$nome_playlist = $_POST['nome_playlist'] ?? '';

$bulk = new MongoDB\Driver\BulkWrite;
$bulk->update(
    ['username' => $username],
    ['$pull' => ['playlist_personali' => ['nome_playlist' => $nome_playlist]]]
);

$manager->executeBulkWrite('admin.User', $bulk);
header('Location: profile.php?success=playlist_rimossa');
exit;
?>
