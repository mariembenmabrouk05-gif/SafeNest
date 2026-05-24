#!/usr/bin/env python3
"""
SafeNest – Python API client example
───────────────────────────────────────────
Demonstrates how to POST threat events to api/add_event.php
from a monitoring script.

Usage:
    python3 python_client_example.py

Requirements:
    pip install requests
"""

import requests
import json

# ── Configuration ────────────────────────────────────────
BASE_URL  = "http://localhost/safenest"   # adjust to your host
API_KEY   = ""                                  # set if you enabled API_KEY in add_event.php
CHILD_ID  = 1                                   # replace with a real child user id


def send_threat_event(
    child_id:    int,
    threat_type: str,
    severity:    str,   # "low" | "medium" | "high"
    context:     str,
) -> dict:
    """
    POST a threat event to the SafeNest API endpoint.
    Returns the parsed JSON response.
    """
    url     = f"{BASE_URL}/api/add_event.php"
    headers = {"Content-Type": "application/json"}

    if API_KEY:
        headers["X-Api-Key"] = API_KEY

    payload = {
        "child_id":    child_id,
        "threat_type": threat_type,
        "severity":    severity,
        "context":     context,
    }

    response = requests.post(url, headers=headers, json=payload, timeout=10)

    try:
        data = response.json()
    except json.JSONDecodeError:
        data = {"raw": response.text}

    print(f"HTTP {response.status_code}  →  {data}")
    return data


# ── Example calls ────────────────────────────────────────
if __name__ == "__main__":
    # Example 1 – low severity
    send_threat_event(
        child_id    = CHILD_ID,
        threat_type = "inappropriate_search",
        severity    = "low",
        context     = "Child searched for 'violent video game cheats' at 21:45.",
    )

    # Example 2 – high severity
    send_threat_event(
        child_id    = CHILD_ID,
        threat_type = "cyberbullying",
        severity    = "high",
        context     = (
            "Detected an incoming message containing targeted insults "
            "from an unknown contact. Message flagged by keyword filter."
        ),
    )

    # Example 3 – medium severity
    send_threat_event(
        child_id    = CHILD_ID,
        threat_type = "suspicious_link",
        severity    = "medium",
        context     = "Child clicked a shortened URL (bit.ly/xxxxx) of unknown origin.",
    )

    print("\nDone — check the parent dashboard to see the events.")
