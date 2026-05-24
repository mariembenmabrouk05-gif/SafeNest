<?php
/**
 * generate_download.php
 * Called from parent_portal.php after parent authenticates.
 * Streams a ready-to-run ZIP for the child's PC.
 */
require 'config.php';
require_role('child'); // must be called from child session

$child_id  = (int)($_GET['child_id']  ?? 0);
$parent_id = (int)($_GET['parent_id'] ?? 0);
$token     = trim($_GET['token']      ?? '');
$base_url  = rtrim(trim($_GET['url']  ?? ''), '/');

if (!$child_id || !$parent_id || !$token || !$base_url) {
    http_response_code(400); die('Paramètres manquants.');
}

// Verify: child = current session user, parent owns child, token matches
if ((int)$_SESSION['user_id'] !== $child_id) {
    http_response_code(403); die('Accès refusé.');
}

$row = $pdo->prepare("
    SELECT c.username AS child_name, p.monitor_token
    FROM users c
    JOIN users p ON p.id = c.parent_id AND p.id = ?
    WHERE c.id = ? AND c.role = 'child' AND p.role = 'parent'
");
$row->execute([$parent_id, $child_id]);
$row = $row->fetch();

if (!$row || !hash_equals($row['monitor_token'] ?? '', $token)) {
    http_response_code(403); die('Token invalide.');
}

$child_name = $row['child_name'];

// ═══════════════════════════════════════════════════════════════════
//  File contents
// ═══════════════════════════════════════════════════════════════════

// ── screen_monitor.py  (uses Google Perspective API — free) ────────
$py = <<<PYTHON
#!/usr/bin/env python3
"""
╔═══════════════════════════════════════════════════════════╗
║         SafeNest — Screen Monitor                         ║
║  Child  : {$child_name}                                   ║
║  Server : {$base_url}                                     ║
║  !! DO NOT SHARE — contains authentication token !!       ║
╚═══════════════════════════════════════════════════════════╝

Content safety powered by Google Perspective API (free).
Get your free API key at: https://developers.perspectiveapi.com/

Usage:
    python screen_monitor.py              (normal, captures every 10s)
    python screen_monitor.py --interval 20
    python screen_monitor.py --dry-run    (analyse only, no reports sent)
    python screen_monitor.py --monitor 2  (second screen)

Requirements (installed by install script):
    pip install mss pillow pytesseract requests
    + Tesseract OCR binary
"""

import os, sys, time, json, hashlib, logging, argparse
from datetime import datetime

# ── Pre-configured — set automatically by parent dashboard ───
BASE_URL      = "{$base_url}"
CHILD_ID      = {$child_id}
PARENT_TOKEN  = "{$token}"

# ── You must set this — get your free key at: ────────────────
# https://developers.perspectiveapi.com/s/docs-get-started
PERSPECTIVE_API_KEY = os.getenv("PERSPECTIVE_API_KEY", "")

# ── Settings ─────────────────────────────────────────────────
DEFAULT_INTERVAL = 10    # seconds between screen captures
MIN_TEXT_LEN     = 40    # skip frames with less text than this
DEDUP_WINDOW     = 60    # ignore identical text within N seconds

# ─────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  [%(levelname)s]  %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler("protect_monitor.log", encoding="utf-8"),
    ],
)
log = logging.getLogger("protect")

# ── Dependency check ─────────────────────────────────────────
_missing = []
try: import mss
except ImportError: _missing.append("mss")
try: from PIL import Image, ImageEnhance, ImageFilter
except ImportError: _missing.append("pillow")
try: import pytesseract
except ImportError: _missing.append("pytesseract")
try: import requests
except ImportError: _missing.append("requests")

if _missing:
    sys.exit(
        "\\n❌ Packages manquants: " + ", ".join(_missing) +
        "\\n   Lancez le script d'installation d'abord."
    )

import mss, requests
from PIL import Image, ImageEnhance, ImageFilter
import pytesseract


# ── Screen capture ─────────────────────────────────────────────
def capture(monitor_idx: int = 1) -> Image.Image:
    with mss.mss() as sct:
        m = sct.monitors
        raw = sct.grab(m[min(monitor_idx, len(m) - 1)])
        return Image.frombytes("RGB", raw.size, raw.bgra, "raw", "BGRX")


