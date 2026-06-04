<?php
session_start();
// Verifica se l'utente è loggato
if (!isset($_SESSION['utente_loggato'])) {
    header('Location: login.php');
    exit();
}
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
// ──────────────────────────────────────────────
//  POST: Aggiungi ai preferiti
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['track_id']) && !empty($_POST['track_name'])) {
    $utente   = $_SESSION['utente_loggato'];
    $trackId  = $_POST['track_id'];
    try {
        $query  = new MongoDB\Driver\Query(['username' => $utente, 'preferiti.brani.id_brano' => $trackId]);
        $cursor = $manager->executeQuery('admin.User', $query);
        $esiste = iterator_count($cursor) > 0;
        if ($esiste) {
            echo json_encode(['success' => false, 'message' => 'The track is already in your favorites!']);
            exit();
        }
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['username' => $utente],
            ['$addToSet' => ['preferiti.brani' => ['id_brano' => $trackId]]],
            ['multi' => false, 'upsert' => true]
        );
        $manager->executeBulkWrite('admin.User', $bulk);
        echo json_encode(['success' => true, 'message' => 'Track ' . $_POST['track_name'] . ' added to favorites!']);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding the track!']);
        exit();
    }
}

// ──────────────────────────────────────────────
//  POST: Recupera playlist dell'utente
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_playlists') {
    $username       = $_SESSION['utente_loggato'];
    $idBranoCliccato = $_POST['song_id'] ?? null;
    $query    = new MongoDB\Driver\Query(['username' => $username]);
    $userCursor = $manager->executeQuery('admin.User', $query);
    $user     = current($userCursor->toArray());
    if (!$user) { die('Utente non trovato'); }
    header('Content-Type: application/json');
    if (!empty($user->playlist_personali)) {
        $playlistArray = array_map(function($playlist) {
            return [
                'nome_playlist' => $playlist->nome_playlist,
                'descrizione'   => $playlist->descrizione ?? '',
                'brani'         => array_map(function($brano) { return $brano->id_brano; }, $playlist->brani ?? [])
            ];
        }, $user->playlist_personali ?? []);
        echo json_encode(['id_brano_richiesto' => $idBranoCliccato, 'playlists' => $playlistArray]);
    } else {
        echo json_encode(['id_brano_richiesto' => $idBranoCliccato, 'playlists' => []]);
    }
    exit();
}

// ──────────────────────────────────────────────
//  Costruisci filtri dai parametri GET
// ──────────────────────────────────────────────
$filters = [];
if (!empty($_GET['song_title'])) {
    $filters['Song_Title'] = ['$regex' => $_GET['song_title'], '$options' => 'i'];
}
if (!empty($_GET['artist_name'])) {
    $filters['Artist_Name'] = ['$regex' => $_GET['artist_name'], '$options' => 'i'];
}
if (!empty($_GET['genre'])) {
    $filters['Genre'] = $_GET['genre'];
}

// Mapping sort field → percorso MongoDB
$sort_fields_map = [
    'Popularity_Index' => 'Popularity_Index',
    'danceability'     => 'Technical_Metrics.danceability',
    'valence'          => 'Technical_Metrics.valence',
    'energy'           => 'Technical_Metrics.energy',
    'tempo'            => 'Technical_Metrics.tempo',
    'acousticness'     => 'Technical_Metrics.acousticness',
    'loudness'         => 'Technical_Metrics.loudness',
];
$sort_field_key = $_GET['sort_field'] ?? 'Popularity_Index';
$sort_field     = $sort_fields_map[$sort_field_key] ?? 'Popularity_Index';
$sort_direction = (($_GET['sort_order'] ?? 'desc') === 'asc') ? 1 : -1;
$sort = [$sort_field => $sort_direction];

// ──────────────────────────────────────────────
//  Count totale con filtri applicati
// ──────────────────────────────────────────────
$results_per_page = 20;
try {
    $countCommand = new MongoDB\Driver\Command(['count' => 'Songs', 'query' => (object) $filters]);
    $countResult  = $manager->executeCommand('admin', $countCommand)->toArray()[0];
    $totalSongs   = $countResult->n ?? 0;
} catch (Exception $e) {
    $totalSongs = 0;
}
$total_pages = max(1, ceil($totalSongs / $results_per_page));

