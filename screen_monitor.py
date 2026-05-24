#!/usr/bin/env python3
"""
╔══════════════════════════════════════════════════════════════╗
║                SafeNest — Screen Monitor                     ║
║  Captures screen  →  OCR  →  Claude safety check            ║
║  →  POST threat to api/add_event.php  →  Parent dashboard   ║
╚══════════════════════════════════════════════════════════════╝

Requirements:
    pip install mss pytesseract pillow anthropic requests

System:
    sudo apt-get install tesseract-ocr          (Linux)
    brew install tesseract                       (macOS)
    https://github.com/UB-Mannheim/tesseract/wiki (Windows)

Usage:
    # Copy .env.example to .env and fill in your values, then:
    python3 screen_monitor.py

    # Or pass args directly:
    python3 screen_monitor.py --child-id 3 --base-url http://localhost/safenest
"""

import os
import sys
import time
import json
import hashlib
import logging
import argparse
import textwrap
from datetime import datetime
from pathlib import Path

# ── Optional .env loading ─────────────────────────────────────────────────────
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv not installed, rely on real env vars

# ── Third-party imports ───────────────────────────────────────────────────────
try:
    import mss
    import mss.tools
except ImportError:
    sys.exit("❌  Install mss:  pip install mss")

try:
    from PIL import Image, ImageEnhance, ImageFilter
except ImportError:
    sys.exit("❌  Install Pillow:  pip install pillow")

try:
    import pytesseract
except ImportError:
    sys.exit("❌  Install pytesseract:  pip install pytesseract")

try:
    import anthropic
except ImportError:
    sys.exit("❌  Install anthropic SDK:  pip install anthropic")

try:
    import requests
except ImportError:
    sys.exit("❌  Install requests:  pip install requests")


# ─────────────────────────────────────────────────────────────────────────────
#  Configuration
# ─────────────────────────────────────────────────────────────────────────────

DEFAULT_BASE_URL   = os.getenv("PROTECT_BASE_URL",  "http://localhost/safenest")
DEFAULT_API_KEY    = os.getenv("PROTECT_API_KEY",   "")           # PHP-side API key (optional)
DEFAULT_CHILD_ID   = int(os.getenv("PROTECT_CHILD_ID", "1"))
DEFAULT_INTERVAL   = int(os.getenv("PROTECT_INTERVAL",  "10"))    # seconds between captures
ANTHROPIC_API_KEY  = os.getenv("ANTHROPIC_API_KEY", "")

# Minimum OCR confidence: only analyse frames with at least this many characters
MIN_TEXT_LENGTH    = 30

# Avoid re-reporting identical text blocks within this many seconds
DEDUP_WINDOW_SEC   = 60

# ─────────────────────────────────────────────────────────────────────────────
#  Logging
# ─────────────────────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  [%(levelname)s]  %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler("screen_monitor.log", encoding="utf-8"),
    ],
)
log = logging.getLogger("protect")


# ─────────────────────────────────────────────────────────────────────────────
#  Screen capture & OCR
# ─────────────────────────────────────────────────────────────────────────────

def capture_screen(monitor_index: int = 1) -> Image.Image:
    """Grab the full screen (or a specific monitor) and return a PIL Image."""
    with mss.mss() as sct:
        monitors = sct.monitors          # index 0 = all monitors combined
        idx = min(monitor_index, len(monitors) - 1)
        raw = sct.grab(monitors[idx])
        return Image.frombytes("RGB", raw.size, raw.bgra, "raw", "BGRX")


def preprocess_for_ocr(img: Image.Image) -> Image.Image:
    """
    Sharpen, increase contrast, and convert to greyscale so Tesseract
    works better on colourful / anti-aliased screen fonts.
    """
    img = img.convert("L")                         # greyscale
    img = ImageEnhance.Contrast(img).enhance(2.0)  # boost contrast
    img = img.filter(ImageFilter.SHARPEN)
    return img


def extract_text(img: Image.Image) -> str:
    """Run Tesseract OCR and return cleaned text."""
    processed = preprocess_for_ocr(img)
    # PSM 3 = fully automatic page segmentation (good for mixed screen content)
    config = "--psm 3 --oem 3 -l eng+fra"
    raw = pytesseract.image_to_string(processed, config=config)
    # Collapse blank lines and strip artefacts
    lines = [ln.strip() for ln in raw.splitlines() if ln.strip()]
    return "\n".join(lines)


# ─────────────────────────────────────────────────────────────────────────────
#  Content-safety analysis via Claude
# ─────────────────────────────────────────────────────────────────────────────