def ocr(img: Image.Image) -> str:
    img = img.convert("L")
    img = ImageEnhance.Contrast(img).enhance(2.0)
    img = img.filter(ImageFilter.SHARPEN)
    lines = [l.strip() for l in pytesseract.image_to_string(img, config="--psm 3").splitlines() if l.strip()]
    return "\\n".join(lines)


# ── Perspective API content safety ────────────────────────────
PERSPECTIVE_URL = "https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze"

# Map Perspective attributes → (threat_type, weight)
ATTRIBUTES = {
    "TOXICITY":           ("cyberbullying",         1.0),
    "SEVERE_TOXICITY":    ("cyberbullying",         1.5),
    "IDENTITY_ATTACK":    ("hate_speech",           1.4),
    "INSULT":             ("cyberbullying",         0.9),
    "THREAT":             ("cyberbullying",         1.3),
    "SEXUALLY_EXPLICIT":  ("inappropriate_content", 1.5),
    "PROFANITY":          ("inappropriate_content", 0.8),
}

# Score thresholds → severity
def score_to_severity(score: float) -> str | None:
    if score >= 0.85: return "high"
    if score >= 0.65: return "medium"
    if score >= 0.50: return "low"
    return None


def analyse(text: str) -> dict:
    """
    Call Perspective API. Returns:
        { threat_detected, threat_type, severity, score, explanation }
    """
    if not PERSPECTIVE_API_KEY:
        raise RuntimeError("PERSPECTIVE_API_KEY is not set.")

    payload = {
        "comment":              {"text": text[:3000]},
        "languages":            ["fr", "en"],
        "requestedAttributes":  {attr: {} for attr in ATTRIBUTES},
    }
    try:
        resp = requests.post(
            PERSPECTIVE_URL,
            params={"key": PERSPECTIVE_API_KEY},
            json=payload,
            timeout=10,
        )
        resp.raise_for_status()
        scores = resp.json().get("attributeScores", {})
    except requests.HTTPError as e:
        log.error("Perspective API HTTP error: %s", e)
        return {"threat_detected": False}
    except Exception as e:
        log.error("Perspective API error: %s", e)
        return {"threat_detected": False}

    # Find the highest weighted score
    best_attr   = None
    best_score  = 0.0
    for attr, (_, weight) in ATTRIBUTES.items():
        raw = scores.get(attr, {}).get("summaryScore", {}).get("value", 0.0)
        weighted = raw * weight
        if weighted > best_score:
            best_score = weighted
            best_attr  = attr
            best_raw   = raw

    if best_attr is None:
        return {"threat_detected": False}

    severity = score_to_severity(best_raw)
    if severity is None:
        return {"threat_detected": False}

    threat_type, _ = ATTRIBUTES[best_attr]
    return {
        "threat_detected": True,
        "threat_type":     threat_type,
        "severity":        severity,
        "score":           round(best_raw, 3),
        "attribute":       best_attr,
        "explanation":     f"Perspective API flagged {best_attr} at {best_raw:.0%}.",
    }


# ── Post to PHP API ────────────────────────────────────────────
def post_event(threat_type: str, severity: str, context: str) -> dict:
    url = BASE_URL.rstrip("/") + "/api/add_event.php"
    try:
        r = requests.post(url, json={
            "child_id":     CHILD_ID,
            "parent_token": PARENT_TOKEN,
            "threat_type":  threat_type,
            "severity":     severity,
            "context":      context,
        }, timeout=10)
        return r.json()
    except Exception as e:
        return {"error": str(e)}


# ── De-duplication ─────────────────────────────────────────────
class Dedup:
    def __init__(self, w=DEDUP_WINDOW):
        self._c: dict = {}; self._w = w
    def seen(self, t: str) -> bool:
        h = hashlib.md5(t.encode()).hexdigest(); now = time.time()
        if h in self._c and now - self._c[h] < self._w: return True
        self._c[h] = now
        if len(self._c) > 300: self._c = {k:v for k,v in self._c.items() if now-v < self._w}
        return False


