<?php 
use MongoDB\BSON\ObjectId;
session_start();
if (!isset($_SESSION['utente_loggato'])) {
    header('Location: login.php');
    exit();
}
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
include 'header.php';
// ============================================================
// === Trend 1: Top 10 Generi per Popolarità Media (Artists) ===
// ============================================================
$pipeline1 = [
    ['$match' => ['Main_Genre' => ['$ne' => null], 'Popularity' => ['$ne' => null]]],
    [
        '$group' => [
            '_id' => '$Main_Genre',
            'avg_popularity' => ['$avg' => '$Popularity'],
            'avg_followers' => ['$avg' => '$Followers'],
            'count' => ['$sum' => 1]
        ]
    ],
    ['$match' => ['count' => ['$gte' => 3]]],
    ['$sort' => ['avg_popularity' => -1]],
    ['$limit' => 12]
];

$command1 = new MongoDB\Driver\Command([
    'aggregate' => 'Artists',
    'pipeline' => $pipeline1,
    'cursor' => new stdClass,
    'allowDiskUse' => true
]);

$cursor1 = $manager->executeCommand('admin', $command1);
$genrePopData = $cursor1->toArray();

$genreLabels = [];
$genreAvgPop = [];
$genreAvgFollowers = [];

foreach ($genrePopData as $entry) {
    $genreLabels[] = $entry->_id;
    $genreAvgPop[] = round($entry->avg_popularity, 1);
    $genreAvgFollowers[] = round(($entry->avg_followers ?? 0) / 1000, 1); // in migliaia
}

echo "<script>
    const genreLabels = " . json_encode($genreLabels) . ";
    const genreAvgPop = " . json_encode($genreAvgPop) . ";
    const genreAvgFollowers = " . json_encode($genreAvgFollowers) . ";
</script>";

// ============================================================
// === Trend 2: Distribuzione Popularity Index per Genere (Songs) ===
// ============================================================
$pipeline2 = [
    ['$match' => ['Genre' => ['$ne' => null], 'Popularity_Index' => ['$ne' => null]]],
    [
        '$bucket' => [
            'groupBy' => '$Popularity_Index',
            'boundaries' => [0, 20, 40, 60, 80, 100],
            'default' => 'Other',
            'output' => [
                'count' => ['$sum' => 1],
                'avg_popularity' => ['$avg' => '$Popularity_Index']
            ]
        ]
    ]
];

$command2 = new MongoDB\Driver\Command([
    'aggregate' => 'Songs',
    'pipeline' => $pipeline2,
    'cursor' => new stdClass,
]);

$cursor2 = $manager->executeCommand('admin', $command2);
$popDistData = $cursor2->toArray();

$popBucketLabels = [];
$popBucketCounts = [];

foreach ($popDistData as $entry) {
    if ($entry->_id === 'Other') {
        $popBucketLabels[] = 'Other';
    } else {
        $from = (int)$entry->_id;
        $to = $from + 19;
        $popBucketLabels[] = $from . '-' . $to;
    }
    $popBucketCounts[] = $entry->count;
}

echo "<script>
    const popBucketLabels = " . json_encode($popBucketLabels) . ";
    const popBucketCounts = " . json_encode($popBucketCounts) . ";
</script>";

// ============================================================
// === Trend 3: Profilo Audio Medio per Genere (Songs) ===
// ============================================================
$pipeline3 = [
    ['$match' => ['Genre' => ['$ne' => null]]],
    ['$addFields' => ['Genre' => ['$toLower' => '$Genre']]],
    ['$addFields' => [
        'Genre' => ['$switch' => [
            'branches' => [
                ['case' => ['$in' => ['$Genre', ['hiphop', 'hip-hop']]], 'then' => 'hip-hop'],
                ['case' => ['$in' => ['$Genre', ['alt', 'alt_music', 'alt-rock', 'alternative']]], 'then' => 'alternative'],
                ['case' => ['$in' => ['$Genre', ['metal', 'heavy-metal', 'black-metal', 'death-metal', 'metalcore']]], 'then' => 'metal'],
                ['case' => ['$in' => ['$Genre', ['rock', 'hard-rock', 'rock-n-roll', 'rockabilly', 'psych-rock', 'punk-rock', 'punk', 'j-rock']]], 'then' => 'rock'],
                ['case' => ['$in' => ['$Genre', ['latin', 'latino', 'reggaeton', 'reggae']]], 'then' => 'latin'],
                ['case' => ['$in' => ['$Genre', ['indie', 'indie-pop']]], 'then' => 'indie'],
                ['case' => ['$eq' => ['$Genre', 'unknown']], 'then' => null],
                // Kids
                ['case' => ['$in' => ['$Genre', ['kids', 'children']]], 'then' => 'kids'],
                // J-Pop
                ['case' => ['$in' => ['$Genre', ['j-pop', 'j-dance', 'j-idol']]], 'then' => 'j-pop'],
                // Brazil
                ['case' => ['$in' => ['$Genre', ['brazil', 'samba']]], 'then' => 'brazil'],
                // C-Pop
                ['case' => ['$in' => ['$Genre', ['cantopop', 'mandopop']]], 'then' => 'c-pop'],
            ],
            'default' => '$Genre'
        ]]
    ]],
    ['$match' => ['Genre' => ['$ne' => null]]],
    [
        '$group' => [
            '_id' => '$Genre',
            'avg_danceability' => ['$avg' => '$Technical_Metrics.danceability'],
            'avg_energy'        => ['$avg' => '$Technical_Metrics.energy'],
            'avg_valence'       => ['$avg' => '$Technical_Metrics.valence'],
            'avg_tempo'         => ['$avg' => '$Technical_Metrics.tempo'],
            'count'             => ['$sum' => 1]
        ]
    ],
    ['$match' => ['count' => ['$gte' => 5]]],
    ['$sort' => ['avg_danceability' => -1]],
    ['$limit' => 12]
];

