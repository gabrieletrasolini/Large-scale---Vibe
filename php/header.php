<?php
// header.php — include questo file in cima a ogni pagina con:
// <?php include 'header.php'; 
//
// Richiede che session_start() sia già stato chiamato nel file padre.

// Determina la pagina corrente per evidenziare la voce attiva nel menu
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($pages) {
    global $current_page;
    if (!is_array($pages)) $pages = [$pages];
    return in_array($current_page, $pages) ? 'active' : '';
}
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
?>

<style>
    /* ════════════════════════════════════════════
       VIBE HEADER
    ════════════════════════════════════════════ */
    @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap');

    :root {
        --hdr-bg:       #0a0a0f;
        --hdr-border:   rgba(201, 151, 0, 0.25);
        --gold:         #C99700;
        --gold-light:   #f0c030;
        --text-nav:     #b0b8c8;
        --text-hover:   #ffffff;
        --accent-green: #1db954;
        --hdr-height:   64px;
    }

    /* Reset margin/padding per evitare spazi indesiderati */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── Barra principale ── */
    .vibe-header {
        position: sticky;
        top: 0;
        z-index: 999;
        width: 100%;
        height: var(--hdr-height);
        background: var(--hdr-bg);
        border-bottom: 1px solid var(--hdr-border);
        display: flex;
        align-items: center;
        padding: 0 32px;
        gap: 0;
        /* sottile glow dorato sotto */
        box-shadow: 0 1px 0 var(--hdr-border), 0 4px 24px rgba(0,0,0,0.6);
    }

    /* ── Logo ── */
    .vibe-logo {
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        flex-shrink: 0;
        margin-right: 40px;
    }
    .vibe-logo-text {
        font-family: 'Bebas Neue', sans-serif;
        font-size: 32px;
        letter-spacing: 4px;
        color: var(--gold);
        line-height: 1;
        transition: color 0.2s;
    }
    .vibe-logo:hover .vibe-logo-text { color: var(--gold-light); }
    .vibe-logo img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1.5px solid var(--gold);
        object-fit: cover;
        transition: border-color 0.2s;
    }
    .vibe-logo:hover img { border-color: var(--gold-light); }

    /* ── Nav centrale ── */
    .vibe-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 1;
    }
    .vibe-nav a {
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--text-nav);
        text-decoration: none;
        padding: 8px 14px;
        border-radius: 6px;
        position: relative;
        transition: color 0.2s, background 0.2s;
        white-space: nowrap;
    }
    .vibe-nav a:hover {
        color: var(--text-hover);
        background: rgba(255,255,255,0.06);
    }
    /* voce attiva */
    .vibe-nav a.active {
        color: var(--gold);
    }
    .vibe-nav a.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 14px;
        right: 14px;
        height: 2px;
        background: var(--gold);
        border-radius: 2px 2px 0 0;
    }

    /* ── Separatore prima del gruppo utente ── */
    .vibe-nav-sep {
        flex: 1;   /* spinge il gruppo utente a destra */
    }

    /* ── Gruppo utente (destra) ── */
    .vibe-user {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
    }
    /* Badge username */
    .vibe-username {
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--text-nav);
        text-decoration: none;
        padding: 7px 14px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: color 0.2s, border-color 0.2s, background 0.2s;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .vibe-username:hover {
        color: var(--text-hover);
        border-color: rgba(255,255,255,0.25);
        background: rgba(255,255,255,0.06);
    }
    .vibe-username svg { opacity: 0.6; flex-shrink: 0; }

    /* Bottone Logout */
    .vibe-logout {
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #888;
        text-decoration: none;
        padding: 7px 14px;
        border-radius: 6px;
        border: 1px solid transparent;
        transition: color 0.2s, border-color 0.2s, background 0.2s;
    }
    .vibe-logout:hover {
        color: #ff6b6b;
        border-color: rgba(255,107,107,0.3);
        background: rgba(255,107,107,0.08);
    }

    /* Bottone Login */
    .vibe-login {
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #0a0a0f;
        background: var(--gold);
        text-decoration: none;
        padding: 8px 18px;
        border-radius: 6px;
        transition: background 0.2s, transform 0.15s;
    }
    .vibe-login:hover {
        background: var(--gold-light);
        transform: translateY(-1px);
    }

    /* Badge Admin */
    .vibe-admin-badge {
        font-family: 'DM Sans', sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--accent-green);
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid rgba(29,185,84,0.35);
        background: rgba(29,185,84,0.08);
        transition: background 0.2s, border-color 0.2s;
    }
    .vibe-admin-badge:hover {
        background: rgba(29,185,84,0.16);
        border-color: rgba(29,185,84,0.6);
        color: var(--accent-green);
    }

    /* ── Mobile hamburger (nascosto su desktop) ── */
    .vibe-hamburger {
        display: none;
        flex-direction: column;
        gap: 5px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        margin-left: auto;
    }
    .vibe-hamburger span {
        display: block;
        width: 22px;
        height: 2px;
        background: var(--text-nav);
        border-radius: 2px;
        transition: background 0.2s;
    }
    .vibe-hamburger:hover span { background: var(--text-hover); }

    /* ── Mobile menu ── */
    .vibe-mobile-menu {
        display: none;
        flex-direction: column;
        background: var(--hdr-bg);
        border-bottom: 1px solid var(--hdr-border);
        padding: 12px 24px 20px 24px;
        gap: 4px;
    }
    .vibe-mobile-menu a {
        font-family: 'DM Sans', sans-serif;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-nav);
        text-decoration: none;
        padding: 10px 12px;
        border-radius: 6px;
        transition: color 0.2s, background 0.2s;
    }
    .vibe-mobile-menu a:hover, .vibe-mobile-menu a.active {
        color: var(--text-hover);
        background: rgba(255,255,255,0.06);
    }
    .vibe-mobile-menu a.active { color: var(--gold); }
    .vibe-mobile-menu.open { display: flex; }

    @media (max-width: 860px) {
        .vibe-nav, .vibe-user { display: none; }
        .vibe-hamburger { display: flex; }
        .vibe-header { padding: 0 20px; }
    }
