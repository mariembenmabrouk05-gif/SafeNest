<?php
require 'config.php';
if (!logged_in()) { header('Location: index.php'); exit; }

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        try {
            $pdo->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?')->execute([$email ?: null, $phone ?: null, $_SESSION['user_id']]);
            $success = "Informations mises à jour.";
            
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    $error = 'Le mot de passe doit contenir au moins 6 caractères.';
                } elseif ($new_pass !== $confirm) {
                    $error = 'Les mots de passe ne correspondent pas.';
                } else {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($new_pass, PASSWORD_BCRYPT), $_SESSION['user_id']]);
                    $success = 'Profil et mot de passe mis à jour.';
                }
            }
        } catch (PDOException $e) { $error = "Erreur de mise à jour."; }
    } elseif (isset($_POST['set_avatar'])) {
        $avatar = $_POST['avatar_filename'] ?? '';
        if (!empty($_FILES['avatar_upload']['tmp_name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['avatar_upload']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                if (!is_dir('image/uploads')) {
                    mkdir('image/uploads', 0777, true);
                }
                $new_name = uniqid('pfp_') . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar_upload']['tmp_name'], 'image/uploads/' . $new_name)) {
                    $avatar = 'uploads/' . $new_name;
                }
            } else {
                $error = "Format d'image non supporté.";
            }
        }
        
        if ($avatar && !$error) {
            $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$avatar, $_SESSION['user_id']]);
            $_SESSION['avatar'] = $avatar;
            $success = "Avatar mis à jour avec succès.";
        }
    }
}

$user = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$current_avatar = $user['avatar'] ?? 'avatar1.png';

$title = 'Profil'; $base = ''; $page = 'profil';
include 'templates/header.php';
?>

<h1 class="page-title">Profil</h1>
<p class="page-sub">Gérez vos informations de compte</p>

<?php if ($error):   ?><div class="alert alert-error" style="max-width:700px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" style="max-width:700px;"><?= $success ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;width:100%;max-width:1200px;">

    <!-- Informations du Compte -->
    <div class="card">
        <h2 class="font-semibold mb-4" style="font-size:1rem;color:var(--text);">Informations du compte</h2>
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <div>
                <div style="font-size:.78rem;color:var(--muted);font-weight:500;text-transform:uppercase;margin-bottom:.25rem;">Nom d'utilisateur</div>
                <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            <div>
                <div style="font-size:.78rem;color:var(--muted);font-weight:500;text-transform:uppercase;margin-bottom:.25rem;">Rôle</div>
                <div style="font-weight:600;text-transform:capitalize;color:var(--text);"><?= $user['role'] === 'parent' ? 'Parent' : 'Enfant' ?></div>
            </div>
            <div>
                <div style="font-size:.78rem;color:var(--muted);font-weight:500;text-transform:uppercase;margin-bottom:.25rem;">Membre depuis</div>
                <div style="font-weight:600;color:var(--text);"><?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Récemment' ?></div>
            </div>
        </div>
    </div>

    <!-- Edit Profil Button -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
        <h2 class="font-semibold mb-2" style="font-size:1.1rem;color:var(--text);">Paramètres du Profil</h2>
        <p style="font-size:0.8rem; color:var(--muted); margin-bottom:1.5rem; max-width:80%;">Modifiez votre identifiant, accès e-mail, sécurité et numéro de téléphone de secours.</p>
        <button onclick="document.getElementById('editModal').style.display='flex'" class="btn w-full" style="max-width: 250px;">✏️ Modifier mes informations</button>
    </div>

    <!-- Avatar Picker -->
    <div class="card" style="grid-column: 1 / -1;">
        <h2 class="font-semibold mb-4" style="font-size:1rem;color:var(--text);">Personnaliser l'Avatar Circular Profile</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="set_avatar" value="1">
            
            <div style="display:flex; flex-wrap:wrap; gap:1.25rem; margin-bottom: 1.5rem;">
                <?php
                // Build the avatar default list
                $pfps = [];
                if (str_starts_with($current_avatar, 'uploads/')) {
                    $pfps[] = $current_avatar;
                }
                foreach (glob('image/*.png') as $file) {
                    $base = basename($file);
                    if (!in_array(strtolower($base), ['logo.png', 'logo2.png'])) {
                        $pfps[] = $base;
                    }
                }
                $pfps = array_unique($pfps);

                foreach ($pfps as $pfp):
                    $is_selected = ($current_avatar === $pfp);
                ?>
                <label style="cursor:pointer; display:flex; flex-direction:column; align-items:center;" class="hover:opacity-80 transition">
                    <input type="radio" name="avatar_filename" value="<?= htmlspecialchars($pfp) ?>" <?= $is_selected ? 'checked' : '' ?> style="display:none" onchange="this.form.submit()">
                    <img src="image/<?= htmlspecialchars($pfp) ?>" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 4px solid <?= $is_selected ? 'var(--primary)' : 'transparent' ?>; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                </label>
                <?php endforeach; ?>
            </div>
            
            <div style="border-top:1px dashed var(--border); padding-top:1.5rem;" class="mt-4">
                <label style="display:block;font-size:.85rem;color:var(--text);margin-bottom:.5rem;font-weight:600;">Téléverser une image personnalisée</label>
                <p style="font-size:.75rem;color:var(--muted);margin-bottom:1rem;">Elle sera automatiquement recadrée et arrondie.</p>
                <div style="display:flex; gap:1rem; align-items:center;">
                    <input type="file" name="avatar_upload" accept="image/*" class="text-sm">
                    <button type="submit" class="btn btn-green shadow-md">Appliquer l'Upload</button>
                </div>
            </div>
        </form>
    </div>