// ──────────────────────────────────────────────
//  Paginazione
// ──────────────────────────────────────────────
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, min($page, $total_pages));
$skip = ($page - 1) * $results_per_page;

$next_page = $page < $total_pages ? $page + 1 : $total_pages;
$prev_page = $page > 1 ? $page - 1 : 1;

$pagination_window = 3;
$start_page = max(1, $page - $pagination_window);
$end_page   = min($total_pages, $page + $pagination_window);
if ($page == 1)              { $start_page = 1; $end_page = min($total_pages, 6); }
elseif ($page == 2)          { $start_page = 1; $end_page = min($total_pages, 5); }
elseif ($page == $total_pages)     { $start_page = max(1, $total_pages - 5); $end_page = $total_pages; }
elseif ($page == $total_pages - 1) { $start_page = max(1, $total_pages - 5); $end_page = $total_pages; }

// Helper URL paginazione (preserva filtri attivi)
function buildPaginationUrl($targetPage, $getParams) {
    $params = $getParams;
    unset($params['page']);
    $params['page'] = $targetPage;
    return '?' . http_build_query($params);
}

// ──────────────────────────────────────────────
//  Query principale
// ──────────────────────────────────────────────
$options = ['limit' => $results_per_page, 'skip' => $skip, 'sort' => $sort];
$query   = new MongoDB\Driver\Query($filters, $options);
$cursor  = $manager->executeQuery('admin.Songs', $query);

$uniqueTracks = [];
$seenIds      = [];
foreach ($cursor as $document) {
    $id = (string)$document->_id;
    if (!in_array($id, $seenIds)) {
        $uniqueTracks[] = $document;
        $seenIds[]      = $id;
    }
}

// ──────────────────────────────────────────────
//  Fetch generi distinti per il filtro
// ──────────────────────────────────────────────
$genres = [];
try {
    $genresCmd    = new MongoDB\Driver\Command(['distinct' => 'Songs', 'key' => 'Genre', 'query' => (object)[]]);
    $genresResult = $manager->executeCommand('admin', $genresCmd)->toArray()[0];
    $genres       = (array)($genresResult->values ?? []);
    $genres       = array_filter($genres, fn($g) => !empty($g));
    sort($genres);
} catch (Exception $e) {
    $genres = [];
}