</style>

<!-- ════════════ HEADER ════════════ -->
<header>
    <div class="vibe-header">

        <!-- Logo -->
        <a href="index.php" class="vibe-logo">
            <span class="vibe-logo-text">VIBE</span>
            <img src="images/logo2.jpg" alt="Vibe logo">
        </a>

        <!-- Navigazione principale -->
        <nav class="vibe-nav">
            <a href="index.php"   class="<?= isActive('index.php') ?>">Home</a>
            <a href="songs.php"   class="<?= isActive('songs.php') ?>">Songs Archive</a>
            <a href="Artist.php"  class="<?= isActive('Artist.php') ?>">Artists</a>
            <a href="trend.php"   class="<?= isActive('trend.php') ?>">Trend</a>

            <?php if ($isAdmin): ?>
                <a href="admin.php" class="<?= isActive('admin.php') ?>">Admin</a>
            <?php endif; ?>

            <!-- Spazio flessibile che spinge utente a destra -->
            <span class="vibe-nav-sep"></span>
        </nav>

        <!-- Gruppo utente (destra) -->
        <div class="vibe-user">
            <?php if (!empty($_SESSION['utente_loggato'])): ?>

                <?php if ($isAdmin): ?>
                    <a href="admin.php" class="vibe-admin-badge">Admin</a>
                <?php endif; ?>

                <a href="profile.php" class="vibe-username">
                    <!-- icona persona SVG inline -->
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?= htmlspecialchars($_SESSION['utente_loggato']) ?>
                </a>

                <a href="logout.php" class="vibe-logout">Logout</a>

            <?php else: ?>
                <a href="login.php" class="vibe-login">Login</a>
            <?php endif; ?>
        </div>

        <!-- Hamburger mobile -->
        <button class="vibe-hamburger" onclick="toggleMobileMenu()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    
</header>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('vibeMobileMenu');
    menu.classList.toggle('open');
}
</script>