</div>

<!-- Modal Edit Profil -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:50; justify-content:center; align-items:center; padding: 1rem;">
    <div class="bg-[#2C345D] p-8 rounded-xl shadow-2xl relative w-full max-w-[500px] border border-white/10 text-white">
        <button onclick="document.getElementById('editModal').style.display='none'" class="absolute top-4 right-4 text-[#B7AED6] hover:text-white text-2xl transition">&times;</button>
        <h2 class="text-xl font-bold mb-6 text-white" style="font-family:'Poppins', sans-serif;">Modifier le profil</h2>
        <form method="POST" class="flex flex-col gap-4">
            <input type="hidden" name="update_info" value="1">
            
            <div>
                <label class="block text-xs font-bold text-[#B7AED6] uppercase tracking-wide mb-1">Adresse e-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full py-2 px-3 bg-[#0B1B44] border border-[#6A64B6] rounded text-white text-sm outline-none focus:border-[#3CB4A8]">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-[#B7AED6] uppercase tracking-wide mb-1">Téléphone Secours</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full py-2 px-3 bg-[#0B1B44] border border-[#6A64B6] rounded text-white text-sm outline-none focus:border-[#3CB4A8]">
            </div>
            
            <div class="border-t border-[#6A64B6]/30 mt-2 pt-4">
                <label class="block text-xs font-bold text-[#B7AED6] uppercase tracking-wide mb-1">Nouveau mot de passe (Optionnel)</label>
                <input type="password" name="new_password" placeholder="Laisser vide pour ignorer" class="w-full py-2 px-3 bg-[#0B1B44] border border-[#6A64B6] rounded text-white text-sm outline-none focus:border-[#3CB4A8]">
            </div>
            <div>
                <label class="block text-xs font-bold text-[#B7AED6] uppercase tracking-wide mb-1">Confirmer mot de passe</label>
                <input type="password" name="confirm_password" placeholder="..." class="w-full py-2 px-3 bg-[#0B1B44] border border-[#6A64B6] rounded text-white text-sm outline-none focus:border-[#3CB4A8]">
            </div>
            
            <button type="submit" class="w-full bg-[#EBCC84] hover:bg-[#d6b772] text-[#0B1B44] font-bold py-2.5 rounded transition mt-4">Sauvegarder les modifications</button>
        </form>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
