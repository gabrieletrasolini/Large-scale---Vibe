<?php
session_start();
if (!isset($_SESSION['utente_loggato'])) {
    header('Location: login.php');
    exit();
}
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
include 'header.php';
// ──────────────────────────────────────────────
//  Costruisci filtri dai parametri GET
// ──────────────────────────────────────────────
$filters = [];
if (!empty($_GET['artist_name'])) {
    $filters['Name'] = ['$regex' => $_GET['artist_name'], '$options' => 'i'];
}

// Sort
$sort_field_key = $_GET['sort_field'] ?? 'Popularity';
$sort_fields_map = [
    'Popularity'      => 'Popularity',
    'Followers'       => 'Followers',
    'Number_of_songs' => 'Number_of_songs',
    'Name'            => 'Name',
];
$sort_field     = $sort_fields_map[$sort_field_key] ?? 'Popularity';
$sort_direction = (($_GET['sort_order'] ?? 'desc') === 'asc') ? 1 : -1;
$sort = [$sort_field => $sort_direction];

// ──────────────────────────────────────────────
//  Count totale con filtri applicati
// ──────────────────────────────────────────────
$results_per_page = 20;
try {
    $countCommand = new MongoDB\Driver\Command(['count' => 'Artists', 'query' => (object)$filters]);
    $countResult  = $manager->executeCommand('admin', $countCommand)->toArray()[0];
    $totalArtists = $countResult->n ?? 0;
} catch (Exception $e) {
    $totalArtists = 0;
}
$total_pages = max(1, ceil($totalArtists / $results_per_page));

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
if      ($page == 1)              { $start_page = 1; $end_page = min($total_pages, 6); }
elseif  ($page == 2)              { $start_page = 1; $end_page = min($total_pages, 5); }
elseif  ($page == $total_pages)   { $start_page = max(1, $total_pages - 5); $end_page = $total_pages; }
elseif  ($page == $total_pages-1) { $start_page = max(1, $total_pages - 5); $end_page = $total_pages; }

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
$cursor  = $manager->executeQuery('admin.Artists', $query);
$artists = iterator_to_array($cursor);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <title>Artists Archive</title>
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <style>
        /* ── Artist list ── */
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

        /* Artist badge iniziale */
        .artist-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1db954, #169c44);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            font-weight: bold;
            margin-right: 18px;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        /* stat badges inline */
        .artist-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f1f8f3;
            border: 1px solid #c8e6c9;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 13px;
            color: #2e7d32;
            font-weight: 500;
        }
        .stat-badge .stat-icon { font-size: 14px; }

        /* ── Add button ── */
        .add-button {
            padding: 8px 14px;
            background: #1db954;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            margin-top: 6px;
            white-space: nowrap;
        }
        .add-button:hover { background: #169c44; }

        /* "View Songs" button — differenziato */
        .view-songs-btn {
            padding: 10px 18px;
            background: #1565c0;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .view-songs-btn:hover { background: #0d47a1; color: white; text-decoration: none; }

        /* ── Filters ── */
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
        .filters-grid label.full-width { grid-column: span 2; }
        input[type=text], select {
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

        /* ── Toast ── */
        .toast {
            visibility: hidden;
            min-width: 250px;
            margin-left: -155px;
            background-color: #333;
            color: white;
            text-align: center;
            border-radius: 2px;
            padding: 16px;
            position: fixed;
            z-index: 9999;
            left: 50%;
            bottom: 30px;
            font-size: 17px;
        }
        .toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        /* ── Popup ── */
        .popup-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex; justify-content: center; align-items: center;
            z-index: 1000;
        }
        .popup-content {
            background: white; padding: 20px 30px;
            border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center; max-width: 560px; width: 90%;
        }
        .popup-content h2 { font-size: 22px; margin-bottom: 14px; font-weight: bold; }
        .popup-content h3 { font-size: 18px; margin-bottom: 10px; }
        .no-playlist-link { color: #007bff; text-decoration: underline; display: inline-block; margin-top: 10px; }
        .playlist-table { max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-top: 10px; padding-right: 8px; }
        .playlist-row { display: grid; grid-template-columns: 1fr auto; align-items: center; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background-color: #f9f9f9; min-width: 250px; }
        .playlist-cell.name { font-weight: 500; font-size: 16px; padding-left: 5px; }
        .playlist-cell.button { display: flex; justify-content: flex-end; }
        .addPlaylistItemBtn { background-color: green; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; font-size: 16px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        #popup-buttons { display: flex; justify-content: space-between; gap: 10px; }
        #close-popup { padding: 10px 20px; background-color: #ccc; color: #333; border: none; border-radius: 5px; cursor: pointer; }
        #close-popup:hover { background-color: #bbb; }
    </style>
</head>

<body class="main-layout about-page">
    <!-- loader -->
    <div class="loader_bg">
        <div class="loader"><img src="images/loading.gif" alt="#" /></div>
    </div>

    <div class="sfondo">

        <h1 style="color: white; font-weight: bold; text-align: center; padding-top: 20px;">Artists Archive</h1>

        <!-- ════════════ FILTRI ════════════ -->
        <form method="GET" class="formfiltri">
            <div class="filters-grid">

                <label class="full-width">
                    Artist Name:
                    <input type="text" name="artist_name"
                           placeholder="Search by artist name…"
                           value="<?= htmlspecialchars($_GET['artist_name'] ?? '') ?>">
                </label>

                <label>
                    Order by:
                    <select name="sort_field" class="select1">
                        <?php
                        $sortOptions = [
                            'Popularity'      => 'Popularity',
                            'Followers'       => 'Followers',
                            'Number_of_songs' => 'Number of Songs',
                            'Name'            => 'Name (A–Z)',
                        ];
                        foreach ($sortOptions as $val => $label):
                        ?>
                            <option value="<?= $val ?>"
                                <?= (($_GET['sort_field'] ?? 'Popularity') === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
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
                Page <?= $page ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= $totalArtists ?> artists found
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

        <!-- ════════════ ARTIST LIST ════════════ -->
        <div class="song-list">
            <?php foreach ($artists as $artist):
                $name       = $artist->Name            ?? 'Unknown Artist';
                $followers  = $artist->Followers       ?? null;
                $popularity = $artist->Popularity      ?? null;
                $genre      = $artist->Main_Genre      ?? null;
                $numSongs   = $artist->Number_of_songs ?? null;
                $artistId   = $artist->Artist_ID       ?? (string)$artist->_id;

                // Iniziale per l'avatar
                $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));

                // Formatta followers
                $followersStr = 'N/A';
                if ($followers !== null) {
                    if ($followers >= 1000000)     $followersStr = number_format($followers / 1000000, 1) . 'M';
                    elseif ($followers >= 1000)    $followersStr = number_format($followers / 1000, 1) . 'K';
                    else                           $followersStr = number_format($followers);
                }
            ?>
            <div class="song-card">
                <!-- Avatar iniziale -->
                <div class="artist-avatar"><?= htmlspecialchars($initial) ?></div>

                <div class="song-info">
                    <div class="song-title"><?= htmlspecialchars($name) ?></div>

                    <div class="artist-stats">
                        <?php if ($followersStr !== 'N/A'): ?>
                        <span class="stat-badge">
                            <span class="stat-icon">👥</span>
                            <?= htmlspecialchars($followersStr) ?> Followers
                        </span>
                        <?php endif; ?>

                        <?php if ($popularity !== null): ?>
                        <span class="stat-badge">
                            <span class="stat-icon">⭐</span>
                            Popularity: <?= htmlspecialchars($popularity) ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($genre)): ?>
                        <span class="stat-badge">
                            <span class="stat-icon">🎵</span>
                            <?= htmlspecialchars(ucfirst($genre)) ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($numSongs !== null): ?>
                        <span class="stat-badge">
                            <span class="stat-icon">🎼</span>
                            <?= htmlspecialchars($numSongs) ?> songs
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bottone Vai alle canzoni -->
                <div class="button-container" style="margin-left: 16px;">
                    <a href="artist_songs.php?artist_name=<?= urlencode($name) ?>"
                       class="view-songs-btn">
                        🎤 View Songs
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($artists)): ?>
            <div style="text-align:center; color: white; padding: 40px 0; font-size: 18px;">
                No artists found for the current search.
            </div>
            <?php endif; ?>
        </div>
        <!-- end artist list -->

        <!-- Popup generico -->
        <div id="popup" class="popup-overlay" style="display: none;">
            <div class="popup-content">
                <p id="popup-message"></p>
                <button onclick="document.getElementById('popup').style.display='none'">Close</button>
            </div>
        </div>

    </div><!-- end sfondo -->

    <div id="toast"></div>

    <script>
    function showPopup(message, isSuccess) {
        document.getElementById('popup-message').textContent = message;
        const popup = document.getElementById('popup');
        popup.classList.toggle('error',   !isSuccess);
        popup.classList.toggle('success',  isSuccess);
        popup.style.display = 'flex';
    }
    </script>

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