# ── Main loop ──────────────────────────────────────────────────
def run(interval: int, monitor_idx: int, dry_run: bool):
    if not PERSPECTIVE_API_KEY:
        sys.exit(
            "\\n❌ PERSPECTIVE_API_KEY manquante."
            "\\n   Obtenez une clé gratuite sur https://developers.perspectiveapi.com/"
            "\\n   Puis définissez-la :"
            "\\n     Windows : set PERSPECTIVE_API_KEY=AIza..."
            "\\n     Mac/Linux: export PERSPECTIVE_API_KEY=AIza..."
        )

    dedup = Dedup()
    log.info("🛡  Protect Monitor démarré  (enfant: {$child_name})")
    log.info("   Serveur  : %s", BASE_URL)
    log.info("   Intervalle: %ds  |  Dry-run: %s", interval, dry_run)
    log.info("Appuyez sur Ctrl-C pour arrêter.\\n")

    while True:
        try:
            text = ocr(capture(monitor_idx))
            if len(text) < MIN_TEXT_LEN or dedup.seen(text):
                time.sleep(interval); continue

            log.info("🔍 Analyse en cours …")
            result = analyse(text)

            if not result.get("threat_detected"):
                log.info("✅ Aucune menace  (score %.2f)", result.get("score", 0))
                time.sleep(interval); continue

            t_type  = result["threat_type"]
            sev     = result["severity"]
            score   = result.get("score", 0)
            ts      = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            context = (f"[{ts}] {result['explanation']}"
                      f"  Attribut: {result.get('attribute','')}  Score: {score:.0%}")

            log.warning("⚠️  MENACE  type=%-26s  sév=%s  score=%.0f%%", t_type, sev, score*100)

            if dry_run:
                log.info("[DRY-RUN] Envoi simulé: type=%s sév=%s", t_type, sev)
            else:
                resp = post_event(t_type, sev, context)
                if resp.get("success"):
                    log.info("✔  Événement enregistré  (id=%s)", resp.get("id"))
                else:
                    log.error("✗  Erreur API: %s", resp)

        except KeyboardInterrupt:
            log.info("\\n👋 Moniteur arrêté."); break
        except Exception as e:
            log.exception("Erreur inattendue: %s", e)

        time.sleep(interval)


if __name__ == "__main__":
    p = argparse.ArgumentParser(description="SafeNest Screen Monitor")
    p.add_argument("--interval", type=int,  default=DEFAULT_INTERVAL, help="Secondes entre captures")
    p.add_argument("--monitor",  type=int,  default=1,                help="Index de l'écran (1=principal)")
    p.add_argument("--dry-run",  action="store_true",                 help="Analyse sans envoyer d'alertes")
    a = p.parse_args()
    run(a.interval, a.monitor, a.dry_run)
PYTHON;


// ── install_windows.bat ─────────────────────────────────────
$win_install = <<<'BAT'
@echo off
title SafeNest — Installation Windows
color 0A
echo.
echo  ============================================================
echo    SAFENEST — Installation
echo  ============================================================
echo.

:: ── Python ───────────────────────────────────────────────────
python --version >nul 2>&1
if errorlevel 1 (
    echo [1/3] Python non detecte. Telechargement...
    powershell -Command "Invoke-WebRequest -Uri 'https://www.python.org/ftp/python/3.12.0/python-3.12.0-amd64.exe' -OutFile python_setup.exe"
    echo [1/3] Installation de Python...
    python_setup.exe /quiet InstallAllUsers=1 PrependPath=1
    del python_setup.exe
    echo [OK] Python installe. Relancez ce script.
    pause & exit /b
)
echo [OK] Python detecte.

:: ── pip packages ─────────────────────────────────────────────
echo [2/3] Installation des dependances...
pip install mss pillow pytesseract requests --quiet --upgrade
if errorlevel 1 ( echo [!] Erreur pip. Verifiez internet. & pause & exit /b 1 )
echo [OK] Dependances installees.

:: ── Tesseract OCR ─────────────────────────────────────────────
where tesseract >nul 2>&1
if errorlevel 1 (
    echo [3/3] Tesseract OCR non detecte. Telechargement...
    powershell -Command "Invoke-WebRequest -Uri 'https://github.com/UB-Mannheim/tesseract/releases/download/v5.3.3.20231005/tesseract-ocr-w64-setup-5.3.3.20231005.exe' -OutFile tesseract_setup.exe"
    tesseract_setup.exe /S
    del tesseract_setup.exe
    setx PATH "%PATH%;C:\Program Files\Tesseract-OCR" /M
    echo [OK] Tesseract installe.
) else ( echo [OK] Tesseract detecte. )

