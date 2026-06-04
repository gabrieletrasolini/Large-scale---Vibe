<?php
session_start();
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

$username = $_SESSION['utente_loggato'];
$id_brano = $_POST['id_brano'];
$nome_playlist = $_POST['nome_playlist'];

$bulk = new MongoDB\Driver\BulkWrite;
$bulk->update(
    [
        'username' => $username,
        'playlist_personali.nome_playlist' => $nome_playlist
    ],
    [
        '$pull' => [
            'playlist_personali.$.brani' => ['id_brano' => $id_brano]
        ]
    ]
);

$manager->executeBulkWrite('admin.User', $bulk);
header('Location: profile.php?success=brano_rimosso');
exit;
