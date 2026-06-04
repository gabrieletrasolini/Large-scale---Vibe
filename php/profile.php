<?php 
session_start();
include 'header.php';
$manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

$username = $_SESSION['utente_loggato']; // o qualunque username attivo

// 1. Carica l'utente
$filter = ['username' => $username];
$query = new MongoDB\Driver\Query($filter);
$userCursor = $manager->executeQuery('admin.User', $query);
$user = current($userCursor->toArray());

if (!$user) {
    die("Utente non trovato");
}

// 1. Raccogli tutti gli ID dei brani
$all_ids = [];

foreach ($user->preferiti->brani ?? [] as $b) {
    $all_ids[] = $b->id_brano;
}

foreach ($user->playlist_personali ?? [] as $playlist) {
    foreach ($playlist->brani ?? [] as $b) {
        $all_ids[] = $b->id_brano;
    }
}

$all_ids = array_values(array_unique($all_ids));

// 3. Cerca in entrambe le collezioni
$tracks = [];

if (!empty($all_ids)) {
    // Prima collezione
    $query1 = new MongoDB\Driver\Query(['Track_ID' => ['$in' => $all_ids]]);
    $cursor1 = $manager->executeQuery('admin.Songs', $query1);
    foreach ($cursor1 as $track) {
        $tracks[$track->Track_ID] = $track;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- basic -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- site metas -->
    <title>Profile</title>
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
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script><![endif]-->
    <style> 
        .sfondo {
    background-image: url('images/sfondobody.jpg'); 
    background-repeat: no-repeat;
    background-position: center;
    min-height: 100vh;
    margin-top:auto;
    background-attachment: fixed;
    background-size: cover;
}    
.song-list { display: flex; flex-direction: column; gap: 12px; max-width: 800px; margin: auto;opacity: 0.8;}
.song-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        } 
        .song-info { flex: 1;}


        /* ── Generic popup overlay ── */
.popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; }
.popup-content { background: white; padding: 0; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.35); text-align: center; max-width: 560px; width: 90%; overflow: hidden; }
.popup-header { padding: 18px 24px 14px; display: flex; align-items: center; justify-content: center; gap: 10px; }
.popup-header.success { background: #e8f5e9; border-bottom: 2px solid #1db954; }
.popup-header.error   { background: #fdecea; border-bottom: 2px solid #e53935; }
.popup-header.neutral { background: #f5f5f5; border-bottom: 2px solid #ccc; }
.popup-icon { font-size: 26px; line-height: 1; }
.popup-header h2 { font-size: 17px; font-weight: 700; margin: 0; color: #222; }
.popup-body { padding: 18px 28px 22px; }
.popup-body p { font-size: 15px; color: #444; margin-bottom: 18px; line-height: 1.5; }
.add-button { padding: 8px 12px; background: #1db954; color: white; border: none; border-radius: 6px; cursor: pointer; display: inline-block; margin-top: 8px; }
.add-button:hover { background: #169c44; }
    </style>
</head>
<!-- body -->

<body class="main-layout contact-page">
    <!-- loader  -->
    <div class="loader_bg">
        <div class="loader"><img src="images/loading.gif" alt="#" /></div>
    </div>

    <div class="sfondo">
       <h1 style="color: white; text-align: center; width: 100%; font-weight:bold;">PROFILE</h1>
       <div class="song-list">
        <div class="song-card">
            <div class="song-info">
       <h2 style="font-weight: bold;">Profile </h2>
<p>Name: <?= $user->profilo->nome ?> </p>
<p>Surname: <?= $user->profilo->cognome ?></p>
<p>Email: <?= $user->profilo->email ?></p>
<p>Gender: <?= $user->profilo->genere ?></p>
<?php
// Supponiamo $user->profilo->data_nascita sia la stringa ISO 8601
$dataNascitaStr = $user->profilo->data_nascita;

if (!empty($dataNascitaStr)) {
    $date = new DateTime($dataNascitaStr);
    echo '<p>Date of Birth: ' . $date->format('d/m/Y') . '</p>';
} else {
    echo '<p>Data di nascita non disponibile</p>';
}
?>
<p>Bio: <?= $user->profilo->bio ?></p>
            </div>
            </div>
            <div class="song-card">
            <div class="song-info">
<h3 style="font-weight: bold;">🎧 Favorite Tracks</h3>
<ul>
<?php foreach ($user->preferiti->brani ?? [] as $b): ?>
    <?php 
        $id = $b->id_brano;
        $track = $tracks[$id] ?? null;
        if (isset($track->{'artists'})) {
            $artistName = $track->{'artists'};
        } elseif (isset($track->{'Artist_Name'})) {
            $artistName = $track->{'Artist_Name'};
        } else {
            $artistName = 'Unknown artist';
        }        
    ?>
    <li> <?=  $track ? $track->Song_Title . " - " . $artistName : 'Brano not found'    ?>
<?php if ($track): ?>
        <form method="post" action="rimuovi_preferito.php" style="display:inline;">
            <input type="hidden" name="id_brano" value="<?= $id ?>">
            <button type="submit" style="background:none;border:none;color:red;cursor:pointer;" title="Remove from favorites" >🗑️</button>
        </form>
    <?php endif; ?></li>
<?php endforeach; ?>
</ul>
            </div>
            </div>
            <div class="song-card">
            <div class="song-info">
            <div style="display: flex; align-items: center; gap: 10px;">
<h3 style="font-weight: bold; text-align: center;margin: 0;">🎶 <?php echo $_SESSION['utente_loggato'];?>'s Playlist</h3>
<button id="addPlaylistBtn" title="Crea nuova playlist" style="
        background-color: green;
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        font-size: 20px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: -7px;
    ">+</button></div>
<?php foreach ($user->playlist_personali ?? [] as $playlist): ?>
    <div style="display: flex; align-items: center; gap: 10px;">
    <h4><?= $playlist->nome_playlist ?></h4>
<form method="post" action="rimuovi_playlist.php" onsubmit="return chiediConferma(event, this, '<?= htmlspecialchars($playlist->nome_playlist, ENT_QUOTES) ?>');" style="margin: 0;">        <input type="hidden" name="nome_playlist" value="<?= htmlspecialchars($playlist->nome_playlist) ?>">
        <button type="submit" title="Rimuovi playlist" style="
            background: none;
            border: none;
            color: red;
            font-size: 20px;
            cursor: pointer;
            display:flex;
            margin-top: -7px;

        ">🗑️</button>
    </form>
    </div>
    <p><?= $playlist->descrizione ?></p>
    <ul>
    <?php foreach ($playlist->brani ?? [] as $b): ?>
        <?php $track = $tracks[$b->id_brano] ?? null; ?>
    <li> <?= $track ? $track->Song_Title . " - " . (isset($track->artists) ? $track->artists : $track->{'Artist_Name'}) : "Brano non trovato" ?>    
    <?php if ($track): ?>
        <form method="post" action="rimuovi_da_playlist.php" style="display:inline;">
            <input type="hidden" name="id_brano" value="<?= $b->id_brano ?>">
            <input type="hidden" name="nome_playlist" value="<?= htmlspecialchars($playlist->nome_playlist) ?>">
            <button type="submit" style="background:none;border:none;color:red;cursor:pointer;" title="Rimuovi dalla playlist">🗑️</button>
        </form>
    <?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
            </div>
        </div>
       </div>
    </div>
<!-- MODALE PER CREARE UNA NUOVA PLAYLIST -->
<div id="playlistModal" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
">
    <div style="
        background: white;
        padding: 20px;
        border-radius: 10px;
        width: 300px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        position: relative;
    ">
        <h3>Crea Playlist</h3>
        <form method="POST" action="crea_playlist.php">
            <label>Nome Playlist:</label><br>
            <input type="text" name="nome_playlist" required><br><br>
            
            <label>Descrizione:</label><br>
            <textarea name="descrizione" rows="3" style="width: 100%;"></textarea><br><br>
            
            <button type="submit" style="background-color: green; color: white; padding: 5px 10px; border: none;">Crea</button>
            <button type="button" onclick="closeModal()" style="margin-left: 10px;">Annulla</button>
        </form>
    </div>
</div>
<div id="popup" class="popup-overlay" style="display: none;">
    <div class="popup-content">
        <div id="popup-header" class="popup-header neutral">
            <span id="popup-icon" class="popup-icon"></span>
            <h2 id="popup-title"></h2>
        </div>
        <div class="popup-body">
            <p id="popup-message"></p>
            <button class="add-button" onclick="closePopup()">✕ Chiudi</button>
        </div>
    </div>
</div>
<div id="confirm-popup" class="popup-overlay" style="display: none;">
    <div class="popup-content" style="max-width: 420px;">
        <div class="popup-header error">
            <span class="popup-icon">⚠️</span>
            <h2 style="font-size: 17px;">Confirm Deletion</h2>
        </div>
        <div class="popup-body">
            <p>Are you sure you want to delete the playlist <strong id="nome-playlist-conferma"></strong>? This action cannot be undone.</p>
            <div style="display: flex; justify-content: center; gap: 15px; margin-top: 20px;">
                <button class="add-button" style="background: #999; margin: 0;" onclick="chiudiConferma()">Cancel</button>
                <button class="add-button" style="background: #e53935; margin: 0;" onclick="eseguiEliminazione()">Yes, delete</button>
            </div>
        </div>
    </div>
</div>
    <!-- end footer -->
    <!-- Javascript files-->
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.0.0.min.js"></script>
    <script src="js/plugin.js"></script>
    <!-- sidebar -->
    <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="https:cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
    <script>
        $(document).ready(function() {
            $(".fancybox").fancybox({
                openEffect: "none",
                closeEffect: "none"
            });

            $(".zoom").hover(function() {

                $(this).addClass('transition');
            }, function() {

                $(this).removeClass('transition');
            });
        });
    </script>
    <script>
    const modal = document.getElementById("playlistModal");

    document.getElementById("addPlaylistBtn").addEventListener("click", function () {
        modal.style.display = "flex";
    });

    function closeModal() {
        modal.style.display = "none";
    }

    // Chiudi se clicchi fuori
    window.onclick = function(event) {
        if (event.target === modal) {
            closeModal();
        }
    }
</script>

<script>
  // 1. Funzioni per accendere/spegnere il nuovo popup grafico
  function showPopup(message, isSuccess) {
      const header  = document.getElementById('popup-header');
      const icon    = document.getElementById('popup-icon');
      const title   = document.getElementById('popup-title');
      const msgEl   = document.getElementById('popup-message');

      header.className = 'popup-header ' + (isSuccess ? 'success' : 'error');
      icon.textContent  = isSuccess ? '✅' : '❌';
      title.textContent = isSuccess ? 'Fatto!' : 'Attenzione';
      msgEl.textContent = message;

      const popup = document.getElementById('popup');
      popup.style.display = 'flex';
  }

  function closePopup() {
      document.getElementById('popup').style.display = 'none';
  }

  // 2. Logica che legge l'URL (es: ?success=brano_rimosso)
  function getParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  const success = getParam("success");
  if (success) {
    const messages = {
      "brano_rimosso": "Track removed successfully.",
        "playlist_rimossa": "Playlist deleted.",
        "brano_aggiunto": "Track added successfully.",
        "playlist_creata": "Playlist created successfully!",
        "playlist_duplicata": "A playlist with this name already exists!",
        "errore_nome_vuoto": "Please enter a valid name for the playlist."
    };

    // Capisce se è un errore o un successo per colorare il popup
    const isError = (success === "playlist_duplicata" || success === "errore_nome_vuoto");
    const message = messages[success] || "Operazione completata.";

    // Mostra il popup
    showPopup(message, !isError);

    // Chiudi in automatico dopo 3 secondi e mezzo
    setTimeout(() => {
      closePopup();
    }, 3500);

    // Pulisce l'URL senza ricaricare la pagina
    const url = new URL(window.location);
    url.searchParams.delete("success");
    window.history.replaceState({}, document.title, url);
  }
</script>
<script>
    // --- LOGICA POPUP DI CONFERMA ELIMINAZIONE ---
  let formDaInviare = null; // Variabile per "ricordarsi" quale form stavamo inviando

  function chiediConferma(event, form, nomePlaylist) {
      event.preventDefault(); // Blocca l'invio immediato del form
      formDaInviare = form;   // Salva il form
      
      // Inserisce il nome della playlist nel testo del popup
      document.getElementById('nome-playlist-conferma').textContent = nomePlaylist;
      
      // Mostra il popup di conferma
      document.getElementById('confirm-popup').style.display = 'flex';
      return false;
  }

  function chiudiConferma() {
      document.getElementById('confirm-popup').style.display = 'none';
      formDaInviare = null; // Resetta la memoria
  }

  function eseguiEliminazione() {
      if (formDaInviare) {
          formDaInviare.submit(); // Esegue l'invio vero e proprio al file PHP
      }
  }
    </script>
</body>

</html>