<?php
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

echo "<h1>Connessione a MongoDB riuscita!</h1>";

$query = new MongoDB\Driver\Query([]);
$cursor = $manager->executeQuery('admin.Spotify2023', $query);

echo "<h2>Dati:</h2>";
foreach ($cursor as $document) {
    echo "<pre>";
    var_dump($document);
    echo "</pre>";
}
?>