$command3 = new MongoDB\Driver\Command([
    'aggregate' => 'Songs',
    'pipeline' => $pipeline3,
    'cursor' => new stdClass,
]);

$cursor3 = $manager->executeCommand('admin', $command3);
$audioProfileData = $cursor3->toArray();

$audioGenreLabels = [];
$audioDanceability = [];
$audioEnergy = [];
$audioValence = [];

foreach ($audioProfileData as $entry) {
    $audioGenreLabels[] = $entry->_id;
    $audioDanceability[] = round($entry->avg_danceability ?? 0, 3);
    $audioEnergy[]       = round($entry->avg_energy ?? 0, 3);
    $audioValence[]      = round($entry->avg_valence ?? 0, 3);
}

echo "<script>
    const audioGenreLabels = " . json_encode($audioGenreLabels) . ";
    const audioDanceability = " . json_encode($audioDanceability) . ";
    const audioEnergy = " . json_encode($audioEnergy) . ";
    const audioValence = " . json_encode($audioValence) . ";
</script>";

// ============================================================
// === Trend 4: Songs + Artists — Top 10 artisti per avg energy
//     (Songs), con followers e popularity da Artists via regex
// ============================================================

// Step A: top 10 artisti per avg energy da Songs (min 3 tracce)
$pipeline4a = [
    ['$match' => ['Artist_Name' => ['$ne' => null], 'Technical_Metrics.energy' => ['$ne' => null]]],
    [
        '$group' => [
            '_id' => '$Artist_Name',
            'avg_energy'      => ['$avg' => '$Technical_Metrics.energy'],
            'avg_song_pop'    => ['$avg' => '$Popularity_Index'],
            'count'           => ['$sum' => 1]
        ]
    ],
    ['$match' => ['count' => ['$gte' => 10]]],
    ['$sort'  => ['avg_energy' => -1]],
    // Prendiamo 20 invece di 10 come buffer: il $lookup + $match successivi
    // escludono gli artisti non trovati in Artists, quindi potrebbero restarne
    // meno di 10. Con 20 candidati abbiamo margine sufficiente.
    ['$limit' => 20],
    [
        '$lookup' => [
            'from'         => 'Artists',
            'localField'   => '_id',
            'foreignField' => 'Name',
            'as'           => 'artist_match'
        ]
    ],
    // Tieni solo gli artisti che hanno un corrispondente in Artists
    ['$match' => ['artist_match' => ['$ne' => []]]],
    ['$limit' => 10]
];

$command4a = new MongoDB\Driver\Command([
    'aggregate' => 'Songs',
    'pipeline'  => $pipeline4a,
    'cursor'    => new stdClass,
]);
$cursor4a       = $manager->executeCommand('admin', $command4a);
$topEnergyArtists = $cursor4a->toArray();

// Step B: per ogni artista cerca in Artists con regex case-insensitive
$crossLabels    = [];
$crossEnergy    = [];   // 0-100 scala
$crossFollowers = [];   // in migliaia, null se non trovato
$crossArtistPop = [];   // 0-100, null se non trovato

foreach ($topEnergyArtists as $row) {
    $name = $row->_id;
    $crossLabels[]  = $name;
    $crossEnergy[]  = round($row->avg_energy * 100, 1);

    // Cerca in Artists con regex case-insensitive sul campo Name
    $safeRegex = preg_quote($name, '/');
    $artistQuery = new MongoDB\Driver\Query(
        ['Name' => ['$regex' => '^' . $safeRegex . '$', '$options' => 'i']],
        ['limit' => 1]
    );
    $artistCursor = $manager->executeQuery('admin.Artists', $artistQuery);
    $artistDoc    = current($artistCursor->toArray());

    if ($artistDoc) {
        $crossFollowers[] = $artistDoc->Followers !== null
            ? round($artistDoc->Followers / 1000, 1)
            : null;
        $crossArtistPop[] = $artistDoc->Popularity !== null
            ? round($artistDoc->Popularity, 1)
            : null;
    } else {
        $crossFollowers[] = null;
        $crossArtistPop[] = null;
    }
}