SAFETY_SYSTEM_PROMPT = textwrap.dedent("""
    You are a child-safety content classifier for a parental monitoring application.
    You will receive text extracted from a child's screen via OCR.

    Your job is to detect any of the following threat categories:
      • cyberbullying        – harassment, insults, threats directed at or from the child
      • inappropriate_content – violence, gore, adult/sexual content, self-harm promotion
      • hate_speech          – slurs, discrimination, extremist language
      • grooming             – suspicious adults asking for private info, meetings, photos
      • dangerous_challenge  – viral challenges that risk physical harm
      • drug_alcohol         – promotion or discussion of substance use
      • inappropriate_search – search queries for explicitly harmful topics
      • suspicious_link      – phishing URLs, shortened links in suspicious context

    Respond ONLY with valid JSON — no markdown fences, no extra text:

    {
      "threat_detected": true | false,
      "threat_type":     "<category from list above or null>",
      "severity":        "low" | "medium" | "high" | null,
      "confidence":      0.0–1.0,
      "explanation":     "<one sentence summarising what was found>",
      "flagged_excerpt": "<the exact short excerpt that triggered the flag, max 120 chars, or null>"
    }

    Rules:
    - Only flag content that poses a genuine risk to a minor.
    - Normal homework, gaming, social-media small-talk → threat_detected: false.
    - severity "high"   → immediate danger (e.g. explicit grooming, direct threat of violence).
    - severity "medium" → concerning but not immediate (e.g. bullying, adult content).
    - severity "low"    → borderline / worth logging (e.g. mild profanity, suspicious link).
    - When threat_detected is false, set threat_type, severity, flagged_excerpt to null.
""").strip()


def analyse_with_claude(text: str, client: anthropic.Anthropic) -> dict:
    """
    Send the OCR text to Claude and get a structured safety assessment.
    Returns the parsed JSON dict.
    """
    message = client.messages.create(
        model="claude-sonnet-4-6",
        max_tokens=512,
        system=SAFETY_SYSTEM_PROMPT,
        messages=[
            {
                "role": "user",
                "content": (
                    f"Analyse the following text extracted from a child's screen:\n\n"
                    f"---\n{text[:4000]}\n---"   # cap at 4000 chars
                ),
            }
        ],
    )

    raw_json = message.content[0].text.strip()

    # Strip accidental markdown fences if the model adds them anyway
    if raw_json.startswith("```"):
        raw_json = raw_json.split("```")[1]
        if raw_json.startswith("json"):
            raw_json = raw_json[4:]

    return json.loads(raw_json)


# ─────────────────────────────────────────────────────────────────────────────
#  Post threat event to PHP API
# ─────────────────────────────────────────────────────────────────────────────

def post_threat_event(
    base_url:    str,
    api_key:     str,
    child_id:    int,
    threat_type: str,
    severity:    str,
    context:     str,
) -> dict:
    """POST a threat event to api/add_event.php and return the JSON response."""

    url     = f"{base_url.rstrip('/')}/api/add_event.php"
    headers = {"Content-Type": "application/json"}
    if api_key:
        headers["X-Api-Key"] = api_key

    payload = {
        "child_id":    child_id,
        "threat_type": threat_type,
        "severity":    severity,
        "context":     context,
    }

    try:
        resp = requests.post(url, headers=headers, json=payload, timeout=10)
        try:
            return resp.json()
        except Exception:
            return {"raw": resp.text, "status_code": resp.status_code}
    except requests.RequestException as exc:
        log.error("Failed to reach API: %s", exc)
        return {"error": str(exc)}


# ─────────────────────────────────────────────────────────────────────────────
#  De-duplication helper
# ─────────────────────────────────────────────────────────────────────────────

class DedupCache:
    """
    Simple in-memory cache that prevents the same text block from triggering
    multiple identical threat reports within DEDUP_WINDOW_SEC seconds.
    """

    def __init__(self, window_sec: int = DEDUP_WINDOW_SEC):
        self._cache: dict[str, float] = {}
        self._window = window_sec

    def _hash(self, text: str) -> str:
        return hashlib.md5(text.encode()).hexdigest()

    def is_duplicate(self, text: str) -> bool:
        h = self._hash(text)
        now = time.time()
        if h in self._cache and (now - self._cache[h]) < self._window:
            return True
        self._cache[h] = now
        # Prune old entries occasionally
        if len(self._cache) > 500:
            cutoff = now - self._window
            self._cache = {k: v for k, v in self._cache.items() if v > cutoff}
        return False


# ─────────────────────────────────────────────────────────────────────────────
#  Main monitoring loop
# ─────────────────────────────────────────────────────────────────────────────

def build_context(result: dict, text: str) -> str:
    """Compose a human-readable context string for the threat_events table."""
    ts      = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    excerpt = result.get("flagged_excerpt") or ""
    explain = result.get("explanation", "")
    conf    = result.get("confidence", 0)
    lines   = [
        f"[{ts}] Auto-detected by screen monitor (confidence {conf:.0%}).",
        explain,
    ]
    if excerpt:
        lines.append(f'Flagged excerpt: "{excerpt}"')
    return "  ".join(filter(None, lines))


