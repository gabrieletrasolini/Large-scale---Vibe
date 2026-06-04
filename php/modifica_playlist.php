<?php
session_start();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
error_log("Input json_decode: " . print_r($data, true));

// Controllo dati richiesti
if (!$data || !isset($data['nome_playlist']) || !isset($data['song_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit();
}

$nome_playlist = $data['nome_playlist'];
$song_id = $data['song_id'];
$action = $data['action'];

$username = $_SESSION['utente_loggato'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Utente non loggato']);
    exit();
}

$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
$bulk = new MongoDB\Driver\BulkWrite;

// Aggiunta o rimozione del brano
if ($action === 'add') {
    $bulk->update(
        [
            'username' => $username,
            'playlist_personali.nome_playlist' => $nome_playlist
        ],
        [
            '$push' => ['playlist_personali.$.brani' => ['id_brano' => $song_id]]
        ]
    );
} elseif ($action === 'remove') {
    $bulk->update(
        [
            'username' => $username,
            'playlist_personali.nome_playlist' => $nome_playlist
        ],
        [
            '$pull' => ['playlist_personali.$.brani' => ['id_brano' => $song_id]]
        ]
    );
} else {
    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    exit();
}

// Esegui l'update su MongoDB
$manager->executeBulkWrite('admin.User', $bulk);

// Risposta finale
echo json_encode(['success' => true, 'message' => "Track {$action} with success"]);
exit();
?>
