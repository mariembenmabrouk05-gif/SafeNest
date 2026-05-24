<?php
/**
 * parent_portal.php
 * ─────────────────
 * Accessed from the CHILD'S PC while the child is logged in.
 * A parent enters their credentials here to authenticate,
 * then downloads the pre-configured monitor script for this child.
 *
 * Session must have role = 'child'.
 */
require 'config.php';
require_role('child');

$dark      = $_COOKIE['theme'] ?? 'light';
$child_id  = (int)$_SESSION['user_id'];
$child_name = $_SESSION['username'];

// Auto-detect server URL
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_url = "$proto://$host$dir";

$error          = '';
$parent_ok      = false;
$parent_data    = null;
$download_url   = null;

// ── Helper: get or create the parent's monitor token ─────────
function ensure_parent_token(PDO $pdo, int $parent_id): string {
    $row = $pdo->prepare("SELECT monitor_token FROM users WHERE id = ?");
    $row->execute([$parent_id]);
    $row = $row->fetch();
    if (!empty($row['monitor_token'])) return $row['monitor_token'];
    $token = bin2hex(random_bytes(32)); // 64-char hex
    $pdo->prepare("UPDATE users SET monitor_token = ? WHERE id = ?")->execute([$token, $parent_id]);
    return $token;
}

// ── Handle parent login form submission ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'parent'");
        $stmt->execute([$username]);
        $parent = $stmt->fetch();

        if (!$parent || !password_verify($password, $parent['password'])) {
            $error = 'Identifiant ou mot de passe incorrect.';
        } else {
            // Verify this parent actually owns the currently logged-in child
            $owns = $pdo->prepare("SELECT id FROM users WHERE id = ? AND parent_id = ?");
            $owns->execute([$child_id, $parent['id']]);
            if (!$owns->fetch()) {
                $error = 'Ce compte parent n\'est pas associé à l\'enfant connecté.';
            } else {
                $parent_ok   = true;
                $parent_data = $parent;
                $token = ensure_parent_token($pdo, $parent['id']);
                $download_url = "generate_download.php?child_id={$child_id}"
                              . "&parent_id={$parent['id']}"
                              . "&token=" . urlencode($token)
                              . "&url="   . urlencode($base_url);
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
    <title>Espace Parent — SafeNest</title>
    <link rel="stylesheet" href="static/style.css">
    <style>
        .portal-wrap {
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 1.5rem;
        }
        .portal-card {
            width: 100%; max-width: 420px;
        }
        .portal-header {
            text-align: center; margin-bottom: 2rem;
        }
        .portal-header .icon {
            font-size: 2.75rem; margin-bottom: .6rem;
        }
        .portal-header h1 {
            font-size: 1.35rem; font-weight: 700; margin-bottom: .25rem;
        }
        .portal-header .sub {
            color: var(--muted); font-size: .875rem; line-height: 1.5;
        }
        .child-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .3rem .75rem; border-radius: 999px;
            background: rgba(16,185,129,.12); color: var(--green);
            font-size: .82rem; font-weight: 600; margin-top: .5rem;
        }
        .download-box {
            border: 1.5px solid var(--green);
            border-radius: 12px;
            padding: 1.5rem;
            background: rgba(16,185,129,.05);
            text-align: center;
        }
        .download-box .big-icon { font-size: 2.5rem; margin-bottom: .75rem; }
        .download-box h2 { font-size: 1rem; font-weight: 700; margin-bottom: .4rem; }
        .download-box p  { color: var(--muted); font-size: .82rem; line-height: 1.6; margin-bottom: 1.25rem; }
        .btn-download {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .7rem 1.4rem; border-radius: 8px;
            background: var(--green); color: #fff;
            font-weight: 700; font-size: .9rem;
            text-decoration: none; transition: opacity .2s;
        }
        .btn-download:hover { opacity: .88; }
        .install-steps {
            margin-top: 1.5rem; text-align: left;
            border-top: 1px solid var(--border); padding-top: 1.25rem;
        }
        .install-steps h3 { font-size: .82rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .75rem; }
        .step { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .6rem; }
        .step-num {
            flex-shrink: 0; width: 22px; height: 22px;
            border-radius: 50%; background: var(--green); color: #fff;
            font-size: .72rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .step-text { font-size: .82rem; color: var(--muted); line-height: 1.5; }
        .step-text code { background: var(--border); padding: .1em .3em; border-radius: 3px; font-size: .78rem; }
        .back-link {
            display: block; text-align: center; margin-top: 1.25rem;
            color: var(--muted); font-size: .82rem; text-decoration: none;
        }
        .back-link:hover { color: var(--text); }
    </style>
</head>
<body class="<?= $dark === 'dark' ? 'dark' : '' ?>">
<div class="portal-wrap">
    <div class="card portal-card">

        <?php if (!$parent_ok): ?>
        <!-- ── Parent login form ─────────────────────────────── -->
        <div class="portal-header">
            <div class="icon">🔐</div>
            <h1>Espace Parent</h1>
            <p class="sub">
                Connectez-vous pour accéder à l'espace parent
                et télécharger le moniteur sur ce PC.
            </p>
            <div class="child-chip">
                👦 PC de : <?= htmlspecialchars($child_name) ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.4rem;font-weight:500;">
                    Nom d'utilisateur parent
                </label>
                <input type="text" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div style="margin-bottom:1.25rem;">
                <label style="display:block;font-size:.82rem;color:var(--muted);margin-bottom:.4rem;font-weight:500;">
                    Mot de passe
                </label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-green w-full">Confirmer l'identité</button>
        </form>

        <a href="child_dashboard.php" class="back-link">← Retour à l'espace enfant</a>

        <?php else: ?>
        <!-- ── Authenticated — show download ────────────────── -->
        <div class="portal-header">
            <div class="icon">✅</div>
            <h1>Bienvenue, <?= htmlspecialchars($parent_data['username']) ?></h1>
            <p class="sub">Téléchargez le moniteur ci-dessous et installez-le sur ce PC.</p>
            <div class="child-chip">👦 Ce PC : <?= htmlspecialchars($child_name) ?></div>
        </div>

        <div class="download-box">
            <div class="big-icon">📦</div>
            <h2>Moniteur de <?= htmlspecialchars($child_name) ?></h2>
            <p>
                Le paquet est pré-configuré avec l'identité de votre enfant.
                Il suffit d'exécuter le script d'installation, aucune configuration supplémentaire.
            </p>
            <a href="<?= htmlspecialchars($download_url) ?>" class="btn-download">
                ⬇ Télécharger le paquet
            </a>

            <div class="install-steps">
                <h3>Installation rapide sur ce PC</h3>

                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">Décompressez le fichier ZIP dans un dossier permanent</div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <strong>Windows :</strong> double-cliquez <code>install_windows.bat</code><br>
                        <strong>Mac :</strong> <code>bash install_mac.sh</code><br>
                        <strong>Linux :</strong> <code>bash install_linux.sh</code>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        Obtenez une clé API gratuite sur
                        <a href="https://developers.perspectiveapi.com/" target="_blank" style="color:var(--green);">
                            Perspective API (Google)
                        </a>
                        et entrez-la quand le script le demande.
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-text">
                        <strong>Windows :</strong> double-cliquez <code>start_monitor.bat</code><br>
                        <strong>Mac/Linux :</strong> <code>bash start_monitor.sh</code>
                    </div>
                </div>
            </div>
        </div>

        <a href="child_dashboard.php" class="back-link">← Retour à l'espace enfant</a>

        <?php endif; ?>

    </div>
</div>
</body>
</html>