echo "<script>
    const crossLabels    = " . json_encode($crossLabels)    . ";
    const crossEnergy    = " . json_encode($crossEnergy)    . ";
    const crossFollowers = " . json_encode($crossFollowers) . ";
    const crossArtistPop = " . json_encode($crossArtistPop) . ";
</script>";

// ============================================================
// === Trend 5 (ORIGINALE): Top 20 Most Liked Tracks by Users ===
// ============================================================
$pipeline5 = [
    ['$project' => ['preferiti.brani.id_brano' => 1]],
    ['$unwind' => '$preferiti.brani'],
    [
        '$group' => [
            '_id' => '$preferiti.brani.id_brano',
            'count' => ['$sum' => 1]
        ]
    ],
    ['$sort' => ['count' => -1]],
    ['$limit' => 20]
];

$command5 = new MongoDB\Driver\Command([
    'aggregate' => 'User',
    'pipeline' => $pipeline5,
    'cursor' => new stdClass,
]);

$cursor5 = $manager->executeCommand('admin', $command5);

$braniPreferiti = [];

foreach ($cursor5 as $doc) {
    $trackId = $doc->_id;  // ora è già una stringa Track_ID tipo "4DzKhecDdMzj5IzjDEK4xP"
    $count = $doc->count;

    // Cerca direttamente per Track_ID, niente più ObjectId
    $querySpotify = new MongoDB\Driver\Query(['Track_ID' => $trackId]);
    $cursorSpotify = $manager->executeQuery('admin.Songs', $querySpotify);
    $spotifyResult = current($cursorSpotify->toArray());

    if ($spotifyResult) {
        $braniPreferiti[] = [
            'titolo'     => $spotifyResult->Song_Title ?? 'Titolo sconosciuto',
            'artista'    => $spotifyResult->Artist_Name ?? 'Artista sconosciuto',
            'preferenze' => $count
        ];
    }
}

// ============================================================
// === Trend 6: Speechiness vs Danceability per fascia (Songs) ===
// ============================================================
$pipeline6 = [
    [
        '$match' => [
            'Technical_Metrics.speechiness' => ['$ne' => null],
            'Technical_Metrics.danceability' => ['$ne' => null]
        ]
    ],
    [
        '$bucket' => [
            'groupBy' => '$Technical_Metrics.speechiness',
            'boundaries' => [0, 0.1, 0.33, 0.66, 1.01],
            'default' => 'Other',
            'output' => [
                'count' => ['$sum' => 1],
                'avg_danceability' => ['$avg' => '$Technical_Metrics.danceability'],
                'avg_energy'       => ['$avg' => '$Technical_Metrics.energy']
            ]
        ]
    ]
];

$command6 = new MongoDB\Driver\Command([
    'aggregate' => 'Songs',
    'pipeline' => $pipeline6,
    'cursor' => new stdClass,
]);

$cursor6 = $manager->executeCommand('admin', $command6);
$speechData = $cursor6->toArray();

$speechLabels = ['Low (0–0.1)', 'Medium-Low (0.1–0.33)', 'Medium-High (0.33–0.66)', 'High (0.66–1)'];
$speechCounts = [];
$speechDanceability = [];
$speechEnergy = [];

$speechMap = [];
foreach ($speechData as $entry) {
    if ($entry->_id !== 'Other') {
        // BSON restituisce 0 come int, gli altri come float — normalizziamo la chiave
        $rawId = $entry->_id;
        if (is_int($rawId) || $rawId == 0) {
            $key = '0';
        } else {
            $key = rtrim(rtrim(number_format((float)$rawId, 2, '.', ''), '0'), '.');
        }
        $speechMap[$key] = $entry;
    }
}

$boundaries = ['0', '0.1', '0.33', '0.66'];
foreach ($boundaries as $b) {
    $entry = $speechMap[$b] ?? null;
    $speechCounts[]      = $entry ? $entry->count : 0;
    $speechDanceability[] = $entry ? round($entry->avg_danceability, 3) : 0;
    $speechEnergy[]      = $entry ? round($entry->avg_energy, 3) : 0;
}

echo "<script>
    const speechLabels = " . json_encode($speechLabels) . ";
    const speechCounts = " . json_encode($speechCounts) . ";
    const speechDanceability = " . json_encode($speechDanceability) . ";
    const speechEnergy = " . json_encode($speechEnergy) . ";
</script>";

