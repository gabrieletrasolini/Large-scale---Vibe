<?php
session_start();
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['utente_loggato'];
    $nome_playlist = trim($_POST['nome_playlist'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');

    if (empty($nome_playlist)) {
        header("Location: profile.php?success=errore_nome_vuoto");
        exit;
    }

    // 1. Recupera l'utente
    $filter = ['username' => $username];
    $query = new MongoDB\Driver\Query($filter);
    $cursor = $manager->executeQuery('admin.User', $query);
    $utente = current($cursor->toArray());

    if (!$utente) {
        die("Utente non trovato");
    }

    // 2. Controlla se esiste già una playlist con lo stesso nome
    foreach ($utente->playlist_personali ?? [] as $playlist) {
        if (strtolower($playlist->nome_playlist) === strtolower($nome_playlist)) {
            // Playlist già esistente con lo stesso nome
            header("Location: profile.php?success=playlist_duplicata");
            exit;
        }
    }

    // 3. Aggiungi la nuova playlist
    $bulk = new MongoDB\Driver\BulkWrite;
    $bulk->update(
        ['username' => $username],
        ['$push' => [
            'playlist_personali' => [
                'nome_playlist' => $nome_playlist,
                'descrizione' => $descrizione,
                'brani' => []
            ]
        ]]
    );

    $manager->executeBulkWrite('admin.User', $bulk);

    header("Location: profile.php?success=playlist_creata");
    exit;
}
?>