def run(
    child_id:      int,
    base_url:      str,
    api_key:       str,
    interval_sec:  int,
    monitor_index: int,
    dry_run:       bool,
):
    if not ANTHROPIC_API_KEY:
        sys.exit(
            "❌  ANTHROPIC_API_KEY is not set.\n"
            "    Export it as an environment variable or add it to a .env file."
        )

    claude  = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)
    dedup   = DedupCache()

    log.info("🛡️  Protect screen monitor started")
    log.info("   Child ID   : %d", child_id)
    log.info("   API target : %s", base_url)
    log.info("   Interval   : %ds", interval_sec)
    log.info("   Dry-run    : %s", dry_run)
    log.info("Press Ctrl-C to stop.\n")

    while True:
        try:
            # 1 ── Capture screen
            log.debug("Capturing screen …")
            img  = capture_screen(monitor_index)

            # 2 ── OCR
            text = extract_text(img)
            if len(text) < MIN_TEXT_LENGTH:
                log.debug("Not enough text on screen (%d chars), skipping.", len(text))
                time.sleep(interval_sec)
                continue

            log.debug("OCR extracted %d chars.", len(text))

            # 3 ── De-duplicate
            if dedup.is_duplicate(text):
                log.debug("Duplicate frame, skipping Claude call.")
                time.sleep(interval_sec)
                continue

            # 4 ── Claude safety analysis
            log.info("🔍 Analysing screen content …")
            result = analyse_with_claude(text, claude)
            log.debug("Claude result: %s", json.dumps(result, indent=2))

            if not result.get("threat_detected"):
                log.info("✅ No threat detected (confidence %.0f%%)",
                         result.get("confidence", 0) * 100)
                time.sleep(interval_sec)
                continue

            # 5 ── Threat found — report it
            threat_type = result["threat_type"]
            severity    = result["severity"]
            context     = build_context(result, text)

            log.warning(
                "⚠️  THREAT DETECTED  type=%-28s severity=%s  conf=%.0f%%",
                threat_type, severity, result.get("confidence", 0) * 100,
            )
            log.warning("   %s", result.get("explanation", ""))

            if dry_run:
                log.info("[DRY-RUN] Would POST: child_id=%d  type=%s  severity=%s",
                         child_id, threat_type, severity)
            else:
                response = post_threat_event(
                    base_url    = base_url,
                    api_key     = api_key,
                    child_id    = child_id,
                    threat_type = threat_type,
                    severity    = severity,
                    context     = context,
                )
                if response.get("success"):
                    log.info("✔  Event saved to dashboard  (id=%s)", response.get("id"))
                else:
                    log.error("✗  API rejected the event: %s", response)

        except KeyboardInterrupt:
            log.info("\n👋 Monitor stopped by user.")
            break
        except json.JSONDecodeError as exc:
            log.error("Claude returned non-JSON response: %s", exc)
        except Exception as exc:
            log.exception("Unexpected error: %s", exc)

        time.sleep(interval_sec)


# ─────────────────────────────────────────────────────────────────────────────
#  CLI entry point
# ─────────────────────────────────────────────────────────────────────────────

def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="SafeNest – real-time screen safety monitor",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=textwrap.dedent("""
            Examples:
              python3 screen_monitor.py
              python3 screen_monitor.py --child-id 3 --interval 15
              python3 screen_monitor.py --dry-run
              python3 screen_monitor.py --base-url http://192.168.1.10/safenest --child-id 2
        """),
    )
    p.add_argument("--child-id",    type=int,   default=DEFAULT_CHILD_ID,
                   help="DB id of the child user to report events against (default: %(default)s)")
    p.add_argument("--base-url",    type=str,   default=DEFAULT_BASE_URL,
                   help="Base URL of the SafeNest PHP app (default: %(default)s)")
    p.add_argument("--api-key",     type=str,   default=DEFAULT_API_KEY,
                   help="PHP-side X-Api-Key (optional, leave blank if not set)")
    p.add_argument("--interval",    type=int,   default=DEFAULT_INTERVAL,
                   help="Seconds between screen captures (default: %(default)s)")
    p.add_argument("--monitor",     type=int,   default=1,
                   help="Monitor index to capture (1=primary, 2=secondary …, default: %(default)s)")
    p.add_argument("--dry-run",     action="store_true",
                   help="Analyse screen but do NOT post events to the PHP API")
    return p.parse_args()


if __name__ == "__main__":
    args = parse_args()
    run(
        child_id      = args.child_id,
        base_url      = args.base_url,
        api_key       = args.api_key,
        interval_sec  = args.interval,
        monitor_index = args.monitor,
        dry_run       = args.dry_run,
    )