// ============================================================
// === Trend 7: Top 15 Generi per Nr. Canzoni e Popularity Media (Songs) ===
// ============================================================
$pipeline7 = [
    ['$match' => ['Genre' => ['$ne' => null]]],
    ['$unwind' => [
        'path' => '$Genre',
        'preserveNullAndEmptyArrays' => false
    ]],
    // toLower PRIMA dello switch, così tutti i confronti sono case-insensitive
    ['$addFields' => ['Genre' => ['$toLower' => '$Genre']]],
    ['$addFields' => [
        'Genre' => ['$switch' => [
            'branches' => [
                ['case' => ['$in' => ['$Genre', ['hiphop', 'hip-hop']]], 'then' => 'hip-hop'],
                ['case' => ['$in' => ['$Genre', ['alt', 'alt_music', 'alt-rock', 'alternative']]], 'then' => 'alternative'],
                ['case' => ['$in' => ['$Genre', ['metal', 'heavy-metal', 'black-metal', 'death-metal', 'metalcore']]], 'then' => 'metal'],
                ['case' => ['$in' => ['$Genre', ['rock', 'hard-rock', 'rock-n-roll', 'rockabilly', 'psych-rock', 'punk-rock', 'punk', 'j-rock']]], 'then' => 'rock'],
                ['case' => ['$in' => ['$Genre', ['latin', 'latino', 'reggaeton', 'reggae']]], 'then' => 'latin'],
                ['case' => ['$in' => ['$Genre', ['indie', 'indie-pop']]], 'then' => 'indie'],
                ['case' => ['$eq' => ['$Genre', 'unknown']], 'then' => null],
                // Kids
                ['case' => ['$in' => ['$Genre', ['kids', 'children']]], 'then' => 'kids'],
                // J-Pop
                ['case' => ['$in' => ['$Genre', ['j-pop', 'j-dance', 'j-idol']]], 'then' => 'j-pop'],
                // Brazil
                ['case' => ['$in' => ['$Genre', ['brazil', 'samba']]], 'then' => 'brazil'],
                // C-Pop
                ['case' => ['$in' => ['$Genre', ['cantopop', 'mandopop']]], 'then' => 'c-pop'],
            ],
            'default' => '$Genre'
        ]]
    ]],
    ['$match' => ['Genre' => ['$ne' => null]]],
    [
        '$group' => [
            '_id' => '$Genre',
            'song_count' => ['$sum' => 1],
            'avg_pop'    => ['$avg' => '$Popularity_Index']
        ]
    ],
    ['$sort' => ['song_count' => -1]],
    ['$limit' => 15]
];

$command7 = new MongoDB\Driver\Command([
    'aggregate' => 'Songs',
    'pipeline' => $pipeline7,
    'cursor' => new stdClass,
]);

$cursor7 = $manager->executeCommand('admin', $command7);
$genreCountData = $cursor7->toArray();

$genreCountLabels = [];
$genreSongCounts  = [];
$genreAvgPopIdx   = [];

foreach ($genreCountData as $entry) {
    $genreCountLabels[] = $entry->_id;
    $genreSongCounts[]  = $entry->song_count;
    $genreAvgPopIdx[]   = round($entry->avg_pop, 1);
}

echo "<script>
    const genreCountLabels = " . json_encode($genreCountLabels) . ";
    const genreSongCounts = " . json_encode($genreSongCounts) . ";
    const genreAvgPopIdx = " . json_encode($genreAvgPopIdx) . ";
