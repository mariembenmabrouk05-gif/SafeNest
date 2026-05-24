<?php
require 'config.php';
require_role('child');

$dark = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace — SafeNest</title>
    <link rel="icon" href="image/logo2.png" type="image/png">
    <link rel="stylesheet" href="static/style.css">
</head>
<body style="background-color: #75f2f6; margin: 0; padding: 0;">
<div style="display:flex;justify-content:center;align-items:center;min-height:100vh;padding:2rem;text-align:center;flex-direction:column;font-family: 'Inter', sans-serif;">
    <div style="margin-bottom:1rem; position:relative; display:inline-block;">
        <img src="image/<?= htmlspecialchars($_SESSION['avatar'] ?? 'avatar1.png') ?>" style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:5px solid rgba(255,255,255,0.4); box-shadow:0 12px 30px rgba(0,0,0,0.12);">
        <a href="profil.php" style="position:absolute; bottom:2px; right:2px; background:var(--green); color:white; border-radius:50%; width:34px; height:34px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.2); text-decoration:none; font-size:1rem;" title="Changer d'avatar">✏️</a>
    </div>
    <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:.5rem;color:#1a3638;">
        Bonjour, <?= htmlspecialchars($_SESSION['username']) ?> !
    </h1>
    <p style="color:#2a5053;max-width:450px;line-height:1.6;margin-bottom:2rem;font-size:1.05rem;font-weight:500;">
        Ici, tu peux te relaxer et rester tranquille. Tu es en sécurité, protégé de tout ce qu’il y a sur le web.
    </p>

    <img src="image/sun.png" alt="Soleil" style="max-width: 320px; width: 100%; margin-bottom: 2.5rem; ">
    <div style="display:flex; gap:1rem; align-items:center; justify-content:center; flex-wrap:nowrap; flex-direction:row;">
        <a href="parent_portal.php"
           style="display:inline-flex;align-items:center;gap:.45rem;padding:.55rem 1.1rem;
                  border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;
                  font-weight:600;color:var(--text);text-decoration:none;
                  background:var(--card);transition:border-color .2s,box-shadow .2s;"
           onmouseover="this.style.borderColor='var(--green)';this.style.boxShadow='0 0 0 3px rgba(16,185,129,.15)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
            👨‍👩‍👧 Parent
        </a>
        <a href="logout.php" class="btn btn-danger">Déconnexion</a>
    </div>
</div>

<style>
.confetti {
  position: fixed;
  z-index: 9999;
  pointer-events: none;
  border-radius: 2px;
}
</style>
<script>
    const colors = ['#FFC700', '#FF0000', '#2E3192', '#41BBC7', '#4ade80', '#f472b6'];
    
    function spawnConfetti() {
        const el = document.createElement('div');
        el.className = 'confetti';
        
        const isSide = Math.random() > 0.6;
        let startX, startY;
        
        if (!isSide) {
            // From top
            startX = Math.random() * 100 + 'vw';
            startY = '-10vh';
        } else {
            // From sides
            const isLeft = Math.random() > 0.5;
            startX = isLeft ? '-5vw' : '105vw';
            startY = Math.random() * 50 + 'vh'; // Top half
        }

        const size = Math.random() * 10 + 6;
        el.style.width = size + 'px';
        el.style.height = (size * 1.5) + 'px';
        el.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        el.style.left = startX;
        el.style.top = startY;
        
        document.body.appendChild(el);
        
        const animDuration = Math.random() * 3000 + 3000;
        const rotateEnd = Math.random() * 1080 - 540;
        
        // Calculate ending X to drift around
        let endX = (Math.random() - 0.5) * 300;
        if (isSide) {
             // make sure side confetti drifts inwards somewhat
             if (startX === '-5vw') endX += 200;
             else endX -= 200;
        }

        const endY = window.innerHeight + 100;

        el.animate([
            { transform: 'translate(0px, 0px) rotate(0deg)', opacity: 1 },
            { transform: `translate(${endX}px, ${endY}px) rotate(${rotateEnd}deg)`, opacity: 0.8 }
        ], {
            duration: animDuration,
            easing: 'linear',
        }).onfinish = () => el.remove();
    }
    
    // Spawn a confetti piece periodically
    setInterval(spawnConfetti, 120);
</script>
</body>
</html>