echo.
echo  ============================================================
echo    Installation terminee !
echo.
echo    Prochaine etape : obtenez une cle API gratuite sur
echo    https://developers.perspectiveapi.com/
echo.
echo    Puis lancez start_monitor.bat
echo  ============================================================
echo.
pause
BAT;


// ── start_monitor.bat ───────────────────────────────────────
$win_start = <<<'BAT'
@echo off
title SafeNest — Moniteur actif
color 0A

if "%PERSPECTIVE_API_KEY%"=="" (
    echo.
    echo  Cle API Perspective manquante.
    echo  Obtenez-la gratuitement sur : https://developers.perspectiveapi.com/
    echo.
    set /p PERSPECTIVE_API_KEY="Entrez votre cle API : "
    setx PERSPECTIVE_API_KEY "%PERSPECTIVE_API_KEY%"
    echo [OK] Cle enregistree pour les prochains demarrages.
)

echo  Demarrage du moniteur...
python screen_monitor.py %*
BAT;


// ── install_mac.sh ─────────────────────────────────────────
$mac_sh = <<<'SH'
#!/bin/bash
set -e
AUTOSTART=false; [[ "$1" == "--autostart" ]] && AUTOSTART=true
echo ""
echo " ════════════════════════════════════════"
echo "   SafeNest — Installation macOS"
echo " ════════════════════════════════════════"

command -v python3 &>/dev/null || { echo "❌ Python 3 requis. Installez depuis https://www.python.org/"; exit 1; }
echo "[OK] Python: $(python3 --version)"

if ! command -v tesseract &>/dev/null; then
    echo "[*] Installation Tesseract via Homebrew..."
    command -v brew &>/dev/null || /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    brew install tesseract
fi
echo "[OK] Tesseract: $(tesseract --version 2>&1 | head -1)"

echo "[*] Dépendances Python..."
pip3 install mss pillow pytesseract requests --quiet --upgrade
echo "[OK] Dépendances installées."

if $AUTOSTART; then
    PLIST="$HOME/Library/LaunchAgents/com.protect.monitor.plist"
    SCRIPT="$(cd "$(dirname "$0")" && pwd)/start_monitor.sh"
    cat > "$PLIST" << PLIST
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>com.protect.monitor</string>
  <key>ProgramArguments</key><array><string>/bin/bash</string><string>$SCRIPT</string></array>
  <key>RunAtLoad</key><true/><key>KeepAlive</key><true/>
</dict></plist>
PLIST
    launchctl load "$PLIST" && echo "[OK] Démarrage automatique configuré."
fi

echo ""
echo " Installation terminée !"
echo " Obtenez votre clé API gratuite : https://developers.perspectiveapi.com/"
echo " Puis lancez : bash start_monitor.sh"
SH;


// ── install_linux.sh ───────────────────────────────────────
$linux_sh = <<<'SH'
#!/bin/bash
set -e
AUTOSTART=false; [[ "$1" == "--autostart" ]] && AUTOSTART=true
echo ""
echo " ════════════════════════════════════════"
echo "   SafeNest — Installation Linux"
echo " ════════════════════════════════════════"

if ! command -v tesseract &>/dev/null; then
    echo "[*] Installation Tesseract..."
    if   command -v apt-get &>/dev/null; then sudo apt-get update -qq && sudo apt-get install -y tesseract-ocr
    elif command -v dnf     &>/dev/null; then sudo dnf install -y tesseract
    elif command -v pacman  &>/dev/null; then sudo pacman -S --noconfirm tesseract
    else echo "❌ Installez tesseract manuellement."; exit 1; fi
fi
echo "[OK] Tesseract: $(tesseract --version 2>&1 | head -1)"

echo "[*] Dépendances Python..."
pip3 install mss pillow pytesseract requests --quiet --upgrade --break-system-packages 2>/dev/null \
    || pip3 install mss pillow pytesseract requests --quiet --upgrade
echo "[OK] Dépendances installées."

