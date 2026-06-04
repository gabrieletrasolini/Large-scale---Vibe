<?php
session_start();
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

$username = $_SESSION['utente_loggato'];
$id_brano = $_POST['id_brano'];

$bulk = new MongoDB\Driver\BulkWrite;
$bulk->update(
    ['username' => $username],
    ['$pull' => ['preferiti.brani' => ['id_brano' => $id_brano]]]
);

$manager->executeBulkWrite('admin.User', $bulk);
header('Location: profile.php?success=brano_rimosso');
exit;
