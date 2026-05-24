<?php
require 'config.php';
if (logged_in()) { header('Location: index.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $role            = $_POST['role'] ?? '';
    $parent_username = trim($_POST['parent_username'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');

    if (strlen($username) < 3) {
        $error = "Nom d'utilisateur : minimum 3 caractères.";
    } elseif (strlen($password) < 6) {
        $error = 'Mot de passe : minimum 6 caractères.';
    } elseif ($password !== ($_POST['confirm'] ?? '')) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif ($role === 'child' && $parent_username === '') {
        $error = 'Un compte enfant doit être lié à un parent.';
    } elseif ($role === 'parent' && (empty($email) || empty($phone))) {
        $error = "L'email et le numéro de téléphone sont obligatoires pour un parent.";
    } else {
        $parent_id = null;
        if ($role === 'child') {
            $p = $pdo->prepare("SELECT id, role FROM users WHERE username = ?");
            $p->execute([$parent_username]);
            $parent = $p->fetch();
            if (!$parent || $parent['role'] !== 'parent') {
                $error = "Aucun compte parent trouvé ('$parent_username').";
            } else {
                $parent_id = $parent['id'];
            }
        }
        if (!$error) {
            try {
                $pdo->prepare('INSERT INTO users (username, password, role, parent_id, email, phone) VALUES (?,?,?,?,?,?)')
                    ->execute([$username, $password, $role, $parent_id, $email ?: null, $phone ?: null]);
                $success = 'Compte créé avec succès !';
            } catch (PDOException) {
                $error = "Ce nom d'utilisateur est déjà pris.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — SafeNest</title>
    <link rel="icon" href="image/logo2.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#0B1B44] to-[#6A64B6] flex items-center justify-center min-h-screen p-4 py-10 font-sans" style="font-family: 'Poppins', sans-serif;">
    <div class="bg-[#2C345D] border border-white/10 rounded-[1.5rem] shadow-2xl flex flex-col md:flex-row w-full max-w-[900px] overflow-hidden min-h-[550px]">
        
        <!-- Left Banner: Logo & Motto -->
        <div class="md:w-5/12 bg-[#0B1B44] p-10 flex flex-col items-center justify-center text-white text-center relative overflow-hidden hidden md:flex">
            <div class="absolute -top-20 -left-20 w-64 h-64 bg-[#3CB4A8]/10 rounded-full blur-3xl"></div>
            
            <img src="image/logo2.png" alt="Logo" class="h-[120px] mb-6 drop-shadow-xl z-10 relative">
            <h1 class="text-3xl font-extrabold mb-3 tracking-tight z-10 relative">SafeNest</h1>
            <p class="text-[#B7AED6] text-sm leading-relaxed z-10 relative">Rejoignez-nous pour construire un internet plus sûr pour ceux que vous aimez.</p>
        </div>

        <!-- Right Side: Interaction Layout -->
        <div class="md:w-7/12 p-8 lg:p-12 flex flex-col justify-center bg-[#2C345D] relative text-white">
            <div class="mb-6 text-center md:text-left">
                <h2 class="text-2xl font-bold text-white">Créer un compte</h2>
                <p class="text-[#B7AED6] text-sm mt-1">Rejoignez l'écosystème SafeNest</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/20 text-red-200 px-4 py-3 rounded-lg text-sm mb-6 border border-red-500/30 font-medium">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-500/20 text-green-200 px-4 py-3 rounded-lg text-sm mb-6 border border-green-500/30 font-medium">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-3">
                <div>
                    <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1">Nom d'utilisateur</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm">
                </div>

                <div id="parent-contact-fields">
                    <div>
                        <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Adresse E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                            class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm">
                    </div>
                    <div>
                        <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Téléphone Sécurité</label>
                        <input type="text" name="phone" placeholder="Format: +216 xxxxxxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                            class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Rôle</label>
                    <select name="role" required id="role-select" onchange="toggleParentField()" 
                            class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm appearance-none">
                        <option class="bg-[#0B1B44]" value="parent" <?= ($_POST['role'] ?? '') === 'parent' ? 'selected' : '' ?>>👨‍👩‍👧 Administrateur (Parent)</option>
                        <option class="bg-[#0B1B44]" value="child"  <?= ($_POST['role'] ?? '') === 'child'  ? 'selected' : '' ?>>👦 Utilisateur (Enfant)</option>
                    </select>
                </div>
                <div id="parent-field" style="display:none;">
                    <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Nom du Parent</label>
                    <input type="text" name="parent_username" placeholder="Requis pour lier les comptes" value="<?= htmlspecialchars($_POST['parent_username'] ?? '') ?>" 
                           class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm placeholder-[#6A64B6]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Mot de passe</label>
                        <input type="password" name="password" required 
                               class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm">
                    </div>
                    <div>
                        <label class="block text-[0.65rem] font-bold tracking-widest uppercase text-[#B7AED6] mb-1 mt-3">Confirmer</label>
                        <input type="password" name="confirm" required 
                               class="w-full py-1.5 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#3CB4A8] transition-colors text-sm">
                    </div>
                </div>
                
                <div class="pt-5">
                    <button type="submit" class="w-[200px] mx-auto md:mx-0 block bg-[#EBCC84] hover:bg-[#d6b772] text-[#0B1B44] font-bold py-3 rounded-full transition shadow-lg">S'inscrire</button>
                </div>
            </form>

            <p class="text-center md:text-left text-sm mt-8 text-[#B7AED6]">
                Déjà inscrit(e) ? <a href="index.php" class="text-[#3CB4A8] font-bold hover:underline">Se connecter</a>
            </p>
        </div>
    </div>

    <script>
    function toggleParentField() {
        const role = document.getElementById('role-select').value;
        const parentField = document.getElementById('parent-field');
        const parentInput = parentField.querySelector('input');
        
        const contactFields = document.getElementById('parent-contact-fields');
        const emailInput = contactFields.querySelector('input[name="email"]');
        const phoneInput = contactFields.querySelector('input[name="phone"]');

        if (role === 'child') {
            parentField.style.display = 'block';
            parentInput.required = true;
            
            contactFields.style.display = 'none';
            emailInput.required = false;
            phoneInput.required = false;
        } else {
            parentField.style.display = 'none';
            parentInput.required = false;

            contactFields.style.display = 'block';
            emailInput.required = true;
            phoneInput.required = true;
        }
    }
    toggleParentField();
    </script>
</body>
</html>