</script>";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <style>
    .track-rank {
        font-weight: bold;
        margin-right: 8px;
        color: #D4A017;
    }
    body::-webkit-scrollbar {
        display: none;
    }
    html, body {
        margin: 0;
        padding: 0;
        scroll-behavior: smooth;
    }

    .navbar {
        position: fixed;
        top: 0;
        width: 100%;
        background-color: #222;
        z-index: 1000;
        display: flex;
        justify-content: center;
        gap: 10px;
        padding: 10px 0;
        flex-wrap: wrap;
    }

    .navbar a {
        color: white;
        text-decoration: none;
        padding: 8px 14px;
        border-radius: 4px;
        transition: background-color 0.3s;
    }

    .navbar a:hover {
        background-color: #444;
        color: white;
    }

    .fullpage-section {
        height: 100vh;
        display: flex;
        align-items: center;
        font-size: 3em;
        color: white;
        scroll-snap-align: start;
        padding-top: 60px;
        flex-direction: column;
        justify-content: space-between;
        padding: 80px 20px 40px;
        text-align: center;
        box-sizing: border-box;
        overflow-y: auto;
    }

    .fullpage-section::-webkit-scrollbar {
        width: 8px;
    }

    .fullpage-section::-webkit-scrollbar-thumb {
        background-color: rgba(255,255,255,0.4);
        border-radius: 4px;
    }

    #section1 { background-color: #1abc9c; }
    #section2 { background-color: #2ecc71; }
    #section3 { background-color: #3498db; }
    #section4 { background-color: #9b59b6; }
    #section5 { background-color: #34495e; }
    #section6 { background-color: #f1c40f; color: black; }
    #section7 { background-color: #e67e22; }

    .back-to-top a {
        display: inline-block;
        padding: 6px 12px;
        font-size: 14px;
        background-color: #333;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .back-to-top a:hover {
        background-color: #444;
        color: white;
        transform: scale(1.05);
    }

    .fullpage-section h1 {
        align-self: flex-start;
        text-align: left;
        margin: 0;
        padding-bottom: 20px;
        font-size: 60px;
        color: white;
        margin-bottom: 40px;
    }

    #section6 h1 { color: #222; }

    .back-to-top {
        align-self: center;
        margin-top: auto;
    }

    .top-artists-list {
        list-style-type: decimal;
        padding-left: 1.5em;
        margin: 0 auto 40px auto;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
        color: white;
        font-size: 25px;
        text-align: left;
        overflow-wrap: break-word;
    }

    .top-artists-list li {
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        gap: 1em;
        flex-wrap: wrap;
        width: 100%;
    }

    .artist-name {
        font-weight: 600;
        max-width: 70%;
        word-break: break-word;
        flex: 1 1 auto;
    }

    .track-count {
        font-style: italic;
        color: #ddd;
        flex-shrink: 0;
    }

    .track-artist {
        font-family: 'Courier New', monospace;
        font-style: normal;
        font-weight: 400;
        margin-left: 0;
    }

    .count {
        font-style: italic;
        color: #aaa;
        flex-shrink: 0;
    }

    canvas {
    width: 100% !important;
    max-height: 48vh !important;
    }

    .fullpage-section h3 {
        font-size: 15px;
        line-height: 1.6;
        max-width: 900px;
        margin: 0 auto;
    }

    .fullpage-section h1 {
        font-size: 40px;
        margin-bottom: 10px;
    }

    .fullpage-section h2.trend-description {
        font-size: 17px;
        margin-bottom: 8px;
    }
    .fullpage-section h2.trend-description,
    .fullpage-section h3 {
        white-space: normal;
        overflow-wrap: break-word;
        word-break: normal;
    }
    </style>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <title>Rock</title>
    <meta name="keywords" content="">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="icon" href="images/fevicon.png" type="image/gif" />
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css" media="screen">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

    <!-- loader -->
    <div class="loader_bg">
        <div class="loader"><img src="images/loading.gif" alt="#" /></div>
    </div>
    <!-- end loader -->


    <!-- Navbar fissa -->
    <div class="navbar">
        <a href="#section1">Genre Popularity</a>
        <a href="#section2">Popularity Distribution</a>
        <a href="#section3">Audio Profile by Genre</a>
        <a href="#section4">Energy vs Fanbase</a>
        <a href="#section5">Top 20 Favorites Tracks</a>
        <a href="#section6">Speechiness vs Danceability</a>
        <a href="#section7">Top Genres by Volume</a>
    </div>

    <!-- ===================== SECTION 1 ===================== -->
    <div class="fullpage-section" id="section1">
        <h1>🎸 Genre Popularity & Followers</h1>
        <h2 class="trend-description">Bars show avg artist popularity per genre; the line tracks their average follower count — all from the Artists collection.</h2>
        <canvas id="genrePopChart"></canvas>
        <h3>
            The bars represent the average Popularity score (0–100) of artists grouped by Main_Genre. Overlaid as a line on the right axis, the average Follower count (normalized to 0–100 for readability — hover for real values in thousands) reveals whether popular genres also command larger fanbases. A genre with tall bars but a low line is critically recognized but niche; one where both are high dominates the mainstream. All data comes from the Artists collection.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 2 ===================== -->
    <div class="fullpage-section" id="section2">
        <h1>📊 Popularity Index Distribution</h1>
        <h2 class="trend-description">How are songs distributed across popularity tiers? From underground to mainstream.</h2>
        <canvas id="popDistChart"></canvas>
        <h3>
            This chart buckets all songs in the Songs collection into five popularity tiers (0–20, 20–40, 40–60, 60–80, 80–100) based on their Popularity Index. The shape of this distribution tells us a lot about the music landscape in the dataset: a concentration at high tiers would suggest a catalog dominated by mainstream hits, while a bell curve around the middle tiers indicates a broad, varied library. Understanding this distribution is fundamental before interpreting any other popularity-related trend on this page.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 3 ===================== -->
    <div class="fullpage-section" id="section3" style="overflow-x:auto;">
        <h1>🎵 Audio Profile by Genre</h1>
        <h2 class="trend-description">Average Danceability, Energy, and Valence for the top genres — what does each genre really feel like?</h2>
        <canvas id="audioProfileChart"></canvas>
        <h3>
            Each genre has a sonic identity. This grouped bar chart breaks down the average danceability, energy, and valence of the top 12 genres (by number of tracks) in the Songs collection. Danceability reflects rhythmic suitability for dancing; energy captures the intensity and loudness of the overall sound; valence measures how emotionally positive or upbeat a track feels. Genres with high energy but low valence may lean towards intense or melancholic sounds, while high danceability paired with high valence is the signature of feel-good music. These profiles help understand the emotional DNA of each genre.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 4 ===================== -->
    <div class="fullpage-section" id="section4" style="overflow-x:auto;">
        <h1>⚡ Energy vs Fanbase: Songs meets Artists</h1>
        <h2 class="trend-description">The 10 most energetic artists in Songs, enriched with their Followers and Popularity from the Artists collection.</h2>
        <canvas id="crossGenreChart" ></canvas>
        <h3>
            This is the only trend that crosses both datasets. The 10 artists with the highest average track energy are identified from Songs (minimum 10 tracks). Each artist is then looked up by name in the Artists collection to retrieve their official Followers count and Popularity score. All three metrics are displayed on the same 0–100 scale for direct comparison: energy as a percentage, popularity as-is, and followers normalized to the top value. Artists not found in the Artists collection show N/A in the tooltip — hover each bar to see real values.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 5 (ORIGINALE) ===================== -->
    <div class="fullpage-section" id="section5">
        <h1>🎧 Top 20 Most Liked Tracks by Users</h1>
        <div class="trend-content">
            <h2 class="trend-description">Ranking based on the number of users who have added each track to their favorites.</h2>
            <ol class="top-artists-list">
                <?php $pos = 1; foreach ($braniPreferiti as $brano): ?>
                    <li>
                        <span class="track-rank"><?php echo $pos++; ?>.</span>
                        <span class="artist-name"><?php echo htmlspecialchars($brano['titolo']); ?> - <span class="track-artist"><?php echo htmlspecialchars($brano['artista']); ?></span></span>
                        <span class="count"> Favorited by <?php echo (int)$brano['preferenze']; ?> users</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <h3>
            This list highlights the 20 most favored tracks among users, showing which songs have gained the greatest popularity within the community based on how many users added them to their favorites.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 6 ===================== -->
    <div class="fullpage-section" id="section6">
        <h1 style="color:#222;">🗣️ Speechiness vs Danceability</h1>
        <h2 class="trend-description" style="color:#333;">Does more talking mean less dancing? Exploring how spoken content affects a track's danceability and energy.</h2>
        <canvas id="speechChart"></canvas>
        <h3 style="color:#333;">
            Speechiness measures the presence of spoken words in a track. Tracks with values below 0.1 are almost entirely instrumental or sung; values between 0.1 and 0.33 are typical of most songs; values above 0.33 indicate a strong presence of spoken word, rap, or podcast-style content; and above 0.66 the track is likely entirely spoken. This chart shows, for each speechiness tier, the average danceability and energy, along with the total number of tracks in that bucket. The insight reveals how the amount of spoken content shapes the physical and emotional character of a song — whether rap-heavy tracks are more or less danceable than sung ones, and whether instrumental tracks carry more or less energy.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- ===================== SECTION 7 ===================== -->
    <div class="fullpage-section" id="section7" style="overflow-x:auto;">
        <h1>🏆 Top 15 Genres by Volume & Popularity</h1>
        <h2 class="trend-description">The most represented genres in the catalog, ranked by song count and overlaid with their average popularity index.</h2>
        <canvas id="genreVolumeChart"></canvas>
        <h3>
            This chart ranks the 15 most prolific genres in the Songs collection by the total number of tracks they contain, while a secondary line overlay shows each genre's average Popularity Index. To ensure a meaningful representation, related subgenres have been consolidated into broader families: <em>rock</em> groups rock, hard-rock, rock-n-roll, rockabilly, psych-rock, punk-rock, punk and j-rock; <em>metal</em> covers metal, heavy-metal, black-metal, death-metal and metalcore; <em>alternative</em> unifies alt, alt_music, alt-rock and alternative; <em>hip-hop</em> merges hiphop and hip-hop; and <em>latin</em> brings together latin, latino, reggaeton and reggae.<em>indie</em> consolidates indie and indie-pop,  kids unifies kids and children; j-pop consolidates j-pop, j-dance and j-idol; brazil merges brazil and samba; c-pop groups cantopop and mandopop.. Genres tagged as "unknown" have been excluded. Volume alone does not define a genre's impact: a genre with fewer songs but a high average popularity score may have a more concentrated hit rate, while a genre with thousands of tracks but a lower average may represent a long-tail niche. The interplay between quantity and average quality — as proxied by the Popularity Index — is one of the most informative ways to map the catalog's musical landscape.
        </h3>
        <div class="back-to-top">
            <a href="#top">⬆ Back to top</a>
        </div>
    </div>

    <!-- footer -->
    <footer></footer>

    <!-- JS files -->
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.0.0.min.js"></script>
    <script src="js/plugin.js"></script>
    <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
    <script>
        $(document).ready(function() {
            $(".fancybox").fancybox({ openEffect: "none", closeEffect: "none" });
            $(".zoom").hover(function() { $(this).addClass('transition'); }, function() { $(this).removeClass('transition'); });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

    <!-- ===== CHART 1: due linee — Avg Popularity + Avg Followers ===== -->
    <script>
    const ctxGenrePop = document.getElementById('genrePopChart').getContext('2d');
    const maxFollowers1 = Math.max(...genreAvgFollowers);
    const followersNorm1 = genreAvgFollowers.map(v => maxFollowers1 > 0 ? parseFloat((v / maxFollowers1 * 100).toFixed(1)) : 0);

    new Chart(ctxGenrePop, {
        type: 'line',
        data: {
            labels: genreLabels,
            datasets: [
                {
                    label: 'Avg Artist Popularity (0-100)',
                    data: genreAvgPop,
                    borderColor: 'rgba(255, 255, 255, 1)',
                    backgroundColor: 'rgba(255, 255, 255, 0.06)',
                    borderWidth: 2.5,
                    pointRadius: 6,
                    pointBackgroundColor: 'rgba(255, 255, 255, 1)',
                    pointBorderColor: 'rgba(26, 188, 156, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35
                },
                {
                    label: 'Avg Followers (normalized 0-100)',
                    data: followersNorm1,
                    borderColor: 'rgba(255, 206, 86, 1)',
                    backgroundColor: 'rgba(255, 206, 86, 0.06)',
                    borderWidth: 2.5,
                    pointRadius: 6,
                    pointBackgroundColor: 'rgba(255, 206, 86, 1)',
                    pointBorderColor: 'rgba(26, 188, 156, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.12)' },
                    title: { display: true, text: 'Score (0-100)', color: 'white', font: { size: 12 } }
                },
                x: {
                    ticks: { color: 'white', maxRotation: 35, minRotation: 15 },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            },
            plugins: {
                legend: { labels: { color: 'white', font: { size: 13, weight: 'bold' } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: ctx => ctx[0].label,
                        label: ctx => {
                            const i = ctx.dataIndex;
                            if (ctx.datasetIndex === 0) return 'Avg Popularity: ' + genreAvgPop[i];
                            return 'Avg Followers: ' + genreAvgFollowers[i].toLocaleString() + 'k (norm: ' + followersNorm1[i] + ')';
                        }
                    }
                }
            }
        }
    });
    </script>

    <!-- ===== CHART 2: Popularity Distribution ===== -->
    <script>
    const ctxPopDist = document.getElementById('popDistChart').getContext('2d');
    new Chart(ctxPopDist, {
        type: 'bar',
        data: {
            labels: popBucketLabels,
            datasets: [{
                label: 'Number of Songs',
                data: popBucketCounts,
                backgroundColor: [
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(241, 196, 15, 0.8)',
                    'rgba(230, 126, 34, 0.8)',
                    'rgba(231, 76, 60, 0.8)',
                    'rgba(149, 165, 166, 0.8)'
                ],
                borderColor: 'rgba(255,255,255,0.6)',
                borderWidth: 1,
                maxBarThickness: 80
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                x: {
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                }
            },
            plugins: {
                legend: { labels: { color: 'white' } },
                datalabels: {
                    color: 'white',
                    anchor: 'end',
                    align: 'top',
                    font: { weight: 'bold', size: 13 }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
    </script>

    <!-- ===== CHART 3: Audio Profile by Genre ===== -->
    <script>
    const ctxAudio = document.getElementById('audioProfileChart').getContext('2d');
    new Chart(ctxAudio, {
        type: 'bar',
        data: {
            labels: audioGenreLabels,
            datasets: [
                {
                    label: 'Danceability',
                    data: audioDanceability,
                    backgroundColor: 'rgba(255, 99, 132, 0.75)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    maxBarThickness: 22
                },
                {
                    label: 'Energy',
                    data: audioEnergy,
                    backgroundColor: 'rgba(255, 206, 86, 0.75)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1,
                    maxBarThickness: 22
                },
                {
                    label: 'Valence',
                    data: audioValence,
                    backgroundColor: 'rgba(153, 102, 255, 0.75)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1,
                    maxBarThickness: 22
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1,
                    ticks: {
                        color: 'white',
                        callback: v => (v * 100) + '%'
                    },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                },
                x: {
                    ticks: { color: 'white', maxRotation: 35, minRotation: 15 },
                    grid: { color: 'rgba(255,255,255,0.1)' }
                }
            },
            plugins: {
                legend: { labels: { color: 'white', font: { size: 13, weight: 'bold' } } }
            }
        }
    });
    </script>

    <!-- ===== CHART 4: due linee — Popularity + Followers, energy nel tooltip ===== -->
    <script>
    const ctxCross = document.getElementById('crossGenreChart').getContext('2d');
    const validF4 = crossFollowers.filter(v => v !== null);
    const maxF4   = validF4.length ? Math.max(...validF4) : 1;
    const followersNorm4 = crossFollowers.map(v => v !== null ? parseFloat((v / maxF4 * 100).toFixed(1)) : null);

    new Chart(ctxCross, {
        type: 'line',
        data: {
            labels: crossLabels,
            datasets: [
                {
                    label: 'Artist Popularity (Artists)',
                    data: crossArtistPop,
                    borderColor: 'rgba(26, 188, 156, 1)',
                    backgroundColor: 'rgba(26, 188, 156, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 7,
                    pointBackgroundColor: 'rgba(26, 188, 156, 1)',
                    pointBorderColor: 'rgba(155, 89, 182, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35,
                    spanGaps: true
                },
                {
                    label: 'Avg Followers (normalized)',
                    data: followersNorm4,
                    borderColor: 'rgba(255, 206, 86, 1)',
                    backgroundColor: 'rgba(255, 206, 86, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 7,
                    pointBackgroundColor: 'rgba(255, 206, 86, 1)',
                    pointBorderColor: 'rgba(155, 89, 182, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.12)' },
                    title: { display: true, text: 'Score (0-100)', color: 'white', font: { size: 12 } }
                },
                x: {
                    ticks: { color: 'white', maxRotation: 30, minRotation: 10 },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            },
            plugins: {
                legend: { labels: { color: 'white', font: { size: 12, weight: 'bold' } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: ctx => ctx[0].label,
                        label: ctx => {
                            const i = ctx.dataIndex;
                            if (ctx.datasetIndex === 0) {
                                const v = crossArtistPop[i];
                                return 'Popularity (Artists): ' + (v !== null ? v : 'not in Artists');
                            }
                            const v = crossFollowers[i];
                            return 'Followers (Artists): ' + (v !== null ? v.toLocaleString() + 'k' : 'not in Artists');
                        },
                        afterBody: ctx => ['Avg Energy (Songs): ' + crossEnergy[ctx[0].dataIndex] + '%']
                    }
                }
            }
        }
    });
    </script>

    <!-- ===== CHART 6: due linee — Danceability + Energy, song count nel tooltip ===== -->
    <script>
    const ctxSpeech = document.getElementById('speechChart').getContext('2d');
    new Chart(ctxSpeech, {
        type: 'line',
        data: {
            labels: speechLabels,
            datasets: [
                {
                    label: 'Avg Danceability',
                    data: speechDanceability,
                    borderColor: 'rgba(52, 152, 219, 1)',
                    backgroundColor: 'rgba(52, 152, 219, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 7,
                    pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                    pointBorderColor: 'rgba(241, 196, 15, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.3
                },
                {
                    label: 'Avg Energy',
                    data: speechEnergy,
                    borderColor: 'rgba(231, 76, 60, 1)',
                    backgroundColor: 'rgba(231, 76, 60, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 7,
                    pointBackgroundColor: 'rgba(231, 76, 60, 1)',
                    pointBorderColor: 'rgba(241, 196, 15, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1,
                    ticks: {
                        color: '#333',
                        callback: v => (v * 100).toFixed(0) + '%'
                    },
                    grid: { color: 'rgba(0,0,0,0.1)' },
                    title: { display: true, text: 'Score (0-100%)', color: '#333', font: { size: 12 } }
                },
                x: {
                    ticks: { color: '#333' },
                    grid: { color: 'rgba(0,0,0,0.08)' }
                }
            },
            plugins: {
                legend: { labels: { color: '#222', font: { size: 13, weight: 'bold' } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: ctx => ctx[0].label,
                        label: ctx => {
                            const i = ctx.dataIndex;
                            if (ctx.datasetIndex === 0) return 'Avg Danceability: ' + (speechDanceability[i] * 100).toFixed(1) + '%';
                            return 'Avg Energy: ' + (speechEnergy[i] * 100).toFixed(1) + '%';
                        },
                        afterBody: ctx => ['Songs in this tier: ' + speechCounts[ctx[0].dataIndex].toLocaleString()]
                    }
                }
            }
        }
    });
    </script>

    <!-- ===== CHART 7: due linee — Avg Popularity + Number of Songs ===== -->
    <script>
    const ctxGenreVol = document.getElementById('genreVolumeChart').getContext('2d');
    const maxSongs7 = Math.max(...genreSongCounts);
    const songsNorm7 = genreSongCounts.map(v => parseFloat((v / maxSongs7 * 100).toFixed(1)));

    new Chart(ctxGenreVol, {
        type: 'line',
        data: {
            labels: genreCountLabels,
            datasets: [
                {
                    label: 'Avg Popularity Index (0-100)',
                    data: genreAvgPopIdx,
                    borderColor: 'rgba(255, 255, 255, 1)',
                    backgroundColor: 'rgba(255, 255, 255, 0.06)',
                    borderWidth: 2.5,
                    pointRadius: 6,
                    pointBackgroundColor: 'rgba(255, 255, 255, 1)',
                    pointBorderColor: 'rgba(230, 126, 34, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35
                },
                {
                    label: 'Number of Songs (normalized 0-100)',
                    data: songsNorm7,
                    borderColor: 'rgba(255, 206, 86, 1)',
                    backgroundColor: 'rgba(255, 206, 86, 0.06)',
                    borderWidth: 2.5,
                    pointRadius: 6,
                    pointBackgroundColor: 'rgba(255, 206, 86, 1)',
                    pointBorderColor: 'rgba(230, 126, 34, 1)',
                    pointBorderWidth: 2.5,
                    fill: false,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { color: 'white' },
                    grid: { color: 'rgba(255,255,255,0.12)' },
                    title: { display: true, text: 'Score (0-100)', color: 'white', font: { size: 12 } }
                },
                x: {
                    ticks: { color: 'white', maxRotation: 35, minRotation: 15 },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            },
            plugins: {
                legend: { labels: { color: 'white', font: { size: 13, weight: 'bold' } } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: ctx => ctx[0].label,
                        label: ctx => {
                            const i = ctx.dataIndex;
                            if (ctx.datasetIndex === 0) return 'Avg Popularity: ' + genreAvgPopIdx[i];
                            return 'Songs: ' + genreSongCounts[i].toLocaleString() + ' (norm: ' + songsNorm7[i] + ')';
                        }
                    }
                }
            }
        }
    });
    </script>

</body>
</html>