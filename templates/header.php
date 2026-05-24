<?php
$dark = $_COOKIE['theme'] ?? 'light';
$is_dark = $dark === 'dark';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SafeNest' ?></title>
    <link rel="icon" href="<?= $base ?? '' ?>image/logo2.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= $base ?? '' ?>static/style.css?v=<?= time() ?>">
</head>
<body class="<?= $is_dark ? 'dark' : '' ?>">
<div class="layout">

<aside class="sidebar <?= $is_dark ? 'dark-active' : '' ?>" id="sidebar">
    <div class="sidebar-logo">
        <img src="<?= $base ?? '' ?>image/logo2.png" alt="Logo" style="height: 32px; width: auto;">
        <span>SafeNest</span>
    </div>

    <?php if (($_SESSION['role'] ?? '') !== 'child'): ?>
        <a href="<?= $base ?? '' ?>parent_dashboard.php" class="nav-item <?= ($page === 'accueil') ? 'active' : '' ?>">🏠 Tableau de bord parent</a>
        <a href="<?= $base ?? '' ?>activities.php" class="nav-item <?= ($page === 'activites') ? 'active' : '' ?>">📊 Activités</a>
        <a href="<?= $base ?? '' ?>incidents.php" class="nav-item <?= ($page === 'incidents') ? 'active' : '' ?>">⚠️ Incidents</a>
    <?php else: ?>
        <a href="<?= $base ?? '' ?>child_dashboard.php" class="nav-item <?= ($page === 'accueil') ? 'active' : '' ?>">👦 Mon Espace</a>
        <a href="javascript:void(0)" onclick="document.getElementById('switchParentModal').style.display='flex'" class="nav-item">🛡️ Accès Parent</a>
    <?php endif; ?>
    <a href="<?= $base ?? '' ?>profil.php" class="<?= ($page === 'profil') ? 'active' : '' ?>">
        <span>👤</span> Profil
    </a>

    <div class="sidebar-bottom">
        <div class="sidebar-user" style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
            <img src="<?= $base ?? '' ?>image/<?= htmlspecialchars($_SESSION['avatar'] ?? 'avatar1.png') ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid var(--border);">
            <div style="line-height:1.3;">
                <span style="font-size:.7rem; color:var(--muted); text-transform:uppercase; font-weight:600;">Connecté</span><br>
                <span style="font-weight:700; color:var(--text);"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            </div>
        </div>

        <div class="toggle-wrap <?= $is_dark ? 'dark-active' : '' ?>" id="theme-toggle">
            <div class="toggle"></div>
            <span>Mode sombre</span>
        </div>

        <div style="margin-top:1rem;">
            <a href="<?= $base ?? '' ?>logout.php" class="btn btn-danger w-full" style="text-align:center;font-size:.82rem;padding:.45rem;">Déconnexion</a>
        </div>
    </div>
</aside>

<!-- Modal Switch Parent (Child Security) -->
<div id="switchParentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); z-index:9999; justify-content:center; align-items:center; padding: 1rem;">
    <div class="bg-[#2C345D] p-8 rounded-xl shadow-2xl relative w-full max-w-[400px] border border-[#6A64B6] text-white">
        <button onclick="document.getElementById('switchParentModal').style.display='none'" class="absolute justify-center items-center w-8 h-8 rounded-full top-4 right-4 bg-[#0B1B44] text-[#B7AED6] hover:text-white transition">&times;</button>
        <h2 class="text-xl font-bold mb-2 text-white">Périmètre Administrateur</h2>
        <p class="text-xs text-[#B7AED6] mb-6">Authentification d'urgence requise. Saisissez le numéro de téléphone de secours du Parent attaché à ce compte.</p>
        <form method="POST" action="<?= $base ?? '' ?>switch_parent.php" class="flex flex-col gap-4">
            <div>
                <input type="text" name="parent_phone" placeholder="Téléphone Administrateur" required class="w-full py-2.5 px-3 bg-[#0B1B44] border-2 border-[#6A64B6] rounded text-white font-bold tracking-widest text-sm outline-none focus:border-[#3CB4A8] shadow-inner text-center">
            </div>
            <button type="submit" class="w-full bg-[#EBCC84] hover:bg-[#d6b772] text-[#0B1B44] font-bold py-3 rounded-full transition shadow-xl mt-2">DÉVERROUILLER</button>
        </form>
    </div>
</div>

<main style="flex:1; padding:2rem; overflow-y:auto; overflow-x:hidden; margin-left:240px;" class="main-content">

<script>
    document.getElementById('theme-toggle').addEventListener('click', function() {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const toggle = this;
        const isDark = body.classList.toggle('dark');
        sidebar.classList.toggle('dark-active', isDark);
        toggle.classList.toggle('dark-active', isDark);
        document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + ';path=/;max-age=31536000';
    });
</script>