if $AUTOSTART && command -v systemctl &>/dev/null; then
    SCRIPT="$(cd "$(dirname "$0")" && pwd)/start_monitor.sh"
    mkdir -p "$HOME/.config/systemd/user"
    cat > "$HOME/.config/systemd/user/protect-monitor.service" << SVC
[Unit]
Description=SafeNest Monitor
After=graphical.target
[Service]
ExecStart=/bin/bash $SCRIPT
Restart=on-failure
RestartSec=10
Environment=DISPLAY=:0
[Install]
WantedBy=default.target
SVC
    systemctl --user daemon-reload
    systemctl --user enable --now protect-monitor.service
    echo "[OK] Service systemd activé."
fi

echo ""
echo " Installation terminée !"
echo " Obtenez votre clé API gratuite : https://developers.perspectiveapi.com/"
echo " Puis lancez : bash start_monitor.sh"
SH;


// ── start_monitor.sh ───────────────────────────────────────
$sh_start = <<<'SH'
#!/bin/bash
DIR="$(cd "$(dirname "$0")" && pwd)"; cd "$DIR"

if [[ -z "$PERSPECTIVE_API_KEY" ]]; then
    echo ""
    echo "  Clé API Perspective API manquante."
    echo "  Obtenez-la gratuitement : https://developers.perspectiveapi.com/"
    echo ""
    read -rp "  Entrez votre clé API : " key
    export PERSPECTIVE_API_KEY="$key"
    echo "export PERSPECTIVE_API_KEY=$key" >> ~/.bashrc 2>/dev/null
    echo "[OK] Clé enregistrée."
fi

echo "[*] Démarrage du moniteur..."
python3 screen_monitor.py "$@"
SH;


// ── README ─────────────────────────────────────────────────
$readme = <<<README
╔══════════════════════════════════════════════════════════╗
║   SAFENEST — Guide d'installation                        ║
╚══════════════════════════════════════════════════════════╝

Enfant surveillé : {$child_name}
Tableau de bord  : {$base_url}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 AVANT DE COMMENCER — Clé API gratuite (obligatoire)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Le script utilise Google Perspective API (gratuit) pour
détecter le contenu inapproprié.

1. Allez sur : https://developers.perspectiveapi.com/
2. Cliquez "Get Started" et connectez-vous avec un compte Google
3. Créez un projet → activez "Perspective Comment Analyzer API"
4. Créez une clé API (Credentials → Create credentials)
   → Copiez votre clé (commence par "AIza...")

Le script vous demandera cette clé au premier démarrage.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 INSTALLATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

WINDOWS:
  1. Double-cliquez install_windows.bat
  2. Double-cliquez start_monitor.bat  (entrez votre clé API)

MACOS:
  1. Terminal: bash install_mac.sh
  2. Terminal: bash start_monitor.sh

LINUX:
  1. Terminal: bash install_linux.sh
  2. Terminal: bash start_monitor.sh

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 DÉMARRAGE AUTOMATIQUE (optionnel)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Mac   : bash install_mac.sh --autostart
Linux : bash install_linux.sh --autostart
Windows: copiez start_monitor.bat dans Win+R → shell:startup

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 COMMENT ÇA MARCHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Capture l'écran toutes les 10 secondes
2. Extrait le texte visible (OCR Tesseract)
3. Envoie à Perspective API pour analyse de sécurité
4. Si contenu problématique → alerte envoyée au tableau de bord

Ce paquet est lié à {$child_name} et ne doit pas être partagé.
README;


// ═══════════════════════════════════════════════════════════════════
//  Build & stream ZIP
// ═══════════════════════════════════════════════════════════════════

$tmp = tempnam(sys_get_temp_dir(), 'pprotect_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); die('Impossible de créer le ZIP.');
}
$zip->addFromString('screen_monitor.py',    $py);
$zip->addFromString('install_windows.bat',  $win_install);
$zip->addFromString('start_monitor.bat',    $win_start);
$zip->addFromString('install_mac.sh',       $mac_sh);
$zip->addFromString('install_linux.sh',     $linux_sh);
$zip->addFromString('start_monitor.sh',     $sh_start);
$zip->addFromString('README_INSTALL.txt',   $readme);
$zip->close();

$filename = "protect_monitor_{$child_name}.zip";
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache');
readfile($tmp);
unlink($tmp);
exit;
