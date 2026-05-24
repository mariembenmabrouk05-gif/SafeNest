<?php
require 'config.php';

$error = '';

if (logged_in()) {
    header('Location: ' . ($_SESSION['role'] === 'parent' ? 'parent_dashboard.php' : 'child_dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([trim($_POST['username'])]);
        $user = $stmt->fetch();
        if ($user && password_verify($_POST['password'], $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['avatar']   = $user['avatar'] ?? 'avatar1.png';
            header('Location: ' . ($user['role'] === 'parent' ? 'parent_dashboard.php' : 'child_dashboard.php'));
            exit;
        }
        $error = 'Identifiant ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — SafeNest</title>
    <link rel="icon" href="image/logo2.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="image/favicon.ico" type="image/x-icon" />  
    <link rel="shortcut icon" href="image/favicon.ico" type="image/x-icon" /> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#0B1B44] to-[#6A64B6] flex items-center justify-center min-h-screen p-4 font-sans" style="font-family: 'Poppins', sans-serif;">
    <div class="bg-[#2C345D] border border-white/10 rounded-[1.5rem] shadow-2xl flex flex-col md:flex-row w-full max-w-[900px] overflow-hidden min-h-[500px]">
        
        <!-- Left Banner: Logo & Motto -->
        <div class="md:w-5/12 bg-[#0B1B44] p-10 flex flex-col items-center justify-center text-white text-center relative overflow-hidden">
            <div class="absolute -top-20 -left-20 w-64 h-64 bg-[#3CB4A8]/10 rounded-full blur-3xl"></div>
            
            <img src="image/logo2.png" alt="Logo" class="h-[140px] mb-6 drop-shadow-xl z-10 relative">
            <h1 class="text-3xl font-extrabold mb-3 tracking-tight z-10 relative">SafeNest</h1>
            <p class="text-[#B7AED6] text-sm leading-relaxed z-10 relative">Périmètre digital hautement sécurisé pour vos enfants. Surveillez, alertez, éduquez.</p>
        </div>

        <!-- Right Side: Interaction Layout -->
        <div class="md:w-7/12 p-10 lg:p-16 flex flex-col justify-center bg-[#2C345D] relative text-white">
            <div class="mb-10 text-center md:text-left">
                <h2 class="text-2xl font-bold text-white">Bienvenue</h2>
                <p class="text-[#B7AED6] text-sm mt-1">Connectez-vous pour commencer</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/20 text-red-200 px-4 py-3 rounded-lg text-sm mb-6 border border-red-500/30 font-medium">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-[#B7AED6] mb-1">Nom d'utilisateur</label>
                    <input type="text" name="username" required autofocus 
                           class="w-full py-2 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors"
                           style="box-shadow: none;">
                </div>
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-6">Mot de passe</label>
                    <input type="password" name="password" required 
                           class="w-full py-2 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors"
                           style="box-shadow: none;">
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="w-[200px] mx-auto md:mx-0 block bg-[#3CB4A8] hover:bg-[#2e948a] text-white font-bold py-3 rounded-full transition shadow-lg mt-2">S'authentifier</button>
                </div>
            </form>

            <p class="text-center md:text-left text-sm mt-8 text-[#B7AED6]">
                Mot de passe oublié ? <a href="forgot_password.php" class="text-[#3CB4A8] font-bold hover:underline">Réinitialiser</a>
            </p>
            <p class="text-center md:text-left text-sm mt-3 text-[#B7AED6]">
                Nouveau ici ? <a href="signup.php" class="text-[#EBCC84] font-bold hover:underline">Créer un compte</a>
            </p>
        </div>
    </div>
</body>
</html>