include 'header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <title>Archive</title>
    <meta name="keywords" content="">
    <meta name="description" content="">
    <meta name="author" content="">
    <!-- bootstrap css -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="images/fevicon.png" type="image/gif" />
    <!-- Scrollbar Custom CSS -->
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <!-- Tweaks for older IEs-->
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css" media="screen">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <style>
        /* ── Song list ── */
        .song-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 800px;
            margin: auto;
        }
        .song-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .song-info { flex: 1; }
        .song-title  { font-size: 18px; font-weight: bold; }
        .song-artist { color: #666; }
        .song-streams { font-size: 14px; color: #999; }

        /* ── Buttons ── */
        .add-button {
            padding: 8px 12px;
            background: #1db954;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            margin-top: 8px;
            margin-right: 6px;
        }
        .add-button:hover { background: #169c44; }

        /* ── Filters form ── */
        .formfiltri {
            margin: 20px auto 30px auto;
            padding: 24px 32px;
            max-width: 760px;
            width: 95%;
            background: rgba(0, 0, 0, 0.45);
            border-radius: 14px;
            text-align: left;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        .filters-grid label {
            display: flex;
            flex-direction: column;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .filters-grid label.full-width {
            grid-column: span 2;
        }
        input[type=text],
        input[type=number],
        select {
            width: 100%;
            padding: 10px 14px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .select1 {
            width: 100%;
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            background-color: #f1f1f1;
        }
        .nice-select { display: none; }

        /* ── Apply button ── */
        .button1 {
            display: block;
            margin: 18px auto 0 auto;
            width: 40%;
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }
        .button1:hover { background-color: #43a047; }

        /* ── Background ── */
        .sfondo {
            background-image: url('images/sfondobody.jpg');
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
            background-size: cover;
            padding-top: 20px;
            padding-bottom: 40px;
        }

        /* ── Generic popup overlay ── */
        .popup-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .popup-content {
            background: white;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.35);
            text-align: center;
            max-width: 560px;
            width: 90%;
            overflow: hidden;
        }
        .popup-header {
            padding: 18px 24px 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .popup-header.success { background: #e8f5e9; border-bottom: 2px solid #1db954; }
        .popup-header.error   { background: #fdecea; border-bottom: 2px solid #e53935; }
        .popup-header.neutral { background: #f5f5f5; border-bottom: 2px solid #ccc; }
        .popup-icon { font-size: 26px; line-height: 1; }
        .popup-header h2 {
            font-size: 17px;
            font-weight: 700;
            margin: 0;
            color: #222;
        }
        .popup-body {
            padding: 18px 28px 22px;
        }
        .popup-body p {
            font-size: 15px;
            color: #444;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .popup-content h3 {
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: #222;
            padding: 16px 24px 0;
        }

        /* ── Lyrics & Features overlay content ── */
        .overlay-scroll {
            max-height: 55vh;
            overflow-y: auto;
            text-align: left;
            margin: 0 0 14px 0;
            padding: 12px 14px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .lyrics-text {
            white-space: pre-wrap;
            font-size: 14px;
            line-height: 1.7;
            font-family: Georgia, serif;
        }
        .feature-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        .feature-row:last-child { border-bottom: none; }
        .feature-label { font-weight: 600; color: #444; }
        .feature-value { color: #222; }

        /* ── Playlist popup ── */
        #playlist-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        #playlist-list li {
            padding: 10px;
            background-color: #f1f1f1;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        #playlist-list li:hover { background-color: #ddd; }
        .popup-buttons {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        #close-popup { padding: 10px 20px; background-color: #ccc; color: #333; border: none; border-radius: 5px; cursor: pointer; }
        #close-popup:hover { background-color: #bbb; }
        #add-to-playlist { padding: 10px 20px; background-color: #1db954; color: white; border: none; border-radius: 5px; cursor: pointer; }
        #add-to-playlist:hover { background-color: #169c44; }
        .no-playlist-link { color: #1db954; font-weight: 600; text-decoration: underline; display: inline-block; margin-top: 10px; }

        /* ── Playlist table ── */
        .playlist-table {
            max-height: 300px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0 0 6px 0;
            padding-right: 4px;
        }
        .playlist-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fafafa;
            transition: background-color 0.2s;
        }
        .playlist-row:hover { background-color: #f0f0f0; }
        .playlist-cell.name { font-weight: 500; font-size: 15px; color: #333; }
        .playlist-cell.button { display: flex; justify-content: flex-end; }
        .addPlaylistItemBtn {
            border: none;
            border-radius: 50%;
            width: 30px; height: 30px;
            font-size: 18px; font-weight: bold;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.15s, opacity 0.15s;
        }
        .addPlaylistItemBtn:hover { transform: scale(1.15); opacity: 0.85; }
        .addPlaylistItemBtn.btn-add    { background-color: #1db954; color: white; }
        .addPlaylistItemBtn.btn-remove { background-color: #e53935; color: white; }
        .playlistToggleBtn {
            border: none; border-radius: 50%; width: 25px; height: 25px;
            font-size: 16px; font-weight: bold; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .playlistToggleBtn.add { background-color: #1db954; color: white; }
        .playlistToggleBtn.remove { background-color: #e53935; color: white; }

        /* ── Toast ── */
        .toast {
            visibility: hidden;
            min-width: 280px;
            max-width: 420px;
            background-color: #333;
            color: white;
            text-align: center;
            border-radius: 8px;
            padding: 14px 22px;
            position: fixed;
            z-index: 9999;
            left: 50%;
            transform: translateX(-50%);
            bottom: 36px;
            font-size: 15px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            letter-spacing: 0.01em;
        }
        .toast.show { visibility: visible; animation: fadein 0.4s, fadeout 0.5s 2.5s; }
        @keyframes fadein  { from {bottom: 10px; opacity: 0;} to {bottom: 36px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 36px; opacity: 1;} to {bottom: 10px; opacity: 0;} }

        /* ── Pagination ── */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            margin: 20px 0 10px 0;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            color: #333;
            background-color: #f1f1f1;
            border-radius: 5px;
            font-size: 14px;
        }
        .pagination .prev, .pagination .next { font-size: 16px; }
        .pagination .first, .pagination .last { font-weight: bold; }
        .pagination a:hover { background-color: #ddd; }
        .pagination .active { background-color: #4CAF50; color: white; }
        .pagination-info { text-align: center; color: #ccc; font-size: 13px; margin-bottom: 12px; }
    </style>
</head>

<body class="main-layout about-page">
    <!-- loader -->
    <div class="loader_bg">
        <div class="loader"><img src="images/loading.gif" alt="#" /></div>
    </div>
    <div class="sfondo">

        <h1 style="color: white; font-weight: bold; text-align: center; padding-top: 20px;">Songs Archive</h1>

        <!-- ════════════ FILTRI ════════════ -->
        <form method="GET" class="formfiltri">
            <div class="filters-grid">

                <label>
                    Song Name:
                    <input type="text" name="song_title"
                           placeholder="Search by title…"
                           value="<?= htmlspecialchars($_GET['song_title'] ?? '') ?>">
                </label>

                <label>
                    Artist:
                    <input type="text" name="artist_name"
                           placeholder="Search by artist…"
                           value="<?= htmlspecialchars($_GET['artist_name'] ?? '') ?>">
                </label>

                <label>
                    Genre:
                    <select name="genre">
                        <option value="">All genres</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>"
                                <?= (isset($_GET['genre']) && $_GET['genre'] === $g) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($g)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Order by:
                    <select name="sort_field" class="select1">
                        <?php
                        $sortOptions = [
                            'Popularity_Index' => 'Popularity',
                            'danceability'     => 'Danceability',
                            'valence'          => 'Valence',
                            'energy'           => 'Energy',
                            'tempo'            => 'Tempo',
                            'acousticness'     => 'Acousticness',
                            'loudness'         => 'Loudness',
                        ];
                        foreach ($sortOptions as $val => $label):
                        ?>
                            <option value="<?= $val ?>"
                                <?= (($_GET['sort_field'] ?? 'Popularity_Index') === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="full-width" style="grid-column: span 1;">
                    Direction:
                    <select name="sort_order" class="select1">
                        <option value="desc" <?= (($_GET['sort_order'] ?? 'desc') === 'desc') ? 'selected' : '' ?>>Descending</option>
                        <option value="asc"  <?= (($_GET['sort_order'] ?? 'desc') === 'asc')  ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </label>

            </div>

            <button type="submit" class="button1">Apply Filters</button>

            <!-- ════════ PAGINAZIONE ════════ -->
            <div class="pagination-info">
                Page <?= $page ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= $totalSongs ?> songs found
            </div>
            <div class="pagination">
                <a href="<?= buildPaginationUrl(1, $_GET) ?>" class="first">« First</a>
                <a href="<?= buildPaginationUrl($prev_page, $_GET) ?>" class="prev">‹</a>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?= buildPaginationUrl($i, $_GET) ?>"
                       class="<?= ($i === $page) ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <a href="<?= buildPaginationUrl($next_page, $_GET) ?>" class="next">›</a>
                <a href="<?= buildPaginationUrl($total_pages, $_GET) ?>" class="last">Last »</a>
            </div>

        </form>
        <!-- end filtri -->

        <!-- ════════════ SONG LIST ════════════ -->
        <div class="song-list">
            <?php foreach ($uniqueTracks as $song):
                // Prepara dati da passare a JS
                $metrics     = $song->Technical_Metrics ?? null;
                $metricsJson = htmlspecialchars(json_encode($metrics), ENT_QUOTES, 'UTF-8');
                $lyricsEsc   = htmlspecialchars($song->Full_Lyrics ?? '', ENT_QUOTES, 'UTF-8');
                $titleEsc    = htmlspecialchars($song->Song_Title  ?? 'Unknown Title', ENT_QUOTES, 'UTF-8');
                $artistEsc   = htmlspecialchars($song->Artist_Name ?? 'Unknown Artist', ENT_QUOTES, 'UTF-8');

                // Durata formattata
                $durationStr = 'N/A';
                if (!empty($metrics->duration_ms)) {
                    $sec = floor($metrics->duration_ms / 1000);
                    if ($sec > 0) $durationStr = gmdate("i:s", $sec);
                }
            ?>
            <div class="song-card">
                <div class="song-info">
                    <div class="song-title"><?= htmlspecialchars($song->Song_Title  ?? 'Unknown Title') ?></div>
                    <div class="song-artist"><?= htmlspecialchars($song->Artist_Name ?? 'Unknown Artist') ?></div>
                    <div class="song-danceability">
                        Album: <?= htmlspecialchars($song->Album_name ?? 'Unknown Album') ?>
                    </div>
                    <div class="song-duration">Duration: <?= $durationStr ?></div>
                    <div class="song-popularity">
                        Genre: <?= htmlspecialchars($song->Genre ?? 'Unknown') ?>
                        &nbsp;·&nbsp;
                        Popularity: <?= htmlspecialchars($song->Popularity_Index ?? 'N/A') ?>
                    </div>

                    <!-- Tasto Lyrics -->
                    <button class="add-button"
                            data-lyrics="<?= $lyricsEsc ?>"
                            data-title="<?= $titleEsc ?>"
                            onclick="openLyricsOverlay(this)">
                        🎵 Lyrics
                    </button>

                    <!-- Tasto Features -->
                    <button class="add-button"
                            data-metrics="<?= $metricsJson ?>"
                            data-title="<?= $titleEsc ?>"
                            onclick="openFeaturesOverlay(this)">
                        📊 Features
                    </button>
                </div>

                <!-- Tasti playlist/preferiti -->
                <div class="button-container">
                    <?php if (isset($_SESSION['utente_loggato'])): ?>

                        <!-- Add to Playlist -->
                        <form method="POST" action="" onsubmit="handleAdd(event, this, 'playlist')">
                            <input type="hidden" name="song_id"   value="<?= htmlspecialchars($song->Track_ID) ?>">
                            <input type="hidden" name="song_name" value="<?= htmlspecialchars($song->Song_Title ?? '') ?>">
                            <button id="aggiungiPlaylistBtn" class="add-button">➕ Add to Playlist</button>
                        </form>
                        <br>

                        <!-- Add to Favorites -->
                        <form method="POST" action="" onsubmit="handleAdd(event, this, 'preferiti')">
                            <input type="hidden" name="track_id"   value="<?= htmlspecialchars($song->Track_ID) ?>">
                            <input type="hidden" name="track_name" value="<?= htmlspecialchars($song->Song_Title ?? '') ?>">
                            <button class="add-button" type="submit">❤️ Add to Favorites</button>
                        </form>

                    <?php else: ?>
                        <p><a href="login.php">Log in to add to favorites and playlists!</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- end song list -->

        <!-- ════════════ POPUP GENERICO (successo/errore) ════════════ -->
        <div id="popup" class="popup-overlay" style="display: none;">
            <div class="popup-content">
                <div id="popup-header" class="popup-header neutral">
                    <span id="popup-icon" class="popup-icon"></span>
                    <h2 id="popup-title"></h2>
                </div>
                <div class="popup-body">
                    <p id="popup-message"></p>
                    <button class="add-button" onclick="closePopup()">✕ Close</button>
                </div>
            </div>
        </div>

        <!-- ════════════ POPUP PLAYLIST (legacy, usato da JS) ════════════ -->
        <div id="playlist-popup" class="popup" style="display: none;">
            <div class="popup-content">
                <h2>Choose a Playlist</h2>
                <ul id="playlist-list"></ul>
                <div class="popup-buttons">
                    <button id="close-popup" onclick="closePlaylistPopup()">Cancel</button>
                    <button id="add-to-playlist" onclick="addToPlaylist()">Add to Playlist</button>
                </div>
            </div>
        </div>

        <!-- ════════════ OVERLAY LYRICS ════════════ -->
        <div id="lyrics-overlay" class="popup-overlay" style="display: none;">
            <div class="popup-content" style="max-width: 620px; width: 92%;">
                <div class="popup-header neutral">
                    <span class="popup-icon">🎵</span>
                    <h2 id="lyrics-title" style="font-size:17px;"></h2>
                </div>
                <div class="popup-body">
                    <div class="overlay-scroll">
                        <div id="lyrics-content" class="lyrics-text"></div>
                    </div>
                    <button class="add-button" onclick="document.getElementById('lyrics-overlay').style.display='none'">
                        ✕ Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════ OVERLAY FEATURES ════════════ -->
        <div id="features-overlay" class="popup-overlay" style="display: none;">
            <div class="popup-content" style="max-width: 500px; width: 92%;">
                <div class="popup-header neutral">
                    <span class="popup-icon">📊</span>
                    <h2 id="features-title" style="font-size:17px;"></h2>
                </div>
                <div class="popup-body">
                    <div class="overlay-scroll">
                        <div id="features-content"></div>
                    </div>
                    <button class="add-button" onclick="document.getElementById('features-overlay').style.display='none'">
                        ✕ Close
                    </button>
                </div>
            </div>
        </div>

    </div><!-- end sfondo -->

    <div id="toast"></div>

    <!-- ════════════ JAVASCRIPT ════════════ -->
    <script>

    /* ── Popup generico successo/errore ── */
    function showPopup(message, isSuccess) {
        const header  = document.getElementById('popup-header');
        const icon    = document.getElementById('popup-icon');
        const title   = document.getElementById('popup-title');
        const msgEl   = document.getElementById('popup-message');

        header.className = 'popup-header ' + (isSuccess ? 'success' : 'error');
        icon.textContent  = isSuccess ? '✅' : '❌';
        title.textContent = isSuccess ? 'Done!' : 'Attention';
        msgEl.textContent = message;

        const popup = document.getElementById('popup');
        popup.style.display = 'flex';
    }
    function closePopup() {
        document.getElementById('popup').style.display = 'none';
    }

    /* ── Overlay Lyrics ── */
    function openLyricsOverlay(btn) {
        const lyrics = btn.getAttribute('data-lyrics');
        const title  = btn.getAttribute('data-title');
        document.getElementById('lyrics-title').textContent  = title;
        document.getElementById('lyrics-content').textContent =
            (lyrics && lyrics.trim()) ? lyrics : 'Lyrics not available for this song.';
        document.getElementById('lyrics-overlay').style.display = 'flex';
    }

    /* ── Overlay Features (Technical Metrics) ── */
    function openFeaturesOverlay(btn) {
        const title     = btn.getAttribute('data-title');
        const metricsStr = btn.getAttribute('data-metrics');
        let metrics = {};
        try { metrics = JSON.parse(metricsStr) || {}; } catch(e) {}

        document.getElementById('features-title').textContent = title;

        const labels = {
            duration_ms:       'Duration (ms)',
            explicit:          'Explicit',
            danceability:      'Danceability',
            energy:            'Energy',
            key:               'Key',
            loudness:          'Loudness (dB)',
            mode:              'Mode',
            speechiness:       'Speechiness',
            acousticness:      'Acousticness',
            instrumentalness:  'Instrumentalness',
            liveness:          'Liveness',
            valence:           'Valence',
            tempo:             'Tempo (BPM)',
            time_signature:    'Time Signature',
        };

        const content = document.getElementById('features-content');
        content.innerHTML = '';

        let hasData = false;
        for (const [key, label] of Object.entries(labels)) {
            const val = metrics[key];
            if (val !== undefined && val !== null && val !== '') {
                hasData = true;
                const row = document.createElement('div');
                row.className = 'feature-row';
                // Format numbers to 4 decimal places max
                const displayVal = (typeof val === 'number') ? +val.toFixed(4) : val;
                row.innerHTML = `<span class="feature-label">${label}</span><span class="feature-value">${displayVal}</span>`;
                content.appendChild(row);
            }
        }
        if (!hasData) {
            content.textContent = 'No features available for this song.';
        }

        document.getElementById('features-overlay').style.display = 'flex';
    }

    /* ── Handler form (playlist / preferiti) ── */
    function handleAdd(event, form, tipo) {
        event.preventDefault();
        const formData = new FormData(form);

        if (tipo === 'preferiti') {
            fetch(form.action || window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => showPopup(data.message, data.success))
                .catch(err => showPopup('Error: ' + err, false));

        } else if (tipo === 'playlist') {
            formData.append('action', 'get_playlists');
            fetch(form.action || window.location.href, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => mostraPopupPlaylist(data.playlists, data.id_brano_richiesto))
                .catch(err => console.error('Errore fetch playlist:', err));
        }
    }

    /* ── Popup selezione playlist ── */
    function mostraPopupPlaylist(playlists, branoId) {
        const popup = document.createElement('div');
        popup.className = 'popup-overlay';
        popup.id = 'playlistPopupAdd';

        const content = document.createElement('div');
        content.className = 'popup-content';

        // Header
        const header = document.createElement('div');
        header.className = 'popup-header neutral';
        header.innerHTML = '<span class="popup-icon">🎵</span><h2>Add to Playlist</h2>';
        content.appendChild(header);

        // Body
        const body = document.createElement('div');
        body.className = 'popup-body';

        if (!playlists || playlists.length === 0) {
            const msg = document.createElement('p');
            msg.style.marginBottom = '10px';
            msg.textContent = "You don't have any playlists yet.";
            const link = document.createElement('a');
            link.href = 'profilo.php';
            link.textContent = 'Go to your profile to create one!';
            link.className = 'no-playlist-link';
            body.appendChild(msg);
            body.appendChild(link);
        } else {
            const table = document.createElement('div');
            table.className = 'playlist-table';

            playlists.forEach(pl => {
                const row     = document.createElement('div');
                row.className = 'playlist-row';

                const cellName = document.createElement('div');
                cellName.className = 'playlist-cell name';
                cellName.textContent = pl.nome_playlist || 'Unnamed';

                const cellBtn = document.createElement('div');
                cellBtn.className = 'playlist-cell button';

                const addBtn = document.createElement('button');
                const alreadyIn = pl.brani && pl.brani.includes(branoId);
                addBtn.className = 'addPlaylistItemBtn ' + (alreadyIn ? 'btn-remove' : 'btn-add');
                addBtn.innerText  = alreadyIn ? '−' : '+';
                addBtn.title      = alreadyIn ? 'Remove from playlist' : 'Add to playlist';
                addBtn.onclick    = alreadyIn
                    ? () => rimuoviCanzoneDaPlaylist(pl.nome_playlist, branoId)
                    : () => aggiungiCanzoneAPlaylist(pl.nome_playlist, branoId);

                cellBtn.appendChild(addBtn);
                row.appendChild(cellName);
                row.appendChild(cellBtn);
                table.appendChild(row);
            });
            body.appendChild(table);
        }

        const closeBtn = document.createElement('button');
        closeBtn.textContent = '✕ Close';
        closeBtn.className   = 'add-button';
        closeBtn.style.marginTop = '16px';
        closeBtn.onclick = () => popup.remove();
        body.appendChild(closeBtn);

        content.appendChild(body);
        popup.appendChild(content);
        document.body.appendChild(popup);
    }

    function closePlaylistPopup() {
        const el = document.getElementById('playlistPopup');
        if (el) el.style.display = 'none';
    }

    /* ── Aggiungi / Rimuovi da playlist ── */
    function aggiungiCanzoneAPlaylist(playlistName, branoId) {
        fetch('modifica_playlist.php', {
            method: 'POST',
            body: JSON.stringify({ nome_playlist: playlistName, song_id: branoId, action: 'add' })
        })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('playlistPopupAdd');
            if (el) el.remove();
            showPopup(data.message, data.success);
        })
        .catch(err => showPopup('Error: ' + err, false));
    }

    function rimuoviCanzoneDaPlaylist(playlistName, branoId) {
        fetch('modifica_playlist.php', {
            method: 'POST',
            body: JSON.stringify({ nome_playlist: playlistName, song_id: branoId, action: 'remove' })
        })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('playlistPopupAdd');
            if (el) el.remove();
            showPopup(data.message, data.success);
        })
        .catch(err => showPopup('Error: ' + err, false));
    }

    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
            $(".zoom").hover(
                function() { $(this).addClass('transition'); },
                function() { $(this).removeClass('transition'); }
            );
        });
    </script>
</body>
</html>