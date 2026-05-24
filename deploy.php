<?php
require 'config.php';
require_role('parent');

// ── Auto-detect the app's public base URL ─────────────────────
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME']);
$base_url = rtrim("$proto://$host$script", '/');

// ── Load this parent's children ───────────────────────────────
$children = $pdo->prepare("SELECT id, username FROM users WHERE role = 'child' AND parent_id = ? ORDER BY username");
$children->execute([$_SESSION['user_id']]);
$children = $children->fetchAll();

// ── Generate / retrieve a monitor token for a child ──────────
function get_or_create_token(PDO $pdo, int $child_id): string {
    $row = $pdo->prepare("SELECT monitor_token FROM users WHERE id = ?");
    $row->execute([$child_id]);
    $row = $row->fetch();
    if (!empty($row['monitor_token'])) return $row['monitor_token'];
    $token = bin2hex(random_bytes(32)); // 64-char hex string
    $pdo->prepare("UPDATE users SET monitor_token = ? WHERE id = ?")->execute([$token, $child_id]);
    return $token;
}

$selected_child = null;
$token          = null;
$download_url   = null;
$custom_url     = $_GET['url'] ?? $base_url;

if (isset($_GET['child_id'])) {
    $cid = (int)$_GET['child_id'];
    // Make sure this child belongs to the logged-in parent
    $check = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND parent_id = ? AND role = 'child'");
    $check->execute([$cid, $_SESSION['user_id']]);
    $selected_child = $check->fetch();
    if ($selected_child) {
        $token = get_or_create_token($pdo, $cid);
        $download_url = "generate_download.php?child_id={$cid}&token=" . urlencode($token)
                       . "&url=" . urlencode($custom_url);
    }
}

$title = 'Déployer le moniteur'; $base = ''; $page = 'deploy';
include 'templates/header.php';
?>

<h1 class="page-title">🖥️ Déployer le moniteur</h1>
<p class="page-sub">Générez un script prêt à l'emploi pour le PC de votre enfant.</p>

<?php if (empty($children)): ?>
<div class="card" style="text-align:center;padding:3rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">👦</div>
    <p style="font-weight:600;margin-bottom:.5rem;">Aucun enfant lié à votre compte</p>
    <p style="color:var(--muted);font-size:.875rem;margin-bottom:1.5rem;">
        Demandez à votre enfant de créer un compte en indiquant votre nom d'utilisateur
        (<strong><?= htmlspecialchars($_SESSION['username']) ?></strong>) lors de l'inscription.
    </p>
    <a href="signup.php" class="btn btn-green">Créer un compte enfant</a>
</div>
<?php else: ?>

<!-- Step 1: pick child + server URL -->
<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">① Sélectionnez un enfant et vérifiez l'URL du serveur</h2>
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.4rem;font-weight:500;">Enfant</label>
            <select name="child_id" required>
                <option value="">— choisir —</option>
                <?php foreach ($children as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (isset($_GET['child_id']) && $_GET['child_id'] == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['username']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:2;min-width:260px;">
            <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.4rem;font-weight:500;">
                URL publique du serveur
                <span style="font-weight:400;font-size:.78rem;"> — ce que le PC de l'enfant utilisera pour se connecter</span>
            </label>
            <input type="url" name="url" value="<?= htmlspecialchars($custom_url) ?>" placeholder="https://votre-serveur.com/safenest">
        </div>
        <div>
            <button type="submit" class="btn btn-green">Générer le paquet ↓</button>
        </div>
    </form>
</div>

<?php if ($selected_child && $token): ?>

<!-- Step 2: download -->
<div class="card" style="margin-bottom:1.5rem;border:1px solid var(--green);background:rgba(16,185,129,.06);">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:.75rem;">② Téléchargez le paquet d'installation</h2>
    <p style="color:var(--muted);font-size:.875rem;margin-bottom:1.25rem;">
        Le paquet contient le script de surveillance pré-configuré pour
        <strong><?= htmlspecialchars($selected_child['username']) ?></strong>
        ainsi que les installateurs pour Windows, Mac et Linux.
    </p>
    <a href="<?= htmlspecialchars($download_url) ?>" class="btn btn-green" style="display:inline-flex;align-items:center;gap:.5rem;">
        ⬇ Télécharger protect_monitor_<?= htmlspecialchars($selected_child['username']) ?>.zip
    </a>
    <p style="color:var(--muted);font-size:.78rem;margin-top:1rem;">
        🔒 Ce paquet contient un jeton d'authentification unique lié à ce compte enfant.
        Ne le partagez pas.
    </p>
</div>

<!-- Step 3: instructions -->
<div class="card">
    <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">③ Instructions d'installation sur le PC de l'enfant</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">

        <div style="border:1px solid var(--border);border-radius:8px;padding:1rem;">
            <div style="font-size:1.5rem;margin-bottom:.5rem;">🪟 Windows</div>
            <ol style="color:var(--muted);font-size:.82rem;padding-left:1.2rem;line-height:1.8;">
                <li>Décompressez le fichier ZIP</li>
                <li>Double-cliquez sur <code>install_windows.bat</code></li>
                <li>Acceptez les invites (installation Python + dépendances)</li>
                <li>Double-cliquez sur <code>start_monitor.bat</code> pour démarrer</li>
            </ol>
            <p style="color:var(--muted);font-size:.78rem;margin-top:.75rem;">
                💡 Pour démarrer automatiquement, copiez <code>start_monitor.bat</code> dans
                <code>shell:startup</code>
            </p>
        </div>

        <div style="border:1px solid var(--border);border-radius:8px;padding:1rem;">
            <div style="font-size:1.5rem;margin-bottom:.5rem;">🍎 macOS</div>
            <ol style="color:var(--muted);font-size:.82rem;padding-left:1.2rem;line-height:1.8;">
                <li>Décompressez le fichier ZIP</li>
                <li>Ouvrez le Terminal dans ce dossier</li>
                <li>Exécutez <code>bash install_mac.sh</code></li>
                <li>Puis <code>bash start_monitor.sh</code></li>
            </ol>
            <p style="color:var(--muted);font-size:.78rem;margin-top:.75rem;">
                💡 Pour démarrer automatiquement : <code>bash install_mac.sh --autostart</code>
            </p>
        </div>

        <div style="border:1px solid var(--border);border-radius:8px;padding:1rem;">
            <div style="font-size:1.5rem;margin-bottom:.5rem;">🐧 Linux</div>
            <ol style="color:var(--muted);font-size:.82rem;padding-left:1.2rem;line-height:1.8;">
                <li>Décompressez le fichier ZIP</li>
                <li>Ouvrez un terminal dans ce dossier</li>
                <li>Exécutez <code>bash install_linux.sh</code></li>
                <li>Puis <code>bash start_monitor.sh</code></li>
            </ol>
            <p style="color:var(--muted);font-size:.78rem;margin-top:.75rem;">
                💡 Pour démarrer automatiquement : <code>bash install_linux.sh --autostart</code>
            </p>
        </div>
    </div>

    <div style="margin-top:1rem;padding:.75rem 1rem;background:rgba(255,193,7,.1);border-radius:6px;font-size:.82rem;color:var(--muted);">
        ⚠️ <strong>Note :</strong> Tesseract OCR est requis et installé automatiquement par les scripts.
        Sur Windows, le script télécharge l'installateur depuis le site officiel.
        Une connexion internet est nécessaire lors de la première installation.
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
